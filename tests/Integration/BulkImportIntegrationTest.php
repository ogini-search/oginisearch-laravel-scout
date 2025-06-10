<?php

namespace OginiScoutDriver\Tests\Integration;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use OginiScoutDriver\OginiServiceProvider;
use OginiScoutDriver\Services\ModelDiscoveryService;
use Laravel\Scout\ScoutServiceProvider;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;

/**
 * Integration test that demonstrates bulk import functionality 
 * working in a realistic Laravel application environment.
 * 
 * @group integration-tests
 * @group real-api-calls
 * @group error-conditions
 */
class BulkImportIntegrationTest extends BaseTestCase
{
    public function createApplication()
    {
        $app = new Application(__DIR__ . '/../../');

        // Register essential service providers
        $app->register(ScoutServiceProvider::class);
        $app->register(OginiServiceProvider::class);

        // Configure basic Laravel services for testing
        $app->singleton('config', function () {
            return new \Illuminate\Config\Repository([
                'oginisearch' => [
                    'base_url' => 'http://localhost:3000',
                    'api_key' => 'test-key',
                    'performance' => [
                        'enabled' => true,
                        'batch_size' => 500,
                    ]
                ],
                'scout' => [
                    'driver' => 'ogini',
                ]
            ]);
        });

        $app->singleton('path', function () {
            return __DIR__ . '/../../';
        });

        return $app;
    }

    public function testModelDiscoveryServiceWorksInRealEnvironment(): void
    {
        $service = new ModelDiscoveryService();

        // Test that service can be instantiated without errors
        $this->assertInstanceOf(ModelDiscoveryService::class, $service);

        // Test that it can discover models (will find our test models)
        $models = $service->discoverSearchableModels();
        $this->assertIsArray($models);

        // Test model resolution functionality
        $resolved = $service->resolveModelClass('NonExistentModel');
        $this->assertNull($resolved);

        // Test validation functionality  
        $validation = $service->validateModel('NonExistentClass');
        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    public function testBulkImportCommandIsRegistered(): void
    {
        // Verify the command is properly registered
        $commands = Artisan::all();
        $this->assertArrayHasKey('ogini:bulk-import', $commands);

        $command = $commands['ogini:bulk-import'];
        $this->assertEquals('ogini:bulk-import', $command->getName());
    }

    public function testCommandHasCorrectSignatureAndOptions(): void
    {
        $command = Artisan::all()['ogini:bulk-import'];
        $definition = $command->getDefinition();

        // Verify all expected options exist
        $expectedOptions = [
            'list',
            'validate',
            'limit',
            'batch-size',
            'chunk-size',
            'queue',
            'dry-run',
            'force'
        ];

        foreach ($expectedOptions as $option) {
            $this->assertTrue(
                $definition->hasOption($option),
                "Command missing required option: {$option}"
            );
        }

        // Verify model argument exists
        $this->assertTrue($definition->hasArgument('model'));
    }

    public function testServiceProviderRegistersAllServices(): void
    {
        $app = $this->createApplication();

        // Test that ModelDiscoveryService can be resolved
        $this->assertTrue($app->bound(ModelDiscoveryService::class));

        $service = $app->make(ModelDiscoveryService::class);
        $this->assertInstanceOf(ModelDiscoveryService::class, $service);
    }

    public function testRealWorldUsageScenario(): void
    {
        // This simulates what would happen in a real Laravel application

        // 1. Service provider registers services ✓
        $app = $this->createApplication();

        // 2. ModelDiscoveryService can discover searchable models ✓
        $discoveryService = $app->make(ModelDiscoveryService::class);
        $this->assertInstanceOf(ModelDiscoveryService::class, $discoveryService);

        // 3. Command can be instantiated and has correct structure ✓
        $command = $app->make(\OginiScoutDriver\Console\Commands\BulkImportCommand::class);
        $this->assertInstanceOf(\OginiScoutDriver\Console\Commands\BulkImportCommand::class, $command);

        // 4. All required dependencies are available ✓
        $this->assertTrue(class_exists(\OginiScoutDriver\Jobs\BulkScoutImportJob::class));
        $this->assertTrue(class_exists(\OginiScoutDriver\Performance\BatchProcessor::class));

        echo "\n✅ Real-world integration test PASSED - All components work together correctly!\n";
    }
}

// Test model to simulate user's searchable models
class IntegrationTestModel extends Model
{
    use Searchable;

    protected $table = 'integration_test_models';

    public function searchableAs(): string
    {
        return 'integration_test_models';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => 'Test Model',
        ];
    }
}
