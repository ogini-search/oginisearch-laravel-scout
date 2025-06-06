<?php

namespace OginiScoutDriver\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Facades\Ogini;
use OginiScoutDriver\Facades\AsyncOgini;
use Mockery;

class GlobalHelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure helper functions are loaded
        if (!function_exists('ogini_search')) {
            require_once __DIR__ . '/../../../src/helpers.php';
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function ogini_search_function_works(): void
    {
        Ogini::shouldReceive('search')
            ->once()
            ->with('test_index', ['query' => ['match' => ['_all' => 'test query']]], 10, 0)
            ->andReturn(['hits' => []]);

        $result = ogini_search('test_index', 'test query');

        $this->assertEquals(['hits' => []], $result);
    }

    /** @test */
    public function ogini_suggest_function_works(): void
    {
        Ogini::shouldReceive('getQuerySuggestions')
            ->once()
            ->with('test_index', 'test', ['size' => 5])
            ->andReturn(['suggestions' => []]);

        $result = ogini_suggest('test_index', 'test', 5);

        $this->assertEquals(['suggestions' => []], $result);
    }

    /** @test */
    public function ogini_autocomplete_function_works(): void
    {
        Ogini::shouldReceive('getAutocompleteSuggestions')
            ->once()
            ->with('test_index', 'test', ['size' => 5])
            ->andReturn(['suggestions' => []]);

        $result = ogini_autocomplete('test_index', 'test', 5);

        $this->assertEquals(['suggestions' => []], $result);
    }

    /** @test */
    public function ogini_index_document_function_works(): void
    {
        Ogini::shouldReceive('indexDocument')
            ->once()
            ->with('test_index', ['title' => 'Test'], 'doc1')
            ->andReturn(['_id' => 'doc1']);

        $result = ogini_index_document('test_index', ['title' => 'Test'], 'doc1');

        $this->assertEquals(['_id' => 'doc1'], $result);
    }

    /** @test */
    public function ogini_search_async_function_works(): void
    {
        AsyncOgini::shouldReceive('searchAsync')
            ->once()
            ->with('test_index', ['query' => ['match' => ['_all' => 'test']]], 10, 0, null)
            ->andReturn('promise');

        $result = ogini_search_async('test_index', 'test');

        $this->assertEquals('promise', $result);
    }

    /** @test */
    public function ogini_bulk_index_function_works(): void
    {
        Ogini::shouldReceive('bulkIndexDocuments')
            ->once()
            ->with('test_index', [['title' => 'Doc1'], ['title' => 'Doc2']])
            ->andReturn(['indexed' => 2]);

        $result = ogini_bulk_index('test_index', [['title' => 'Doc1'], ['title' => 'Doc2']]);

        $this->assertEquals(['indexed' => 2], $result);
    }

    /** @test */
    public function ogini_delete_document_function_works(): void
    {
        Ogini::shouldReceive('deleteDocument')
            ->once()
            ->with('test_index', 'doc1')
            ->andReturn(['deleted' => true]);

        $result = ogini_delete_document('test_index', 'doc1');

        $this->assertEquals(['deleted' => true], $result);
    }

    /** @test */
    public function ogini_manage_synonyms_function_works(): void
    {
        Ogini::shouldReceive('addSynonyms')
            ->once()
            ->with('test_index', ['happy,glad,cheerful'])
            ->andReturn(['success' => true]);

        $result = ogini_manage_synonyms('test_index', ['happy,glad,cheerful'], 'add');

        $this->assertEquals(['success' => true], $result);
    }

    /** @test */
    public function ogini_manage_stopwords_function_works(): void
    {
        Ogini::shouldReceive('getStopwords')
            ->once()
            ->with('test_index')
            ->andReturn(['stopwords' => ['the', 'and', 'or']]);

        $result = ogini_manage_stopwords('test_index', [], 'get');

        $this->assertEquals(['stopwords' => ['the', 'and', 'or']], $result);
    }

    /** @test */
    public function ogini_sanitize_query_function_works(): void
    {
        $result = ogini_sanitize_query('<script>alert("test")</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('"', $result);
    }

    /** @test */
    public function ogini_validate_document_function_works(): void
    {
        $document = ['title' => 'Test'];
        $errors = ogini_validate_document($document, ['title']);

        $this->assertEmpty($errors);
    }

    /** @test */
    public function ogini_health_check_function_works(): void
    {
        Ogini::shouldReceive('listIndices')
            ->once()
            ->andReturn(['indices' => []]);

        $health = ogini_health_check();

        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('timestamp', $health);
    }

    /** @test */
    public function ogini_format_results_function_works(): void
    {
        $rawResults = [
            'total' => 10,
            'hits' => [['_id' => 'doc1', '_score' => 1.5, '_source' => ['title' => 'Test']]]
        ];

        $formatted = ogini_format_results($rawResults);

        $this->assertEquals(10, $formatted['total']);
        $this->assertCount(1, $formatted['hits']);
        $this->assertEquals('doc1', $formatted['hits'][0]['id']);
    }

    /** @test */
    public function ogini_generate_job_id_function_works(): void
    {
        $jobId = ogini_generate_job_id('test');

        $this->assertStringStartsWith('test_', $jobId);
        $this->assertGreaterThan(10, strlen($jobId));
    }

    /** @test */
    public function ogini_estimate_index_size_function_works(): void
    {
        $documents = [['title' => 'Test'], ['title' => 'Another']];
        $estimate = ogini_estimate_index_size($documents);

        $this->assertEquals(2, $estimate['document_count']);
        $this->assertArrayHasKey('total_size_bytes', $estimate);
    }

    /** @test */
    public function ogini_build_pagination_function_works(): void
    {
        $results = ['total' => 50];
        $pagination = ogini_build_pagination($results, 2, 10);

        $this->assertEquals(2, $pagination['current_page']);
        $this->assertEquals(50, $pagination['total']);
        $this->assertEquals(5, $pagination['total_pages']);
    }

    /** @test */
    public function ogini_extract_text_function_works(): void
    {
        $content = '<p>Hello <strong>world</strong>!</p>';
        $text = ogini_extract_text($content);

        $this->assertEquals('Hello world!', $text);
    }
}
