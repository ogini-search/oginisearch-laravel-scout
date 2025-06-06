<?php

namespace OginiScoutDriver\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Helpers\ConfigHelpers;
use Illuminate\Support\Facades\Config;
use Mockery;

class ConfigHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear any existing mock expectations
        Mockery::close();

        // Reset the Config facade to ensure clean state
        Config::clearResolvedInstances();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_get_full_config(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini', [])
            ->andReturn(['base_url' => 'http://localhost:3000']);

        $result = ConfigHelpers::getConfig();

        $this->assertEquals(['base_url' => 'http://localhost:3000'], $result);
    }

    /** @test */
    public function it_can_get_specific_config_key(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.base_url', null)
            ->andReturn('http://localhost:3000');

        $result = ConfigHelpers::getConfig('base_url');

        $this->assertEquals('http://localhost:3000', $result);
    }

    /** @test */
    public function it_can_get_base_url(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.base_url', 'http://localhost:3000')
            ->andReturn('http://example.com');

        $result = ConfigHelpers::getBaseUrl();

        $this->assertEquals('http://example.com', $result);
    }

    /** @test */
    public function it_can_get_api_key(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.api_key', '')
            ->andReturn('secret-key');

        $result = ConfigHelpers::getApiKey();

        $this->assertEquals('secret-key', $result);
    }

    /** @test */
    public function it_can_check_performance_enabled(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.performance.enabled', true)
            ->andReturn(true);

        $result = ConfigHelpers::isPerformanceEnabled();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_check_specific_performance_feature(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.performance.cache.enabled', false)
            ->andReturn(true);

        $result = ConfigHelpers::isPerformanceEnabled('cache');

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_query_optimization_config(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.performance.query_optimization', [])
            ->andReturn(['enabled' => true]);

        $result = ConfigHelpers::getQueryOptimization();

        $this->assertEquals(['enabled' => true], $result);
    }

    /** @test */
    public function it_can_get_cache_config(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.performance.cache', [])
            ->andReturn(['driver' => 'redis']);

        $result = ConfigHelpers::getCacheConfig();

        $this->assertEquals(['driver' => 'redis'], $result);
    }

    /** @test */
    public function it_can_check_cache_enabled(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.performance.cache.enabled', false)
            ->andReturn(true);

        $result = ConfigHelpers::isCacheEnabled();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_cache_ttl(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('ogini.performance.cache.query_ttl', null)
            ->andReturn(600);

        $result = ConfigHelpers::getCacheTtl('query');

        $this->assertEquals(600, $result);
    }

    /** @test */
    public function it_can_get_timeouts(): void
    {
        Config::shouldReceive('get')
            ->times(3)
            ->andReturn(30, 5, 30); // timeout, connection_timeout, idle_timeout

        $result = ConfigHelpers::getTimeouts();

        $this->assertArrayHasKey('client_timeout', $result);
        $this->assertArrayHasKey('connection_timeout', $result);
        $this->assertArrayHasKey('idle_timeout', $result);
    }

    /** @test */
    public function it_can_get_retry_config(): void
    {
        Config::shouldReceive('get')
            ->times(4)
            ->andReturn(3, 100, 3, 100); // Various retry settings

        $result = ConfigHelpers::getRetryConfig();

        $this->assertArrayHasKey('client_retry_attempts', $result);
        $this->assertArrayHasKey('client_retry_delay', $result);
        $this->assertArrayHasKey('batch_retry_attempts', $result);
        $this->assertArrayHasKey('batch_retry_delay', $result);
    }

    /** @test */
    public function it_can_validate_config(): void
    {
        Config::shouldReceive('get')
            ->with('ogini.base_url', 'http://localhost:3000')
            ->andReturn('http://localhost:3000');

        Config::shouldReceive('get')
            ->with('ogini.api_key', '')
            ->andReturn('test-key');

        // For numeric field validations
        Config::shouldReceive('get')
            ->with('ogini.client.timeout', null)
            ->andReturn(30);

        Config::shouldReceive('get')
            ->with('ogini.client.retry_attempts', null)
            ->andReturn(3);

        Config::shouldReceive('get')
            ->with('ogini.performance.cache.query_ttl', null)
            ->andReturn(300);

        Config::shouldReceive('get')
            ->with('ogini.performance.cache.result_ttl', null)
            ->andReturn(600);

        Config::shouldReceive('get')
            ->with('ogini.performance.connection_pool.pool_size', null)
            ->andReturn(10);

        $errors = ConfigHelpers::validateConfig();

        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_detects_missing_required_fields(): void
    {
        Config::shouldReceive('get')
            ->with('ogini.base_url', 'http://localhost:3000')
            ->andReturn('');

        Config::shouldReceive('get')
            ->with('ogini.api_key', '')
            ->andReturn('');

        // For numeric field validations (still need mocks even with errors)
        Config::shouldReceive('get')
            ->with('ogini.client.timeout', null)
            ->andReturn(30);

        Config::shouldReceive('get')
            ->with('ogini.client.retry_attempts', null)
            ->andReturn(3);

        Config::shouldReceive('get')
            ->with('ogini.performance.cache.query_ttl', null)
            ->andReturn(300);

        Config::shouldReceive('get')
            ->with('ogini.performance.cache.result_ttl', null)
            ->andReturn(600);

        Config::shouldReceive('get')
            ->with('ogini.performance.connection_pool.pool_size', null)
            ->andReturn(10);

        $errors = ConfigHelpers::validateConfig();

        $this->assertContains('Base URL is required', $errors);
        $this->assertContains('API key is required', $errors);
    }

    /** @test */
    public function it_detects_invalid_url(): void
    {
        Config::shouldReceive('get')
            ->with('ogini.base_url', 'http://localhost:3000')
            ->andReturn('invalid-url');

        Config::shouldReceive('get')
            ->with('ogini.api_key', '')
            ->andReturn('test-key');

        // For numeric field validations (won't be reached due to URL error, but still need mocks)
        Config::shouldReceive('get')
            ->with('ogini.client.timeout', null)
            ->andReturn(30);

        Config::shouldReceive('get')
            ->with('ogini.client.retry_attempts', null)
            ->andReturn(3);

        Config::shouldReceive('get')
            ->with('ogini.performance.cache.query_ttl', null)
            ->andReturn(300);

        Config::shouldReceive('get')
            ->with('ogini.performance.cache.result_ttl', null)
            ->andReturn(600);

        Config::shouldReceive('get')
            ->with('ogini.performance.connection_pool.pool_size', null)
            ->andReturn(10);

        $errors = ConfigHelpers::validateConfig();

        $this->assertContains('Base URL is not a valid URL', $errors);
    }

    /** @test */
    public function it_can_get_environment_overrides(): void
    {
        $overrides = ConfigHelpers::getEnvironmentOverrides('testing');

        $this->assertArrayHasKey('client.timeout', $overrides);
        $this->assertArrayHasKey('performance.cache.enabled', $overrides);
        $this->assertEquals(10, $overrides['client.timeout']);
        $this->assertFalse($overrides['performance.cache.enabled']);
    }

    /** @test */
    public function it_can_check_production_optimization(): void
    {
        // Mock for isCacheEnabled() 
        Config::shouldReceive('get')
            ->with('ogini.performance.cache.enabled', null)
            ->andReturn(true);

        // Mock for isConnectionPoolEnabled()
        Config::shouldReceive('get')
            ->with('ogini.performance.connection_pool.enabled', null)
            ->andReturn(true);

        // Mock for isBatchProcessingEnabled()
        Config::shouldReceive('get')
            ->with('ogini.performance.batch_processing.enabled', null)
            ->andReturn(true);

        // Mock for getClientConfig('retry_attempts')
        Config::shouldReceive('get')
            ->with('ogini.client.retry_attempts', null)
            ->andReturn(3);

        $result = ConfigHelpers::isOptimizedForProduction();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_detects_unoptimized_production_config(): void
    {
        // Mock for isCacheEnabled() - returns false (cache disabled)
        Config::shouldReceive('get')
            ->with('ogini.performance.cache.enabled', null)
            ->andReturn(false);

        // Mock for isConnectionPoolEnabled()
        Config::shouldReceive('get')
            ->with('ogini.performance.connection_pool.enabled', null)
            ->andReturn(true);

        // Mock for isBatchProcessingEnabled()
        Config::shouldReceive('get')
            ->with('ogini.performance.batch_processing.enabled', null)
            ->andReturn(true);

        // Mock for getClientConfig('retry_attempts')
        Config::shouldReceive('get')
            ->with('ogini.client.retry_attempts', null)
            ->andReturn(3);

        $result = ConfigHelpers::isOptimizedForProduction();

        $this->assertFalse($result);
    }
}
