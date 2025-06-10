<?php

namespace OginiScoutDriver\Tests\Unit\Jobs;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Jobs\BulkScoutImportJob;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Mockery;

/**
 * Tests for BulkScoutImportJob functionality.
 * 
 * @group integration-tests
 * @group error-conditions
 */
class BulkScoutImportJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testJobCanBeConstructed(): void
    {
        $job = new BulkScoutImportJob([1, 2, 3], TestJobModel::class, 500);

        $this->assertInstanceOf(BulkScoutImportJob::class, $job);
    }

    public function testJobHandleSuccessfully(): void
    {
        $modelIds = [1, 2, 3];
        $batchSize = 500;

        // Mock the model query
        $mockCollection = Mockery::mock(Collection::class);
        $mockCollection->shouldReceive('isEmpty')->andReturn(false);
        $mockCollection->shouldReceive('count')->andReturn(3);
        $mockCollection->shouldReceive('chunk')->with($batchSize)->andReturnUsing(function ($size, $callback = null) {
            if ($callback) {
                $chunk = Mockery::mock(Collection::class);
                $chunk->shouldReceive('searchable')->once();
                $chunk->shouldReceive('count')->andReturn(3);

                $chunks = [$chunk];
                foreach ($chunks as $chunk) {
                    $callback($chunk);
                }

                return $chunks;
            }
            return [];
        });

        // Mock the static model method - use unique name
        $mockModel = Mockery::mock('alias:TestJobModelUnique1');
        $mockModel->shouldReceive('whereIn')
            ->with('id', $modelIds)
            ->andReturnSelf();
        $mockModel->shouldReceive('get')
            ->andReturn($mockCollection);

        Log::shouldReceive('info')->twice(); // Start and end log messages

        $job = new BulkScoutImportJob($modelIds, 'TestJobModelUnique1', $batchSize);
        $job->handle();

        $this->assertTrue(true); // Test passed if no exceptions thrown
    }

    public function testJobHandlesNonExistentModelClass(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(Mockery::pattern('/Model class.*does not exist/'));

        $job = new BulkScoutImportJob([1, 2, 3], 'NonExistentModel', 500);
        $job->handle();

        $this->assertTrue(true); // Test passed if no exceptions thrown
    }

    public function testJobHandlesEmptyModelCollection(): void
    {
        $modelIds = [1, 2, 3];

        // Mock empty collection
        $mockCollection = Mockery::mock(Collection::class);
        $mockCollection->shouldReceive('isEmpty')->andReturn(true);

        // Mock the static model method - use unique name
        $mockModel = Mockery::mock('alias:TestJobModelUnique2');
        $mockModel->shouldReceive('whereIn')
            ->with('id', $modelIds)
            ->andReturnSelf();
        $mockModel->shouldReceive('get')
            ->andReturn($mockCollection);

        Log::shouldReceive('warning')
            ->once()
            ->with('BulkScoutImportJob: No models found for IDs', Mockery::any());

        $job = new BulkScoutImportJob($modelIds, 'TestJobModelUnique2', 500);
        $job->handle();

        $this->assertTrue(true); // Test passed if no exceptions thrown
    }

    public function testJobHandlesSearchableException(): void
    {
        $modelIds = [1, 2, 3];
        $batchSize = 500;

        // Mock the model query with exception
        $mockCollection = Mockery::mock(Collection::class);
        $mockCollection->shouldReceive('isEmpty')->andReturn(false);
        $mockCollection->shouldReceive('count')->andReturn(3);
        $mockCollection->shouldReceive('chunk')->with($batchSize)->andReturnUsing(function ($size, $callback = null) {
            if ($callback) {
                $chunk = Mockery::mock(Collection::class);
                $chunk->shouldReceive('searchable')->andThrow(new \Exception('Searchable failed'));
                $chunk->shouldReceive('count')->andReturn(3);

                $chunks = [$chunk];
                foreach ($chunks as $chunk) {
                    try {
                        $callback($chunk);
                    } catch (\Exception $e) {
                        // Exception expected and caught
                        throw $e;
                    }
                }

                return $chunks;
            }
            return [];
        });

        // Mock the static model method - use unique name
        $mockModel = Mockery::mock('alias:TestJobModelUnique3');
        $mockModel->shouldReceive('whereIn')
            ->with('id', $modelIds)
            ->andReturnSelf();
        $mockModel->shouldReceive('get')
            ->andReturn($mockCollection);

        Log::shouldReceive('info')->once(); // Start log message
        Log::shouldReceive('error')->atLeast()->once(); // Error messages

        $job = new BulkScoutImportJob($modelIds, 'TestJobModelUnique3', $batchSize);

        // Test that job handles exception appropriately
        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Searchable failed', $e->getMessage());
        }
    }

    public function testJobFailedMethod(): void
    {
        $exception = new \Exception('Test exception');

        Log::shouldReceive('error')
            ->once()
            ->with('BulkScoutImportJob: Job failed permanently', Mockery::on(function ($context) {
                return isset($context['model']) &&
                    isset($context['model_name']) &&
                    isset($context['ids_count']) &&
                    isset($context['error']);
            }));

        $job = new BulkScoutImportJob([1, 2, 3], TestJobModel::class, 500);
        $job->failed($exception);

        $this->assertTrue(true); // Test passed if no exceptions thrown
    }

    public function testJobBackoffMethod(): void
    {
        $job = new BulkScoutImportJob([1, 2, 3], TestJobModel::class, 500);
        $backoff = $job->backoff();

        $this->assertEquals([60, 120, 300], $backoff);
    }

    public function testJobTagsMethod(): void
    {
        $job = new BulkScoutImportJob([1, 2, 3], TestJobModel::class, 500);
        $tags = $job->tags();

        $this->assertContains('ogini-bulk-import', $tags);
        $this->assertContains('model:TestJobModel', $tags);
        $this->assertContains('batch-size:500', $tags);
        $this->assertContains('records:3', $tags);
    }

    public function testJobPropertiesAreSetCorrectly(): void
    {
        $job = new BulkScoutImportJob([1, 2, 3], TestJobModel::class, 750);

        $this->assertEquals(600, $job->timeout);
        $this->assertEquals(3, $job->tries);
    }

    public function testJobHandlesMultipleBatches(): void
    {
        $modelIds = [1, 2, 3, 4, 5];
        $batchSize = 2; // Will create 3 batches

        // Mock the model query
        $mockCollection = Mockery::mock(Collection::class);
        $mockCollection->shouldReceive('isEmpty')->andReturn(false);
        $mockCollection->shouldReceive('count')->andReturn(5);
        $mockCollection->shouldReceive('chunk')->with($batchSize)->andReturnUsing(function ($size, $callback = null) {
            if ($callback) {
                // Simulate three chunks (2, 2, 1)
                $chunks = [];
                for ($i = 0; $i < 3; $i++) {
                    $chunk = Mockery::mock(Collection::class);
                    $chunk->shouldReceive('searchable')->once();
                    $chunk->shouldReceive('count')->andReturn($i === 2 ? 1 : 2);
                    $chunks[] = $chunk;
                }

                foreach ($chunks as $chunk) {
                    $callback($chunk);
                }

                return $chunks;
            }
            return [];
        });

        // Mock the static model method - use unique name
        $mockModel = Mockery::mock('alias:TestJobModelUnique4');
        $mockModel->shouldReceive('whereIn')
            ->with('id', $modelIds)
            ->andReturnSelf();
        $mockModel->shouldReceive('get')
            ->andReturn($mockCollection);

        Log::shouldReceive('info')->twice(); // Start and end log messages

        $job = new BulkScoutImportJob($modelIds, 'TestJobModelUnique4', $batchSize);
        $job->handle();

        $this->assertTrue(true); // Test passed if no exceptions thrown
    }
}

// Test model for testing purposes
class TestJobModel extends Model
{
    use Searchable;

    protected $table = 'test_job_models';

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => 'Test Model',
        ];
    }

    public function searchableAs(): string
    {
        return 'test_job_models';
    }
}
