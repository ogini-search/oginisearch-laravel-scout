<?php

namespace OginiScoutDriver\Tests\Unit\Performance;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Performance\BatchProcessor;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Exceptions\OginiException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class TestModel extends Model
{
    protected $fillable = ['id', 'title', 'content'];

    public function getScoutKey()
    {
        return $this->id;
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
        ];
    }
}

class BatchProcessorTest extends TestCase
{
    protected BatchProcessor $batchProcessor;
    protected $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(OginiClient::class);
        $this->batchProcessor = new BatchProcessor($this->mockClient, [
            'chunk_size' => 2,
            'max_parallel_requests' => 2,
            'enable_parallel_processing' => false, // Disable for simpler testing
            'retry_failed_chunks' => true,
            'max_retry_attempts' => 2,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_bulk_index_models_successfully(): void
    {
        $models = new Collection([
            new TestModel(['id' => 1, 'title' => 'Test 1', 'content' => 'Content 1']),
            new TestModel(['id' => 2, 'title' => 'Test 2', 'content' => 'Content 2']),
            new TestModel(['id' => 3, 'title' => 'Test 3', 'content' => 'Content 3']),
        ]);

        // Expect two bulk operations (chunk size = 2)
        $this->mockClient->shouldReceive('bulkIndexDocuments')
            ->twice()
            ->andReturn(['success' => true]);

        $result = $this->batchProcessor->bulkIndex('test_index', $models);

        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(100, $result['success_rate']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_handles_bulk_indexing_failures_with_retry(): void
    {
        $models = new Collection([
            new TestModel(['id' => 1, 'title' => 'Test 1', 'content' => 'Content 1']),
            new TestModel(['id' => 2, 'title' => 'Test 2', 'content' => 'Content 2']),
        ]);

        // First bulk operation fails
        $this->mockClient->shouldReceive('bulkIndexDocuments')
            ->once()
            ->andThrow(new OginiException('Bulk operation failed'));

        // Individual indexing succeeds
        $this->mockClient->shouldReceive('indexDocument')
            ->twice()
            ->andReturn(['success' => true]);

        $result = $this->batchProcessor->bulkIndex('test_index', $models);

        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(100, $result['success_rate']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_handles_individual_indexing_failures(): void
    {
        $models = new Collection([
            new TestModel(['id' => 1, 'title' => 'Test 1', 'content' => 'Content 1']),
            new TestModel(['id' => 2, 'title' => 'Test 2', 'content' => 'Content 2']),
        ]);

        // Bulk operation fails
        $this->mockClient->shouldReceive('bulkIndexDocuments')
            ->once()
            ->andThrow(new OginiException('Bulk operation failed'));

        // First individual indexing succeeds
        $this->mockClient->shouldReceive('indexDocument')
            ->with('test_index', Mockery::any(), 1)
            ->once()
            ->andReturn(['success' => true]);

        // Second individual indexing fails after retries
        $this->mockClient->shouldReceive('indexDocument')
            ->with('test_index', Mockery::any(), 2)
            ->times(2) // max_retry_attempts
            ->andThrow(new OginiException('Individual indexing failed'));

        $result = $this->batchProcessor->bulkIndex('test_index', $models);

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(50, $result['success_rate']);
        $this->assertCount(1, $result['errors']);
        $this->assertEquals(2, $result['errors'][0]['document_id']);
    }

    /** @test */
    public function it_can_bulk_delete_models(): void
    {
        $models = new Collection([
            new TestModel(['id' => 1, 'title' => 'Test 1']),
            new TestModel(['id' => 2, 'title' => 'Test 2']),
            new TestModel(['id' => 3, 'title' => 'Test 3']),
        ]);

        $this->mockClient->shouldReceive('deleteDocument')
            ->times(3)
            ->andReturn(['success' => true]);

        $result = $this->batchProcessor->bulkDelete('test_index', $models);

        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(100, $result['success_rate']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_handles_delete_failures(): void
    {
        $models = new Collection([
            new TestModel(['id' => 1, 'title' => 'Test 1']),
            new TestModel(['id' => 2, 'title' => 'Test 2']),
        ]);

        $this->mockClient->shouldReceive('deleteDocument')
            ->with('test_index', 1)
            ->once()
            ->andReturn(['success' => true]);

        $this->mockClient->shouldReceive('deleteDocument')
            ->with('test_index', 2)
            ->once()
            ->andThrow(new OginiException('Delete failed'));

        $result = $this->batchProcessor->bulkDelete('test_index', $models);

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(50, $result['success_rate']);
        $this->assertCount(1, $result['errors']);
    }

    /** @test */
    public function it_handles_empty_model_collections(): void
    {
        $models = new Collection([]);

        $result = $this->batchProcessor->bulkIndex('test_index', $models);

        $this->assertEquals(0, $result['processed']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_can_track_progress_with_callback(): void
    {
        $models = new Collection([
            new TestModel(['id' => 1, 'title' => 'Test 1']),
            new TestModel(['id' => 2, 'title' => 'Test 2']),
            new TestModel(['id' => 3, 'title' => 'Test 3']),
        ]);

        $this->mockClient->shouldReceive('bulkIndexDocuments')
            ->twice()
            ->andReturn(['success' => true]);

        $progressCalls = [];
        $progressCallback = function ($processed, $chunkSize, $chunkIndex, $totalChunks) use (&$progressCalls) {
            $progressCalls[] = compact('processed', 'chunkSize', 'chunkIndex', 'totalChunks');
        };

        $this->batchProcessor->bulkIndex('test_index', $models, $progressCallback);

        $this->assertCount(2, $progressCalls);
        $this->assertEquals(2, $progressCalls[0]['processed']);
        $this->assertEquals(3, $progressCalls[1]['processed']);
    }

    /** @test */
    public function it_provides_statistics(): void
    {
        $stats = $this->batchProcessor->getStatistics();

        $this->assertArrayHasKey('chunk_size', $stats);
        $this->assertArrayHasKey('max_parallel_requests', $stats);
        $this->assertArrayHasKey('parallel_processing_enabled', $stats);
        $this->assertArrayHasKey('retry_enabled', $stats);
        $this->assertArrayHasKey('max_retry_attempts', $stats);

        $this->assertEquals(2, $stats['chunk_size']);
        $this->assertEquals(2, $stats['max_parallel_requests']);
        $this->assertFalse($stats['parallel_processing_enabled']);
        $this->assertTrue($stats['retry_enabled']);
        $this->assertEquals(2, $stats['max_retry_attempts']);
    }

    /** @test */
    public function it_can_update_configuration(): void
    {
        $this->batchProcessor->updateConfig(['chunk_size' => 5]);

        $stats = $this->batchProcessor->getStatistics();
        $this->assertEquals(5, $stats['chunk_size']);
    }
}
