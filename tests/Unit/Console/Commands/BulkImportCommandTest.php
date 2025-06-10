<?php

namespace OginiScoutDriver\Tests\Unit\Console\Commands;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Console\Commands\BulkImportCommand;
use OginiScoutDriver\Services\ModelDiscoveryService;
use OginiScoutDriver\Jobs\BulkScoutImportJob;
use Illuminate\Console\Application;
use Illuminate\Foundation\Application as Laravel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Illuminate\Console\OutputStyle;
use Mockery;

/**
 * Tests for BulkImportCommand functionality.
 * 
 * @group integration-tests
 * @group error-conditions
 */
class BulkImportCommandTest extends TestCase
{
    protected BulkImportCommand $command;
    protected $mockDiscoveryService;
    protected $output;
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock discovery service with fixed search paths to avoid app_path() issues
        $this->mockDiscoveryService = Mockery::mock(ModelDiscoveryService::class);
        $this->command = new BulkImportCommand();
        $bufferedOutput = new BufferedOutput();
        $this->output = new OutputStyle(new ArrayInput([]), $bufferedOutput);

        // Mock the app container properly
        $this->app = Mockery::mock(Laravel::class);
        $this->app->shouldReceive('make')
            ->with(ModelDiscoveryService::class)
            ->andReturn($this->mockDiscoveryService);
        $this->app->shouldReceive('path')
            ->andReturn(__DIR__ . '/../../..');
        $this->app->shouldReceive('basePath')
            ->andReturn(__DIR__ . '/../../..');

        // Set up facades for testing
        Http::setFacadeApplication($this->app);
        Queue::setFacadeApplication($this->app);

        // Set up the command
        $this->command->setLaravel($this->app);
        $this->command->setOutput($this->output);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testCommandListsAvailableModelsWhenListOptionUsed(): void
    {
        $this->mockDiscoveryService->shouldReceive('getModelDetails')
            ->once()
            ->andReturn([
                [
                    'class' => 'TestListUserModel',
                    'short_name' => 'TestUserModel',
                    'index_name' => 'users',
                    'table' => 'users',
                    'searchable_fields' => ['id', 'name', 'email']
                ]
            ]);

        $input = new ArrayInput(['--list' => true]);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(0, $exitCode);
    }

    public function testCommandShowsAvailableModelsWhenNoModelProvided(): void
    {
        $this->mockDiscoveryService->shouldReceive('getSearchableModelsMap')
            ->once()
            ->andReturn([
                'TestUserModel' => 'TestMapUserModel'
            ]);

        $input = new ArrayInput([]);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(0, $exitCode);
    }

    public function testCommandValidatesModelWhenValidateOptionUsed(): void
    {
        $this->mockDiscoveryService->shouldReceive('resolveModelClass')
            ->with('TestUserModel')
            ->once()
            ->andReturn('TestValidateUserModel');

        $this->mockDiscoveryService->shouldReceive('validateModel')
            ->with('TestValidateUserModel')
            ->once()
            ->andReturn([
                'valid' => true,
                'errors' => [],
                'warnings' => [],
                'info' => ['Index name: users']
            ]);

        // Mock the model count with unique name
        $mockModel = Mockery::mock('alias:TestValidateUserModel');
        $mockModel->shouldReceive('count')->andReturn(100);

        $input = new ArrayInput(['model' => 'TestUserModel', '--validate' => true]);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(0, $exitCode);
    }

    public function testCommandFailsWhenModelNotFound(): void
    {
        $this->mockDiscoveryService->shouldReceive('resolveModelClass')
            ->with('NonExistentModel')
            ->once()
            ->andReturn(null);

        $input = new ArrayInput(['model' => 'NonExistentModel']);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(1, $exitCode);
    }

    public function testCommandFailsWhenNoSearchableModelsFound(): void
    {
        $this->mockDiscoveryService->shouldReceive('getSearchableModelsMap')
            ->once()
            ->andReturn([]);

        $input = new ArrayInput([]);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(1, $exitCode);
    }

    public function testCommandValidationFailsForInvalidModel(): void
    {
        $this->mockDiscoveryService->shouldReceive('resolveModelClass')
            ->with('InvalidModel')
            ->once()
            ->andReturn('InvalidModel');

        $this->mockDiscoveryService->shouldReceive('validateModel')
            ->with('InvalidModel')
            ->once()
            ->andReturn([
                'valid' => false,
                'errors' => ['Model does not use Searchable trait'],
                'warnings' => [],
                'info' => []
            ]);

        $input = new ArrayInput(['model' => 'InvalidModel', '--validate' => true]);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(1, $exitCode);
    }

    public function testCommandCanHandleQueueOption(): void
    {
        $this->mockDiscoveryService->shouldReceive('resolveModelClass')
            ->with('TestUserModel')
            ->once()
            ->andReturn('TestQueueUserModel');

        // Mock HTTP connection test
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200)
        ]);

        // Mock the model query
        $mockModel = Mockery::mock('alias:TestQueueUserModel');
        $mockModel->shouldReceive('count')->andReturn(10);
        $mockModel->shouldReceive('query')->andReturnSelf();
        $mockModel->shouldReceive('chunk')->andReturnUsing(function ($size, $callback) {
            // Simulate chunked processing
            $mockCollection = Mockery::mock(\Illuminate\Database\Eloquent\Collection::class);
            $mockCollection->shouldReceive('pluck')->with('id')->andReturnSelf();
            $mockCollection->shouldReceive('toArray')->andReturn([1, 2, 3]);
            $callback($mockCollection);
            return true;
        });

        // Mock queue
        Queue::fake();

        $input = new ArrayInput([
            'model' => 'TestUserModel',
            '--queue' => true,
            '--limit' => 10
        ]);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(0, $exitCode);
        // Assert job was dispatched
        Queue::assertPushed(BulkScoutImportJob::class);
    }

    public function testCommandHandlesDryRunOption(): void
    {
        $this->mockDiscoveryService->shouldReceive('resolveModelClass')
            ->with('TestUserModel')
            ->once()
            ->andReturn('TestDryRunUserModel');

        // Mock the model count with unique name
        $mockModel = Mockery::mock('alias:TestDryRunUserModel');
        $mockModel->shouldReceive('count')->andReturn(100);

        $input = new ArrayInput([
            'model' => 'TestUserModel',
            '--dry-run' => true,
            '--limit' => 50
        ]);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(0, $exitCode);
    }

    public function testCommandFailsWhenConnectionTestFails(): void
    {
        $this->mockDiscoveryService->shouldReceive('resolveModelClass')
            ->with('TestUserModel')
            ->once()
            ->andReturn('TestFailUserModel');

        // Mock HTTP connection test failure
        Http::fake([
            '*' => Http::response([], 500)
        ]);

        $input = new ArrayInput(['model' => 'TestUserModel']);
        $this->command->setInput($input);

        $exitCode = $this->command->handle();

        $this->assertEquals(1, $exitCode);
    }

    public function testCommandCanBeConstructed(): void
    {
        $command = new BulkImportCommand();
        $this->assertInstanceOf(BulkImportCommand::class, $command);
    }

    public function testCommandHasCorrectSignature(): void
    {
        $command = new BulkImportCommand();
        $this->assertEquals('ogini:bulk-import', $command->getName());
    }

    public function testCommandHasCorrectArguments(): void
    {
        $command = new BulkImportCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('model'));
    }

    public function testCommandHasCorrectOptions(): void
    {
        $command = new BulkImportCommand();
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('list'));
        $this->assertTrue($definition->hasOption('validate'));
        $this->assertTrue($definition->hasOption('limit'));
        $this->assertTrue($definition->hasOption('batch-size'));
        $this->assertTrue($definition->hasOption('chunk-size'));
        $this->assertTrue($definition->hasOption('queue'));
        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('force'));
    }

    public function testModelDiscoveryServiceCanBeInstantiated(): void
    {
        // Test that the ModelDiscoveryService works in isolation
        $service = new ModelDiscoveryService([__DIR__ . '/../../../'], false);
        $this->assertInstanceOf(ModelDiscoveryService::class, $service);
    }

    public function testModelDiscoveryServiceResolveMethod(): void
    {
        $service = new ModelDiscoveryService([__DIR__ . '/../../../'], false);

        // Test that it can handle non-existent models
        $result = $service->resolveModelClass('NonExistentModel');
        $this->assertNull($result);
    }

    public function testModelDiscoveryServiceValidateMethod(): void
    {
        $service = new ModelDiscoveryService([__DIR__ . '/../../../'], false);

        // Test validation of non-existent class
        $result = $service->validateModel('NonExistentClass');
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testModelDiscoveryServiceMapMethod(): void
    {
        $service = new ModelDiscoveryService([__DIR__ . '/../../../'], false);

        // Test that the map method returns an array
        $result = $service->getSearchableModelsMap();
        $this->assertIsArray($result);
    }

    public function testModelDiscoveryServiceDetailsMethod(): void
    {
        $service = new ModelDiscoveryService([__DIR__ . '/../../../'], false);

        // Test that the details method returns an array
        $result = $service->getModelDetails();
        $this->assertIsArray($result);
    }
}

// Simple test model for testing purposes
class TestCommandModel extends Model
{
    use Searchable;

    protected $table = 'test_command_models';

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => 'Test Model',
        ];
    }

    public function searchableAs(): string
    {
        return 'test_command_models';
    }
}
