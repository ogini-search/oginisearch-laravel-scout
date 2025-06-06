<?php

namespace OginiScoutDriver\Tests\Unit;

use Illuminate\Foundation\Application;
use Laravel\Scout\EngineManager;
use Laravel\Scout\ScoutServiceProvider;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\OginiServiceProvider;
use Orchestra\Testbench\TestCase;

class OginiServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ScoutServiceProvider::class,
            OginiServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // OginiSearch configuration
        $app['config']->set('ogini.base_url', 'http://localhost:3000');
        $app['config']->set('ogini.api_key', 'test-api-key');
    }

    public function testServiceProviderRegistersClient(): void
    {
        $this->assertTrue($this->app->bound(OginiClient::class));
    }

    public function testScoutDriverIsRegistered(): void
    {
        $engineManager = $this->app->make(EngineManager::class);
        $engine = $engineManager->driver('ogini');

        $this->assertInstanceOf(OginiEngine::class, $engine);
    }

    public function testConfigIsPublishable(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => OginiServiceProvider::class,
            '--tag' => 'ogini-config',
        ])->assertSuccessful();

        $this->assertFileExists(config_path('ogini.php'));
    }
}
