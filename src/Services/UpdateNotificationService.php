<?php

namespace OginiScoutDriver\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use OginiScoutDriver\OginiServiceProvider;

class UpdateNotificationService
{
    protected string $packageName = 'ogini-search/laravel-scout-driver';
    protected string $packagistApiUrl = 'https://packagist.org/packages/';
    protected string $githubApiUrl = 'https://api.github.com/repos/ogini-search/laravel-scout-driver';
    protected int $cacheLifetime = 3600; // 1 hour

    /**
     * Check for available updates.
     *
     * @return array
     */
    public function checkForUpdates(): array
    {
        $cacheKey = 'ogini_driver_update_check';

        return Cache::remember($cacheKey, $this->cacheLifetime, function () {
            try {
                $currentVersion = OginiServiceProvider::VERSION;
                $latestVersion = $this->getLatestVersion();

                if (!$latestVersion) {
                    return [
                        'success' => false,
                        'message' => 'Could not fetch version information',
                        'current_version' => $currentVersion,
                    ];
                }

                $hasUpdate = version_compare($currentVersion, $latestVersion, '<');
                $securityUpdate = $this->checkForSecurityUpdates($currentVersion, $latestVersion);

                return [
                    'success' => true,
                    'current_version' => $currentVersion,
                    'latest_version' => $latestVersion,
                    'has_update' => $hasUpdate,
                    'is_security_update' => $securityUpdate,
                    'update_priority' => $this->getUpdatePriority($securityUpdate, $currentVersion, $latestVersion),
                    'release_notes' => $this->getReleaseNotes($latestVersion),
                    'checked_at' => now()->toISOString(),
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to check for Ogini Scout Driver updates', [
                    'error' => $e->getMessage(),
                    'package' => $this->packageName,
                ]);

                return [
                    'success' => false,
                    'message' => 'Update check failed: ' . $e->getMessage(),
                    'current_version' => OginiServiceProvider::VERSION,
                ];
            }
        });
    }

    /**
     * Get the latest version from Packagist.
     *
     * @return string|null
     */
    protected function getLatestVersion(): ?string
    {
        try {
            $response = Http::timeout(10)->get($this->packagistApiUrl . $this->packageName . '.json');

            if ($response->successful()) {
                $data = $response->json();
                $versions = array_keys($data['package']['versions'] ?? []);

                // Filter out dev versions and get the latest stable
                $stableVersions = array_filter($versions, function ($version) {
                    return !str_contains($version, 'dev') && preg_match('/^\d+\.\d+\.\d+$/', $version);
                });

                if (empty($stableVersions)) {
                    return null;
                }

                usort($stableVersions, 'version_compare');
                return end($stableVersions);
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Failed to fetch latest version from Packagist', [
                'error' => $e->getMessage(),
                'package' => $this->packageName,
            ]);

            return null;
        }
    }

    /**
     * Check for security updates.
     *
     * @param string $currentVersion
     * @param string $latestVersion
     * @return bool
     */
    protected function checkForSecurityUpdates(string $currentVersion, string $latestVersion): bool
    {
        try {
            // Get release information from GitHub
            $response = Http::timeout(10)->get($this->githubApiUrl . '/releases');

            if ($response->successful()) {
                $releases = $response->json();

                foreach ($releases as $release) {
                    $releaseVersion = ltrim($release['tag_name'] ?? '', 'v');

                    // Check if this release is newer than current but not newer than latest
                    if (
                        version_compare($currentVersion, $releaseVersion, '<') &&
                        version_compare($releaseVersion, $latestVersion, '<=')
                    ) {

                        $releaseNotes = strtolower($release['body'] ?? '');

                        // Check for security-related keywords
                        $securityKeywords = [
                            'security',
                            'vulnerability',
                            'cve',
                            'exploit',
                            'xss',
                            'sql injection',
                            'csrf',
                            'authentication',
                            'authorization',
                            'privilege escalation',
                            'rce'
                        ];

                        foreach ($securityKeywords as $keyword) {
                            if (str_contains($releaseNotes, $keyword)) {
                                return true;
                            }
                        }
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to check for security updates', [
                'error' => $e->getMessage(),
                'package' => $this->packageName,
                'note' => 'This is expected if the GitHub repository does not exist yet',
            ]);

            return false;
        }
    }

    /**
     * Get update priority level.
     *
     * @param bool $isSecurityUpdate
     * @param string $currentVersion
     * @param string $latestVersion
     * @return string
     */
    protected function getUpdatePriority(bool $isSecurityUpdate, string $currentVersion, string $latestVersion): string
    {
        if ($isSecurityUpdate) {
            return 'critical';
        }

        $currentParts = explode('.', $currentVersion);
        $latestParts = explode('.', $latestVersion);

        // Major version difference
        if (($latestParts[0] ?? 0) > ($currentParts[0] ?? 0)) {
            return 'high';
        }

        // Minor version difference
        if (($latestParts[1] ?? 0) > ($currentParts[1] ?? 0)) {
            return 'medium';
        }

        // Patch version difference
        return 'low';
    }

    /**
     * Get release notes for a specific version.
     *
     * @param string $version
     * @return array
     */
    protected function getReleaseNotes(string $version): array
    {
        try {
            $response = Http::timeout(10)->get($this->githubApiUrl . '/releases/tags/v' . $version);

            if ($response->successful()) {
                $release = $response->json();

                return [
                    'version' => $version,
                    'name' => $release['name'] ?? "Release {$version}",
                    'body' => $release['body'] ?? '',
                    'html_url' => $release['html_url'] ?? '',
                    'published_at' => $release['published_at'] ?? null,
                    'prerelease' => $release['prerelease'] ?? false,
                ];
            }

            return [];
        } catch (\Exception $e) {
            Log::warning('Failed to fetch release notes', [
                'error' => $e->getMessage(),
                'version' => $version,
                'package' => $this->packageName,
            ]);

            return [];
        }
    }

    /**
     * Send update notification.
     *
     * @param array $updateInfo
     * @return bool
     */
    public function sendUpdateNotification(array $updateInfo): bool
    {
        try {
            $notificationSettings = Config::get('ogini.update_notifications', []);

            if (!($notificationSettings['enabled'] ?? true)) {
                return false;
            }

            // Log the notification
            $this->logUpdateNotification($updateInfo);

            // Send email notification if configured
            if ($notificationSettings['email']['enabled'] ?? false) {
                $this->sendEmailNotification($updateInfo);
            }

            // Send Slack notification if configured
            if ($notificationSettings['slack']['enabled'] ?? false) {
                $this->sendSlackNotification($updateInfo);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send update notification', [
                'error' => $e->getMessage(),
                'update_info' => $updateInfo,
            ]);

            return false;
        }
    }

    /**
     * Log update notification.
     *
     * @param array $updateInfo
     * @return void
     */
    protected function logUpdateNotification(array $updateInfo): void
    {
        $level = $updateInfo['is_security_update'] ? 'warning' : 'info';
        $message = sprintf(
            'Ogini Scout Driver update available: %s -> %s (Priority: %s)',
            $updateInfo['current_version'],
            $updateInfo['latest_version'],
            $updateInfo['update_priority']
        );

        Log::log($level, $message, [
            'package' => $this->packageName,
            'update_info' => $updateInfo,
        ]);
    }

    /**
     * Send email notification.
     *
     * @param array $updateInfo
     * @return void
     */
    protected function sendEmailNotification(array $updateInfo): void
    {
        // Implementation would depend on your mail setup
        // This is a placeholder for email notification logic
        Log::info('Email notification would be sent', ['update_info' => $updateInfo]);
    }

    /**
     * Send Slack notification.
     *
     * @param array $updateInfo
     * @return void
     */
    protected function sendSlackNotification(array $updateInfo): void
    {
        $webhookUrl = Config::get('ogini.update_notifications.slack.webhook_url');

        if (!$webhookUrl) {
            return;
        }

        try {
            $color = $updateInfo['is_security_update'] ? 'danger' : 'good';
            $priority = $updateInfo['update_priority'];

            $payload = [
                'text' => 'Ogini Scout Driver Update Available',
                'attachments' => [
                    [
                        'color' => $color,
                        'title' => "Update from {$updateInfo['current_version']} to {$updateInfo['latest_version']}",
                        'fields' => [
                            [
                                'title' => 'Priority',
                                'value' => ucfirst($priority),
                                'short' => true,
                            ],
                            [
                                'title' => 'Security Update',
                                'value' => $updateInfo['is_security_update'] ? 'Yes' : 'No',
                                'short' => true,
                            ],
                        ],
                        'footer' => 'Ogini Scout Driver',
                        'ts' => time(),
                    ],
                ],
            ];

            Http::timeout(10)->post($webhookUrl, $payload);
        } catch (\Exception $e) {
            Log::warning('Failed to send Slack notification', [
                'error' => $e->getMessage(),
                'webhook_url' => $webhookUrl,
            ]);
        }
    }

    /**
     * Get update history.
     *
     * @return array
     */
    public function getUpdateHistory(): array
    {
        return Cache::get('ogini_driver_update_history', []);
    }

    /**
     * Record update check in history.
     *
     * @param array $updateInfo
     * @return void
     */
    public function recordUpdateCheck(array $updateInfo): void
    {
        $history = $this->getUpdateHistory();

        $history[] = [
            'checked_at' => now()->toISOString(),
            'current_version' => $updateInfo['current_version'],
            'latest_version' => $updateInfo['latest_version'] ?? null,
            'has_update' => $updateInfo['has_update'] ?? false,
            'is_security_update' => $updateInfo['is_security_update'] ?? false,
            'success' => $updateInfo['success'],
        ];

        // Keep only last 50 entries
        $history = array_slice($history, -50);

        Cache::put('ogini_driver_update_history', $history, 86400 * 30); // 30 days
    }

    /**
     * Clear update cache.
     *
     * @return void
     */
    public function clearUpdateCache(): void
    {
        Cache::forget('ogini_driver_update_check');
        Cache::forget('ogini_driver_update_history');
    }

    /**
     * Check if automatic updates are enabled.
     *
     * @return bool
     */
    public function isAutoUpdateEnabled(): bool
    {
        return Config::get('ogini.auto_updates.enabled', false);
    }

    /**
     * Get update command for the current environment.
     *
     * @return string
     */
    public function getUpdateCommand(): string
    {
        return 'composer update ogini-search/laravel-scout-driver';
    }
}
