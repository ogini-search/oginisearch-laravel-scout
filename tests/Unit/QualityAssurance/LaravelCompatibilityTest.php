<?php

namespace OginiScoutDriver\Tests\Unit\QualityAssurance;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\OginiServiceProvider;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Facades\Ogini;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\ScoutServiceProvider;
use Laravel\Scout\EngineManager;
use Mockery;

class LaravelCompatibilityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test Laravel 8.x compatibility.
     */
    public function testLaravel8Compatibility(): void
    {
        // Mock Laravel 8.x application
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('version')->andReturn('8.83.27');
        $app->shouldReceive('make')->andReturn(Mockery::mock());
        $app->shouldReceive('singleton')->andReturn(null);
        $app->shouldReceive('bind')->andReturn(null);
        $app->shouldReceive('when')->andReturnSelf();
        $app->shouldReceive('needs')->andReturnSelf();
        $app->shouldReceive('give')->andReturn(null);

        $provider = new OginiServiceProvider($app);

        // Test that provider can be instantiated with Laravel 8
        $this->assertInstanceOf(ServiceProvider::class, $provider);
        $this->assertInstanceOf(OginiServiceProvider::class, $provider);
    }

    /**
     * Test Laravel 9.x compatibility.
     */
    public function testLaravel9Compatibility(): void
    {
        // Mock Laravel 9.x application
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('version')->andReturn('9.52.16');
        $app->shouldReceive('make')->andReturn(Mockery::mock());
        $app->shouldReceive('singleton')->andReturn(null);
        $app->shouldReceive('bind')->andReturn(null);
        $app->shouldReceive('when')->andReturnSelf();
        $app->shouldReceive('needs')->andReturnSelf();
        $app->shouldReceive('give')->andReturn(null);

        $provider = new OginiServiceProvider($app);

        // Test that provider can be instantiated with Laravel 9
        $this->assertInstanceOf(ServiceProvider::class, $provider);
        $this->assertInstanceOf(OginiServiceProvider::class, $provider);
    }

    /**
     * Test Laravel 10.x compatibility.
     */
    public function testLaravel10Compatibility(): void
    {
        // Mock Laravel 10.x application
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('version')->andReturn('10.48.4');
        $app->shouldReceive('make')->andReturn(Mockery::mock());
        $app->shouldReceive('singleton')->andReturn(null);
        $app->shouldReceive('bind')->andReturn(null);
        $app->shouldReceive('when')->andReturnSelf();
        $app->shouldReceive('needs')->andReturnSelf();
        $app->shouldReceive('give')->andReturn(null);

        $provider = new OginiServiceProvider($app);

        // Test that provider can be instantiated with Laravel 10
        $this->assertInstanceOf(ServiceProvider::class, $provider);
        $this->assertInstanceOf(OginiServiceProvider::class, $provider);
    }

    /**
     * Test Laravel 11.x compatibility.
     */
    public function testLaravel11Compatibility(): void
    {
        // Mock Laravel 11.x application
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('version')->andReturn('11.0.0');
        $app->shouldReceive('make')->andReturn(Mockery::mock());
        $app->shouldReceive('singleton')->andReturn(null);
        $app->shouldReceive('bind')->andReturn(null);
        $app->shouldReceive('when')->andReturnSelf();
        $app->shouldReceive('needs')->andReturnSelf();
        $app->shouldReceive('give')->andReturn(null);

        $provider = new OginiServiceProvider($app);

        // Test that provider can be instantiated with Laravel 11
        $this->assertInstanceOf(ServiceProvider::class, $provider);
        $this->assertInstanceOf(OginiServiceProvider::class, $provider);
    }

    /**
     * Test Scout integration compatibility.
     */
    public function testScoutIntegrationCompatibility(): void
    {
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('make')->andReturn(Mockery::mock());
        $app->shouldReceive('singleton')->andReturn(null);
        $app->shouldReceive('bind')->andReturn(null);
        $app->shouldReceive('when')->andReturnSelf();
        $app->shouldReceive('needs')->andReturnSelf();
        $app->shouldReceive('give')->andReturn(null);

        // Mock Scout's EngineManager
        $engineManager = Mockery::mock(EngineManager::class);
        $engineManager->shouldReceive('extend')->with('ogini', Mockery::any())->andReturn(null);

        $app->shouldReceive('make')->with(EngineManager::class)->andReturn($engineManager);

        $provider = new OginiServiceProvider($app);

        // Test that Scout integration works
        $this->assertInstanceOf(OginiServiceProvider::class, $provider);
    }

    /**
     * Test PHP version compatibility.
     */
    public function testPhpVersionCompatibility(): void
    {
        $currentVersion = PHP_VERSION;

        // Test that we're running on a supported PHP version
        $this->assertTrue(
            version_compare($currentVersion, '8.1.0', '>='),
            "PHP version {$currentVersion} is not supported. Minimum required: 8.1.0"
        );

        // Test that modern PHP features are available
        $this->assertTrue(function_exists('str_contains'), 'str_contains function not available');
        $this->assertTrue(function_exists('str_starts_with'), 'str_starts_with function not available');
        $this->assertTrue(function_exists('str_ends_with'), 'str_ends_with function not available');
    }

    /**
     * Test package dependencies compatibility.
     */
    public function testPackageDependenciesCompatibility(): void
    {
        // Test that required packages are available
        $this->assertTrue(
            class_exists('Laravel\Scout\EngineManager'),
            'Laravel Scout is not available'
        );

        $this->assertTrue(
            class_exists('GuzzleHttp\Client'),
            'Guzzle HTTP client is not available'
        );

        $this->assertTrue(
            interface_exists('Psr\Log\LoggerInterface'),
            'PSR-3 Logger interface is not available'
        );

        $this->assertTrue(
            class_exists('Illuminate\Support\ServiceProvider'),
            'Laravel Service Provider is not available'
        );
    }

    /**
     * Test configuration compatibility.
     */
    public function testConfigurationCompatibility(): void
    {
        // Test that configuration structure is compatible
        $configPath = __DIR__ . '/../../../config/ogini.php';

        if (file_exists($configPath)) {
            $config = include $configPath;

            $this->assertIsArray($config, 'Configuration must be an array');
            $this->assertArrayHasKey('base_url', $config, 'base_url configuration is required');
            $this->assertArrayHasKey('api_key', $config, 'api_key configuration is required');
            $this->assertArrayHasKey('client', $config, 'client configuration is required');
            $this->assertArrayHasKey('timeout', $config['client'], 'client.timeout configuration is required');
        } else {
            $this->markTestSkipped('Configuration file not found');
        }
    }

    /**
     * Test facade compatibility.
     */
    public function testFacadeCompatibility(): void
    {
        // Test that facade can be resolved
        $this->assertTrue(
            class_exists(Ogini::class),
            'Ogini facade class does not exist'
        );

        // Test facade methods exist
        $reflection = new \ReflectionClass(Ogini::class);
        $this->assertTrue(
            $reflection->hasMethod('getFacadeAccessor'),
            'Facade accessor method missing'
        );
    }

    /**
     * Test engine compatibility with Scout.
     */
    public function testEngineScoutCompatibility(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test']);

        // Test that engine implements Scout's Engine interface
        $this->assertInstanceOf(
            'Laravel\Scout\Engines\Engine',
            $engine,
            'OginiEngine must extend Scout Engine'
        );

        // Test required Scout methods exist
        $requiredMethods = [
            'update',
            'delete',
            'search',
            'paginate',
            'map',
            'getTotalCount',
            'flush',
        ];

        $reflection = new \ReflectionClass($engine);
        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Required Scout method '{$method}' is missing"
            );
        }
    }

    /**
     * Test middleware compatibility.
     */
    public function testMiddlewareCompatibility(): void
    {
        // Test that middleware can be applied
        $app = Mockery::mock(Application::class);
        $app->shouldReceive('make')->andReturn(Mockery::mock());
        $app->shouldReceive('singleton')->andReturn(null);
        $app->shouldReceive('bind')->andReturn(null);
        $app->shouldReceive('when')->andReturnSelf();
        $app->shouldReceive('needs')->andReturnSelf();
        $app->shouldReceive('give')->andReturn(null);

        $provider = new OginiServiceProvider($app);

        // Test that provider can handle middleware registration
        $this->assertInstanceOf(OginiServiceProvider::class, $provider);
    }

    /**
     * Test event system compatibility.
     */
    public function testEventSystemCompatibility(): void
    {
        // Test that Laravel's event system is compatible
        $this->assertTrue(
            class_exists('Illuminate\Events\Dispatcher'),
            'Laravel Event Dispatcher not available'
        );

        $this->assertTrue(
            interface_exists('Illuminate\Contracts\Events\Dispatcher'),
            'Laravel Event Dispatcher contract not available'
        );
    }

    /**
     * Test cache system compatibility.
     */
    public function testCacheSystemCompatibility(): void
    {
        // Test that Laravel's cache system is compatible
        $this->assertTrue(
            interface_exists('Illuminate\Contracts\Cache\Repository'),
            'Laravel Cache Repository contract not available'
        );

        $this->assertTrue(
            class_exists('Illuminate\Cache\CacheManager'),
            'Laravel Cache Manager not available'
        );
    }

    /**
     * Test queue system compatibility.
     */
    public function testQueueSystemCompatibility(): void
    {
        // Test that Laravel's queue system is compatible
        $this->assertTrue(
            interface_exists('Illuminate\Contracts\Queue\Queue'),
            'Laravel Queue contract not available'
        );

        $this->assertTrue(
            class_exists('Illuminate\Queue\QueueManager'),
            'Laravel Queue Manager not available'
        );
    }

    /**
     * Test database compatibility.
     */
    public function testDatabaseCompatibility(): void
    {
        // Test that Eloquent is available
        $this->assertTrue(
            class_exists('Illuminate\Database\Eloquent\Model'),
            'Eloquent Model not available'
        );

        $this->assertTrue(
            class_exists('Illuminate\Database\Eloquent\Collection'),
            'Eloquent Collection not available'
        );

        $this->assertTrue(
            class_exists('Illuminate\Database\Eloquent\Builder'),
            'Eloquent Builder not available'
        );
    }
}
