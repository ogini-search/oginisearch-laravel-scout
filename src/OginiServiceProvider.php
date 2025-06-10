<?php

namespace OginiScoutDriver;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Client\AsyncOginiClient;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\Listeners\LogIndexingActivity;
use OginiScoutDriver\Listeners\LogSearchActivity;
use OginiScoutDriver\Services\UpdateChecker;
use OginiScoutDriver\Services\UpdateNotificationService;
use OginiScoutDriver\Console\CheckUpdatesCommand;
use OginiScoutDriver\Console\OginiHealthCheckCommand;
use OginiScoutDriver\Console\Commands\BulkImportCommand;
use OginiScoutDriver\Services\ModelDiscoveryService;
use Illuminate\Support\Facades\Event;

class OginiServiceProvider extends ServiceProvider
{
    /**
     * Package version.
     */
    const VERSION = '1.0.3';
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ogini.php', 'ogini');

        // Load helper functions
        require_once __DIR__ . '/helpers.php';

        $this->app->singleton(OginiClient::class, function ($app) {
            $config = $app['config']['ogini'];

            return new OginiClient(
                $config['base_url'],
                $config['api_key'],
                $config['client'] ?? []
            );
        });

        $this->app->singleton(AsyncOginiClient::class, function ($app) {
            $config = $app['config']['ogini'];

            return new AsyncOginiClient(
                $config['base_url'],
                $config['api_key'],
                $config['client'] ?? []
            );
        });

        $this->app->singleton(UpdateChecker::class);
        $this->app->singleton(UpdateNotificationService::class);
        $this->app->singleton(ModelDiscoveryService::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/ogini.php' => config_path('ogini.php'),
            ], 'ogini-config');

            $this->commands([
                CheckUpdatesCommand::class,
                OginiHealthCheckCommand::class,
                BulkImportCommand::class,
            ]);
        }

        // Register event listeners
        Event::subscribe(LogIndexingActivity::class);
        Event::subscribe(LogSearchActivity::class);

        $this->app->make(EngineManager::class)->extend('ogini', function ($app) {
            $config = $app['config']['ogini'];
            $engineConfig = array_merge(
                $config['engine'] ?? [],
                ['performance' => $config['performance'] ?? []]
            );

            // Get cache repository if caching is enabled
            $cache = null;
            if (isset($config['performance']['cache']['enabled']) && $config['performance']['cache']['enabled']) {
                $cacheDriver = $config['performance']['cache']['driver'] ?? 'default';
                $cache = $app['cache']->store($cacheDriver);
            }

            return new OginiEngine(
                $app->make(OginiClient::class),
                $engineConfig,
                $cache
            );
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            OginiClient::class,
            AsyncOginiClient::class,
            UpdateChecker::class,
        ];
    }
}
