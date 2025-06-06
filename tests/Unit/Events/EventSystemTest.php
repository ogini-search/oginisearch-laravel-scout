<?php

namespace OginiScoutDriver\Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Events\IndexingCompleted;
use OginiScoutDriver\Events\IndexingFailed;
use OginiScoutDriver\Events\SearchCompleted;
use OginiScoutDriver\Events\SearchFailed;
use OginiScoutDriver\Events\DeletionCompleted;
use OginiScoutDriver\Events\DeletionFailed;

class EventSystemTest extends TestCase
{
    /** @test */
    public function indexing_completed_event_has_correct_data(): void
    {
        $data = [
            'job_id' => 'job123',
            'index_name' => 'test_index',
            'document_id' => 'doc123',
            'is_bulk' => false,
            'result' => ['success' => true],
        ];

        $event = new IndexingCompleted($data);

        $this->assertEquals('job123', $event->getJobId());
        $this->assertEquals('test_index', $event->getIndexName());
        $this->assertEquals('doc123', $event->getDocumentId());
        $this->assertFalse($event->isBulk());
        $this->assertEquals(['success' => true], $event->getResult());
    }

    /** @test */
    public function indexing_failed_event_has_correct_data(): void
    {
        $exception = new \Exception('Test error');
        $data = [
            'job_id' => 'job123',
            'index_name' => 'test_index',
            'document_id' => 'doc123',
            'is_bulk' => true,
            'error' => 'Test error',
            'exception' => $exception,
        ];

        $event = new IndexingFailed($data);

        $this->assertEquals('job123', $event->getJobId());
        $this->assertEquals('test_index', $event->getIndexName());
        $this->assertEquals('doc123', $event->getDocumentId());
        $this->assertTrue($event->isBulk());
        $this->assertEquals('Test error', $event->getError());
        $this->assertEquals($exception, $event->getException());
    }

    /** @test */
    public function search_completed_event_has_correct_data(): void
    {
        $data = [
            'job_id' => 'search123',
            'index_name' => 'test_index',
            'query' => ['term' => 'test'],
            'size' => 10,
            'from' => 0,
            'result' => ['hits' => []],
        ];

        $event = new SearchCompleted($data);

        $this->assertEquals('search123', $event->getJobId());
        $this->assertEquals('test_index', $event->getIndexName());
        $this->assertEquals(['term' => 'test'], $event->getQuery());
        $this->assertEquals(10, $event->getSize());
        $this->assertEquals(0, $event->getFrom());
        $this->assertEquals(['hits' => []], $event->getResult());
    }

    /** @test */
    public function search_failed_event_has_correct_data(): void
    {
        $exception = new \Exception('Search error');
        $data = [
            'job_id' => 'search123',
            'index_name' => 'test_index',
            'query' => ['term' => 'test'],
            'error' => 'Search error',
            'exception' => $exception,
        ];

        $event = new SearchFailed($data);

        $this->assertEquals('search123', $event->getJobId());
        $this->assertEquals('test_index', $event->getIndexName());
        $this->assertEquals(['term' => 'test'], $event->getQuery());
        $this->assertEquals('Search error', $event->getError());
        $this->assertEquals($exception, $event->getException());
    }

    /** @test */
    public function deletion_completed_event_has_correct_data(): void
    {
        $data = [
            'job_id' => 'delete123',
            'index_name' => 'test_index',
            'document_id' => 'doc123',
            'result' => ['deleted' => true],
        ];

        $event = new DeletionCompleted($data);

        $this->assertEquals('delete123', $event->getJobId());
        $this->assertEquals('test_index', $event->getIndexName());
        $this->assertEquals('doc123', $event->getDocumentId());
        $this->assertEquals(['deleted' => true], $event->getResult());
    }

    /** @test */
    public function deletion_failed_event_has_correct_data(): void
    {
        $exception = new \Exception('Delete error');
        $data = [
            'job_id' => 'delete123',
            'index_name' => 'test_index',
            'document_id' => 'doc123',
            'error' => 'Delete error',
            'exception' => $exception,
        ];

        $event = new DeletionFailed($data);

        $this->assertEquals('delete123', $event->getJobId());
        $this->assertEquals('test_index', $event->getIndexName());
        $this->assertEquals('doc123', $event->getDocumentId());
        $this->assertEquals('Delete error', $event->getError());
        $this->assertEquals($exception, $event->getException());
    }

    /** @test */
    public function events_handle_missing_optional_data_gracefully(): void
    {
        $data = [
            'job_id' => 'job123',
            'index_name' => 'test_index',
        ];

        $indexingEvent = new IndexingCompleted($data);
        $this->assertNull($indexingEvent->getDocumentId());
        $this->assertFalse($indexingEvent->isBulk());
        $this->assertEquals([], $indexingEvent->getResult());

        $failedEvent = new IndexingFailed($data);
        $this->assertEquals('Unknown error', $failedEvent->getError());
        $this->assertNull($failedEvent->getException());
    }
}
