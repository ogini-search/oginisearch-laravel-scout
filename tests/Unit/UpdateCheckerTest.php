<?php

namespace OginiScoutDriver\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use OginiScoutDriver\Services\UpdateChecker;
use Orchestra\Testbench\TestCase;

class UpdateCheckerTest extends TestCase
{
    protected UpdateChecker $updateChecker;
    protected MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->updateChecker = new UpdateChecker();
        $reflection = new \ReflectionClass($this->updateChecker);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->updateChecker, $httpClient);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_gets_current_version(): void
    {
        $version = $this->updateChecker->getCurrentVersion();

        $this->assertIsString($version);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $version);
    }

    public function test_fetches_latest_version_from_packagist(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.0.0' => [],
                    'v1.1.0' => [],
                    'v2.0.0-beta' => [],
                    'v1.2.0' => [],
                ]
            ]
        ])));

        $latestVersion = $this->updateChecker->getLatestVersion();

        $this->assertEquals('1.2.0', $latestVersion);
    }

    public function test_fetches_latest_version_from_github_when_packagist_fails(): void
    {
        // First request (Packagist) fails
        $this->mockHandler->append(new Response(500));

        // Second request (GitHub) succeeds
        $this->mockHandler->append(new Response(200, [], json_encode([
            'tag_name' => 'v1.3.0'
        ])));

        $latestVersion = $this->updateChecker->getLatestVersion();

        $this->assertEquals('1.3.0', $latestVersion);
    }

    public function test_detects_update_available(): void
    {
        // Mock current version as 1.0.0
        $reflection = new \ReflectionClass($this->updateChecker);
        $currentVersionProperty = $reflection->getProperty('currentVersion');
        $currentVersionProperty->setAccessible(true);
        $currentVersionProperty->setValue($this->updateChecker, '1.0.0');

        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.0.0' => [],
                    'v1.1.0' => [],
                ]
            ]
        ])));

        $hasUpdate = $this->updateChecker->hasUpdate();

        $this->assertTrue($hasUpdate);
    }

    public function test_detects_no_update_when_latest(): void
    {
        // Mock current version as 1.1.0
        $reflection = new \ReflectionClass($this->updateChecker);
        $currentVersionProperty = $reflection->getProperty('currentVersion');
        $currentVersionProperty->setAccessible(true);
        $currentVersionProperty->setValue($this->updateChecker, '1.1.0');

        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.0.0' => [],
                    'v1.1.0' => [],
                ]
            ]
        ])));

        $hasUpdate = $this->updateChecker->hasUpdate();

        $this->assertFalse($hasUpdate);
    }

    public function test_gets_update_info_with_no_update(): void
    {
        // Mock current version as latest
        $reflection = new \ReflectionClass($this->updateChecker);
        $currentVersionProperty = $reflection->getProperty('currentVersion');
        $currentVersionProperty->setAccessible(true);
        $currentVersionProperty->setValue($this->updateChecker, '1.1.0');

        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.1.0' => [],
                ]
            ]
        ])));

        $updateInfo = $this->updateChecker->getUpdateInfo();

        $this->assertFalse($updateInfo['has_update']);
        $this->assertEquals('1.1.0', $updateInfo['current_version']);
        $this->assertEquals('1.1.0', $updateInfo['latest_version']);
    }

    public function test_gets_update_info_with_update_available(): void
    {
        // Mock current version as older
        $reflection = new \ReflectionClass($this->updateChecker);
        $currentVersionProperty = $reflection->getProperty('currentVersion');
        $currentVersionProperty->setAccessible(true);
        $currentVersionProperty->setValue($this->updateChecker, '1.0.0');

        // Mock Packagist response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.0.0' => [],
                    'v1.1.0' => [],
                ]
            ]
        ])));

        // Mock GitHub release notes response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'name' => 'Release 1.1.0',
            'body' => '## Changes\n- New features\n- Bug fixes',
            'published_at' => '2024-01-01T00:00:00Z',
            'html_url' => 'https://github.com/ogini-search/oginisearch-laravel-scout/releases/tag/v1.1.0',
            'prerelease' => false,
        ])));

        $updateInfo = $this->updateChecker->getUpdateInfo();

        $this->assertTrue($updateInfo['has_update']);
        $this->assertEquals('1.0.0', $updateInfo['current_version']);
        $this->assertEquals('1.1.0', $updateInfo['latest_version']);
        $this->assertEquals('composer update ogini/oginisearch-laravel-scout', $updateInfo['update_command']);
        $this->assertFalse($updateInfo['security_update']);
        $this->assertFalse($updateInfo['breaking_changes']);
        $this->assertArrayHasKey('release_notes', $updateInfo);
    }

    public function test_detects_security_update(): void
    {
        // Mock current version as older
        $reflection = new \ReflectionClass($this->updateChecker);
        $currentVersionProperty = $reflection->getProperty('currentVersion');
        $currentVersionProperty->setAccessible(true);
        $currentVersionProperty->setValue($this->updateChecker, '1.0.0');

        // Mock Packagist response
        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.0.0' => [],
                    'v1.0.1' => [],
                ]
            ]
        ])));

        // Mock GitHub release notes with security content
        $this->mockHandler->append(new Response(200, [], json_encode([
            'name' => 'Security Release 1.0.1',
            'body' => 'This release fixes a critical security vulnerability CVE-2024-0001',
            'published_at' => '2024-01-01T00:00:00Z',
            'html_url' => 'https://github.com/ogini-search/oginisearch-laravel-scout/releases/tag/v1.0.1',
            'prerelease' => false,
        ])));

        $updateInfo = $this->updateChecker->getUpdateInfo();

        $this->assertTrue($updateInfo['security_update']);
    }

    public function test_detects_breaking_changes(): void
    {
        // Mock current version as 1.x
        $reflection = new \ReflectionClass($this->updateChecker);
        $currentVersionProperty = $reflection->getProperty('currentVersion');
        $currentVersionProperty->setAccessible(true);
        $currentVersionProperty->setValue($this->updateChecker, '1.0.0');

        // Mock Packagist response with major version update
        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.0.0' => [],
                    'v2.0.0' => [],
                ]
            ]
        ])));

        // Mock GitHub release notes
        $this->mockHandler->append(new Response(200, [], json_encode([
            'name' => 'Release 2.0.0',
            'body' => 'Major version with breaking changes',
            'published_at' => '2024-01-01T00:00:00Z',
            'html_url' => 'https://github.com/ogini-search/oginisearch-laravel-scout/releases/tag/v2.0.0',
            'prerelease' => false,
        ])));

        $updateInfo = $this->updateChecker->getUpdateInfo();

        $this->assertTrue($updateInfo['breaking_changes']);
    }

    public function test_caches_version_information(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.1.0' => [],
                ]
            ]
        ])));

        // First call
        $version1 = $this->updateChecker->getLatestVersion();

        // Second call should use cache (no HTTP request)
        $version2 = $this->updateChecker->getLatestVersion();

        $this->assertEquals($version1, $version2);
        $this->assertEquals('1.1.0', $version1);
    }

    public function test_clears_cache(): void
    {
        // Set cache
        Cache::put('ogini_scout_latest_version', '1.0.0', 3600);
        Cache::put('ogini_scout_release_notes_1.0.0', ['test' => 'data'], 3600);

        $this->assertEquals('1.0.0', Cache::get('ogini_scout_latest_version'));
        $this->assertNotNull(Cache::get('ogini_scout_release_notes_1.0.0'));

        $this->updateChecker->clearCache();

        $this->assertNull(Cache::get('ogini_scout_latest_version'));
        $this->assertNull(Cache::get('ogini_scout_release_notes_1.0.0'));
    }

    public function test_handles_network_errors_gracefully(): void
    {
        // Both Packagist and GitHub fail
        $this->mockHandler->append(new Response(500));
        $this->mockHandler->append(new Response(500));

        $latestVersion = $this->updateChecker->getLatestVersion();

        $this->assertNull($latestVersion);
    }

    public function test_filters_out_pre_release_versions(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'package' => [
                'versions' => [
                    'v1.0.0' => [],
                    'v1.1.0-alpha' => [],
                    'v1.1.0-beta' => [],
                    'v1.2.0-dev' => [],
                    'v1.1.0' => [],
                ]
            ]
        ])));

        $latestVersion = $this->updateChecker->getLatestVersion();

        $this->assertEquals('1.1.0', $latestVersion);
    }

    public function test_gets_release_notes(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([
            'name' => 'Release 1.1.0',
            'body' => 'Release notes content',
            'published_at' => '2024-01-01T00:00:00Z',
            'html_url' => 'https://github.com/ogini-search/oginisearch-laravel-scout/releases/tag/v1.1.0',
            'prerelease' => false,
        ])));

        $releaseNotes = $this->updateChecker->getReleaseNotes('1.1.0');

        $this->assertEquals('1.1.0', $releaseNotes['version']);
        $this->assertEquals('Release 1.1.0', $releaseNotes['name']);
        $this->assertEquals('Release notes content', $releaseNotes['body']);
        $this->assertNotNull($releaseNotes['published_at']);
        $this->assertNotNull($releaseNotes['html_url']);
        $this->assertFalse($releaseNotes['prerelease']);
    }
}
