<?php

namespace OginiScoutDriver\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateChecker
{
    protected Client $httpClient;
    protected string $packageName;
    protected string $currentVersion;
    protected string $packagistUrl;
    protected string $githubUrl;
    protected int $cacheMinutes;

    public function __construct()
    {
        $this->httpClient = new Client([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]);

        $this->packageName = 'ogini-search/laravel-scout-driver';
        $this->packagistUrl = 'https://packagist.org/packages/' . $this->packageName . '.json';
        $this->githubUrl = 'https://api.github.com/repos/ogini-search/laravel-scout-driver/releases/latest';
        $this->cacheMinutes = 60; // Cache results for 1 hour

        // Initialize currentVersion to avoid property access before initialization
        $this->currentVersion = '';
    }

    /**
     * Check if there's a newer version available
     */
    public function hasUpdate(): bool
    {
        $latestVersion = $this->getLatestVersion();

        if (!$latestVersion) {
            return false;
        }

        return version_compare($this->currentVersion, $latestVersion, '<');
    }

    /**
     * Get the latest available version
     */
    public function getLatestVersion(): ?string
    {
        $cacheKey = 'ogini_scout_latest_version';

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () {
            return $this->fetchLatestVersionFromPackagist() ?? $this->fetchLatestVersionFromGitHub();
        });
    }

    /**
     * Get current installed version
     */
    public function getCurrentVersion(): string
    {
        if ($this->currentVersion !== '') {
            return $this->currentVersion;
        }

        // Try to get version from Composer's installed packages
        $composerLockPath = base_path('composer.lock');
        if (file_exists($composerLockPath)) {
            $composerLock = json_decode(file_get_contents($composerLockPath), true);

            foreach ($composerLock['packages'] ?? [] as $package) {
                if ($package['name'] === $this->packageName) {
                    $this->currentVersion = ltrim($package['version'], 'v');
                    return $this->currentVersion;
                }
            }
        }

        // Fallback to reading from package's composer.json
        $packageComposerPath = __DIR__ . '/../../composer.json';
        if (file_exists($packageComposerPath)) {
            $composer = json_decode(file_get_contents($packageComposerPath), true);
            $this->currentVersion = $composer['version'] ?? '1.0.0';
            return $this->currentVersion;
        }

        $this->currentVersion = '1.0.0'; // Default fallback
        return $this->currentVersion;
    }

    /**
     * Get update information including release notes
     */
    public function getUpdateInfo(): array
    {
        $latestVersion = $this->getLatestVersion();

        if (!$latestVersion || !$this->hasUpdate()) {
            return [
                'has_update' => false,
                'current_version' => $this->currentVersion,
                'latest_version' => $latestVersion,
            ];
        }

        $releaseNotes = $this->getReleaseNotes($latestVersion);

        return [
            'has_update' => true,
            'current_version' => $this->currentVersion,
            'latest_version' => $latestVersion,
            'release_notes' => $releaseNotes,
            'update_command' => 'composer update ogini-search/laravel-scout-driver',
            'security_update' => $this->isSecurityUpdate($releaseNotes),
            'breaking_changes' => $this->hasBreakingChanges($latestVersion),
        ];
    }

    /**
     * Get release notes for a specific version
     */
    public function getReleaseNotes(string $version): array
    {
        $cacheKey = "ogini_scout_release_notes_{$version}";

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($version) {
            return $this->fetchReleaseNotesFromGitHub($version);
        });
    }

    /**
     * Check for security updates
     */
    public function hasSecurityUpdate(): bool
    {
        $updateInfo = $this->getUpdateInfo();
        return $updateInfo['security_update'] ?? false;
    }

    /**
     * Clear update cache
     */
    public function clearCache(): void
    {
        // Get cached version before clearing it
        $cachedVersion = Cache::get('ogini_scout_latest_version');

        Cache::forget('ogini_scout_latest_version');

        // Clear release notes cache for the cached version if it exists
        if ($cachedVersion) {
            Cache::forget("ogini_scout_release_notes_{$cachedVersion}");
        }
    }

    /**
     * Fetch latest version from Packagist
     */
    protected function fetchLatestVersionFromPackagist(): ?string
    {
        try {
            $response = $this->httpClient->get($this->packagistUrl);
            $data = json_decode($response->getBody()->getContents(), true);

            $versions = array_keys($data['package']['versions'] ?? []);
            $stableVersions = array_filter($versions, function ($version) {
                return !preg_match('/-(alpha|beta|rc|dev)/i', $version);
            });

            if (empty($stableVersions)) {
                return null;
            }

            usort($stableVersions, 'version_compare');
            $latest = end($stableVersions);

            return ltrim($latest, 'v');
        } catch (RequestException $e) {
            Log::warning('Failed to fetch latest version from Packagist', [
                'error' => $e->getMessage(),
                'package' => $this->packageName,
            ]);
            return null;
        }
    }

    /**
     * Fetch latest version from GitHub releases
     */
    protected function fetchLatestVersionFromGitHub(): ?string
    {
        try {
            $response = $this->httpClient->get($this->githubUrl);
            $data = json_decode($response->getBody()->getContents(), true);

            return ltrim($data['tag_name'] ?? '', 'v');
        } catch (RequestException $e) {
            Log::warning('Failed to fetch latest version from GitHub', [
                'error' => $e->getMessage(),
                'package' => $this->packageName,
            ]);
            return null;
        }
    }

    /**
     * Fetch release notes from GitHub
     */
    protected function fetchReleaseNotesFromGitHub(string $version): array
    {
        try {
            $url = "https://api.github.com/repos/ogini-search/laravel-scout-driver/releases/tags/v{$version}";
            $response = $this->httpClient->get($url);
            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'version' => $version,
                'name' => $data['name'] ?? "Release {$version}",
                'body' => $data['body'] ?? 'No release notes available.',
                'published_at' => $data['published_at'] ?? null,
                'html_url' => $data['html_url'] ?? null,
                'prerelease' => $data['prerelease'] ?? false,
            ];
        } catch (RequestException $e) {
            Log::warning('Failed to fetch release notes from GitHub', [
                'error' => $e->getMessage(),
                'version' => $version,
            ]);

            return [
                'version' => $version,
                'name' => "Release {$version}",
                'body' => 'Release notes unavailable.',
                'published_at' => null,
                'html_url' => null,
                'prerelease' => false,
            ];
        }
    }

    /**
     * Check if the release contains security updates
     */
    protected function isSecurityUpdate(array $releaseNotes): bool
    {
        $securityKeywords = ['security', 'vulnerability', 'CVE-', 'exploit', 'patch'];
        $text = strtolower($releaseNotes['body'] ?? '');

        foreach ($securityKeywords as $keyword) {
            if (str_contains($text, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if there are breaking changes in the new version
     */
    protected function hasBreakingChanges(string $newVersion): bool
    {
        $currentMajor = (int) explode('.', $this->currentVersion)[0];
        $newMajor = (int) explode('.', $newVersion)[0];

        return $newMajor > $currentMajor;
    }
}
