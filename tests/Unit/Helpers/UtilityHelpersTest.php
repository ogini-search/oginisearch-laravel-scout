<?php

namespace OginiScoutDriver\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Helpers\UtilityHelpers;
use OginiScoutDriver\Facades\Ogini;
use Illuminate\Support\Facades\Log;
use Mockery;

class UtilityHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_generate_unique_job_id(): void
    {
        $jobId1 = UtilityHelpers::generateJobId();
        $jobId2 = UtilityHelpers::generateJobId();

        $this->assertStringStartsWith('ogini_', $jobId1);
        $this->assertStringStartsWith('ogini_', $jobId2);
        $this->assertNotEquals($jobId1, $jobId2);
    }

    /** @test */
    public function it_can_generate_job_id_with_custom_prefix(): void
    {
        $jobId = UtilityHelpers::generateJobId('custom');

        $this->assertStringStartsWith('custom_', $jobId);
    }

    /** @test */
    public function it_can_format_search_results(): void
    {
        $rawResults = [
            'total' => 100,
            'took' => 25,
            'max_score' => 9.5,
            'hits' => [
                [
                    '_id' => 'doc1',
                    '_score' => 9.5,
                    '_source' => ['title' => 'Test Document'],
                    'highlight' => ['title' => ['<em>Test</em> Document']]
                ]
            ]
        ];

        $formatted = UtilityHelpers::formatSearchResults($rawResults);

        $this->assertEquals(100, $formatted['total']);
        $this->assertEquals(25, $formatted['took']);
        $this->assertEquals(9.5, $formatted['max_score']);
        $this->assertCount(1, $formatted['hits']);
        $this->assertEquals('doc1', $formatted['hits'][0]['id']);
        $this->assertEquals(9.5, $formatted['hits'][0]['score']);
        $this->assertEquals(['title' => 'Test Document'], $formatted['hits'][0]['source']);
    }

    /** @test */
    public function it_can_extract_text_from_string(): void
    {
        $content = '<p>Hello <strong>world</strong>!</p>';
        $result = UtilityHelpers::extractTextContent($content);

        $this->assertEquals('Hello world!', $result);
    }

    /** @test */
    public function it_can_extract_text_from_array(): void
    {
        $content = [
            'title' => 'Test Title',
            'description' => 'Test <em>description</em>',
            'nested' => ['content' => 'Nested content']
        ];

        $result = UtilityHelpers::extractTextContent($content);

        $this->assertStringContainsString('Test Title', $result);
        $this->assertStringContainsString('Test description', $result);
        $this->assertStringContainsString('Nested content', $result);
    }

    /** @test */
    public function it_can_validate_document(): void
    {
        $document = [
            'title' => 'Test Document',
            'content' => 'This is test content',
            'status' => 'published'
        ];

        $errors = UtilityHelpers::validateDocument($document, ['title', 'content']);

        $this->assertEmpty($errors);
    }

    /** @test */
    public function it_detects_missing_required_fields(): void
    {
        $document = ['title' => 'Test Document'];

        $errors = UtilityHelpers::validateDocument($document, ['title', 'content']);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('content', $errors[0]);
    }

    /** @test */
    public function it_detects_large_fields(): void
    {
        $document = ['large_field' => str_repeat('x', 10000001)]; // > 10MB

        $errors = UtilityHelpers::validateDocument($document);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('too large', $errors[0]);
    }

    /** @test */
    public function it_can_sanitize_query(): void
    {
        $query = '<script>alert("xss")</script> "malicious" query\'s content';
        $sanitized = UtilityHelpers::sanitizeQuery($query);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringNotContainsString('"', $sanitized);
        $this->assertStringNotContainsString("'", $sanitized);
        $this->assertEquals('scriptalert(xss)/script malicious querys content', $sanitized);
    }

    /** @test */
    public function it_limits_query_length(): void
    {
        $longQuery = str_repeat('a', 1500);
        $sanitized = UtilityHelpers::sanitizeQuery($longQuery);

        $this->assertEquals(1000, strlen($sanitized));
    }

    /** @test */
    public function it_can_build_pagination(): void
    {
        $results = ['total' => 150];
        $pagination = UtilityHelpers::buildPagination($results, 2, 10);

        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertEquals(150, $pagination['total']);
        $this->assertEquals(15, $pagination['total_pages']);
        $this->assertTrue($pagination['has_previous']);
        $this->assertTrue($pagination['has_next']);
        $this->assertEquals(1, $pagination['previous_page']);
        $this->assertEquals(3, $pagination['next_page']);
    }

    /** @test */
    public function it_can_debug_search_performance(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->with('Search performance metrics', Mockery::type('array'));

        $results = ['total' => 50, 'took' => 150, 'max_score' => 5.5];

        UtilityHelpers::debugSearchPerformance('test query', $results, 0.5);

        // Assert that the method executed without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_logs_warning_for_slow_queries(): void
    {
        Log::shouldReceive('debug')->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('Slow search query detected', Mockery::type('array'));

        $results = ['total' => 50];

        UtilityHelpers::debugSearchPerformance('test query', $results, 1.5);

        // Assert that the method executed without throwing exceptions
        $this->assertTrue(true);
    }

    /** @test */
    public function it_can_check_service_health(): void
    {
        Ogini::shouldReceive('listIndices')
            ->once()
            ->andReturn(['indices' => ['index1', 'index2']]);

        $health = UtilityHelpers::checkServiceHealth();

        $this->assertEquals('healthy', $health['status']);
        $this->assertArrayHasKey('response_time', $health);
        $this->assertArrayHasKey('indices_count', $health);
        $this->assertEquals(2, $health['indices_count']);
    }

    /** @test */
    public function it_handles_service_health_errors(): void
    {
        Ogini::shouldReceive('listIndices')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $health = UtilityHelpers::checkServiceHealth();

        $this->assertEquals('unhealthy', $health['status']);
        $this->assertEquals('Service unavailable', $health['error']);
    }

    /** @test */
    public function it_can_estimate_index_size(): void
    {
        $documents = [
            ['title' => 'Doc 1', 'content' => 'Content 1'],
            ['title' => 'Doc 2', 'content' => 'Content 2'],
        ];

        $estimate = UtilityHelpers::estimateIndexSize($documents);

        $this->assertEquals(2, $estimate['document_count']);
        $this->assertArrayHasKey('total_size_bytes', $estimate);
        $this->assertArrayHasKey('total_size_mb', $estimate);
        $this->assertArrayHasKey('avg_document_size_bytes', $estimate);
        $this->assertArrayHasKey('field_statistics', $estimate);
        $this->assertArrayHasKey('estimated_index_overhead_mb', $estimate);
    }

    /** @test */
    public function it_can_generate_search_analytics(): void
    {
        $searchResults = [
            ['query' => 'test query', 'response_time' => 0.5, 'total_results' => 10],
            ['query' => 'another test', 'response_time' => 0.3, 'total_results' => 5],
            ['query' => 'no results query', 'response_time' => 0.2, 'total_results' => 0],
        ];

        $analytics = UtilityHelpers::generateSearchAnalytics($searchResults);

        $this->assertEquals(3, $analytics['total_searches']);
        $this->assertEquals(0.33, $analytics['avg_response_time']); // (0.5 + 0.3 + 0.2) / 3
        $this->assertEquals(5, $analytics['avg_result_count']); // (10 + 5 + 0) / 3
        $this->assertEquals(66.67, $analytics['success_rate']); // 2/3 * 100
        $this->assertContains('no results query', $analytics['zero_result_queries']);
        $this->assertArrayHasKey('popular_terms', $analytics);
    }

    /** @test */
    public function it_can_cleanup_old_data(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Cleanup operation completed', Mockery::type('array'));

        $cleaned = UtilityHelpers::cleanupOldData(48);

        $this->assertIsInt($cleaned);
        $this->assertGreaterThanOrEqual(0, $cleaned);
    }
}
