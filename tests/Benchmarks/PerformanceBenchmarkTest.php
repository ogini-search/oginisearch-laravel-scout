<?php

namespace OginiScoutDriver\Tests\Benchmarks;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Client\AsyncOginiClient;
use OginiScoutDriver\Helpers\UtilityHelpers;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class PerformanceBenchmarkTest extends TestCase
{
    private OginiClient $client;
    private AsyncOginiClient $asyncClient;
    private MockHandler $mockHandler;
    private string $testIndex = 'performance_test';
    private array $benchmarkResults = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->client = new OginiClient('http://localhost:3000', 'test-key');
        $this->client->setHttpClient($httpClient);

        $this->asyncClient = new AsyncOginiClient('http://localhost:3000', 'test-key');
        $this->asyncClient->setHttpClient($httpClient);
    }

    protected function tearDown(): void
    {
        if (!empty($this->benchmarkResults)) {
            $this->outputBenchmarkResults();
        }
        parent::tearDown();
    }

    /** @test */
    public function it_benchmarks_single_document_indexing(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['_id' => 'doc1', 'result' => 'created'])));

        $document = [
            'title' => 'Performance Test Document',
            'content' => str_repeat('This is test content for performance benchmarking. ', 100),
            'category' => 'Test',
            'tags' => ['performance', 'benchmark', 'testing'],
            'metadata' => [
                'author' => 'Test Author',
                'created_at' => date('Y-m-d H:i:s'),
                'word_count' => 500,
            ]
        ];

        $iterations = 10;
        $times = [];

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            $result = $this->client->indexDocument($this->testIndex, $document, "doc_$i");
            $end = microtime(true);

            $times[] = ($end - $start) * 1000; // Convert to milliseconds
            $this->assertArrayHasKey('_id', $result);

            // Reset mock for next iteration
            $this->mockHandler->append(new Response(200, [], json_encode(['_id' => "doc_$i", 'result' => 'created'])));
        }

        $this->benchmarkResults['single_document_indexing'] = [
            'iterations' => $iterations,
            'times_ms' => $times,
            'avg_time_ms' => array_sum($times) / count($times),
            'min_time_ms' => min($times),
            'max_time_ms' => max($times),
            'total_time_ms' => array_sum($times),
        ];

        // Performance assertions
        $avgTime = $this->benchmarkResults['single_document_indexing']['avg_time_ms'];
        $this->assertLessThan(100, $avgTime, "Average indexing time should be under 100ms, got {$avgTime}ms");
        $this->assertGreaterThan(0, $avgTime, "Indexing time should be positive");
    }

    /** @test */
    public function it_benchmarks_bulk_document_indexing(): void
    {
        $batchSizes = [10, 50, 100, 500];
        $results = [];

        foreach ($batchSizes as $batchSize) {
            $documents = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $documents[] = [
                    'title' => "Bulk Document $i",
                    'content' => str_repeat("Content for document $i. ", 50),
                    'category' => 'Bulk Test',
                    'batch_number' => $batchSize,
                    'document_index' => $i,
                ];
            }

            $this->mockHandler->append(new Response(200, [], json_encode([
                'took' => rand(10, 100),
                'items' => array_fill(0, $batchSize, ['index' => ['_id' => 'test', 'result' => 'created']])
            ])));

            $start = microtime(true);
            $result = $this->client->bulkIndexDocuments($this->testIndex, $documents);
            $end = microtime(true);

            $time = ($end - $start) * 1000;
            $throughput = $batchSize / ($time / 1000); // documents per second

            $results[$batchSize] = [
                'batch_size' => $batchSize,
                'time_ms' => $time,
                'throughput_docs_per_sec' => $throughput,
                'time_per_doc_ms' => $time / $batchSize,
            ];

            $this->assertArrayHasKey('items', $result);
        }

        $this->benchmarkResults['bulk_indexing'] = $results;

        // Performance assertions
        foreach ($results as $batchSize => $metrics) {
            $this->assertGreaterThan(
                0,
                $metrics['throughput_docs_per_sec'],
                "Throughput should be positive for batch size $batchSize"
            );
            $this->assertLessThan(
                50,
                $metrics['time_per_doc_ms'],
                "Time per document should be under 50ms for batch size $batchSize"
            );
        }
    }

    /** @test */
    public function it_benchmarks_search_performance(): void
    {
        $searchQueries = [
            'simple_term' => ['query' => ['match' => ['title' => 'test']]],
            'multi_field' => ['query' => ['multi_match' => ['query' => 'test search', 'fields' => ['title', 'content']]]],
            'complex_bool' => [
                'query' => [
                    'bool' => [
                        'must' => [['match' => ['title' => 'test']]],
                        'filter' => [['term' => ['category' => 'test']]],
                        'should' => [['match' => ['content' => 'performance']]]
                    ]
                ]
            ],
            'with_aggregations' => [
                'query' => ['match_all' => []],
                'aggs' => [
                    'categories' => ['terms' => ['field' => 'category']],
                    'avg_score' => ['avg' => ['field' => 'score']]
                ]
            ]
        ];

        $results = [];

        foreach ($searchQueries as $queryType => $query) {
            $expectedResponse = [
                'took' => rand(5, 50),
                'hits' => array_fill(0, 100, [
                    '_id' => 'test',
                    '_score' => 1.0,
                    '_source' => ['title' => 'Test Document']
                ]),
                'total' => 1000,
                'aggregations' => [
                    'categories' => ['buckets' => [['key' => 'test', 'doc_count' => 100]]],
                    'avg_score' => ['value' => 0.75]
                ]
            ];

            $iterations = 5;
            $times = [];

            for ($i = 0; $i < $iterations; $i++) {
                $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

                $start = microtime(true);
                $result = $this->client->search($this->testIndex, $query, 20, 0);
                $end = microtime(true);

                $times[] = ($end - $start) * 1000;
                $this->assertArrayHasKey('hits', $result);
            }

            $results[$queryType] = [
                'iterations' => $iterations,
                'times_ms' => $times,
                'avg_time_ms' => array_sum($times) / count($times),
                'min_time_ms' => min($times),
                'max_time_ms' => max($times),
                'queries_per_second' => 1000 / (array_sum($times) / count($times)),
            ];
        }

        $this->benchmarkResults['search_performance'] = $results;

        // Performance assertions
        foreach ($results as $queryType => $metrics) {
            $this->assertLessThan(
                200,
                $metrics['avg_time_ms'],
                "Average search time for $queryType should be under 200ms"
            );
            $this->assertGreaterThan(
                5,
                $metrics['queries_per_second'],
                "Should handle at least 5 queries per second for $queryType"
            );
        }
    }

    /** @test */
    public function it_benchmarks_memory_usage(): void
    {
        $initialMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // Simulate large document operations
        $largeDocuments = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeDocuments[] = [
                'title' => "Large Document $i",
                'content' => str_repeat("This is a large document with lots of content. ", 1000),
                'metadata' => array_fill(0, 100, "metadata_value_$i"),
            ];
        }

        $this->mockHandler->append(new Response(200, [], json_encode([
            'took' => 150,
            'items' => array_fill(0, 1000, ['index' => ['_id' => 'test', 'result' => 'created']])
        ])));

        $memoryBefore = memory_get_usage(true);
        $result = $this->client->bulkIndexDocuments($this->testIndex, $largeDocuments);
        $memoryAfter = memory_get_usage(true);
        $finalPeakMemory = memory_get_peak_usage(true);

        $memoryUsed = $memoryAfter - $memoryBefore;
        $peakMemoryIncrease = $finalPeakMemory - $peakMemory;

        $this->benchmarkResults['memory_usage'] = [
            'initial_memory_mb' => round($initialMemory / 1024 / 1024, 2),
            'memory_before_mb' => round($memoryBefore / 1024 / 1024, 2),
            'memory_after_mb' => round($memoryAfter / 1024 / 1024, 2),
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'peak_memory_increase_mb' => round($peakMemoryIncrease / 1024 / 1024, 2),
            'final_peak_memory_mb' => round($finalPeakMemory / 1024 / 1024, 2),
        ];

        // Memory assertions
        $this->assertLessThan(
            100,
            $memoryUsed / 1024 / 1024,
            "Memory usage should be under 100MB for 1000 large documents"
        );
        $this->assertArrayHasKey('items', $result);
    }

    /** @test */
    public function it_benchmarks_concurrent_search_performance(): void
    {
        // Mock responses for concurrent searches
        for ($i = 0; $i < 10; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                'took' => rand(10, 100),
                'hits' => [['_id' => "concurrent_$i", '_score' => 1.0, '_source' => ['title' => "Result $i"]]],
                'total' => 1
            ])));
        }

        $concurrentRequests = [];
        for ($i = 0; $i < 10; $i++) {
            $concurrentRequests[] = [
                'method' => 'POST',
                'endpoint' => "/api/indices/{$this->testIndex}/search",
                'data' => ['query' => ['match' => ['title' => "query_$i"]]]
            ];
        }

        $start = microtime(true);
        $results = $this->asyncClient->executeParallel($concurrentRequests);
        $end = microtime(true);

        $totalTime = ($end - $start) * 1000;
        $avgTimePerRequest = $totalTime / count($concurrentRequests);

        $this->benchmarkResults['concurrent_search'] = [
            'concurrent_requests' => count($concurrentRequests),
            'total_time_ms' => $totalTime,
            'avg_time_per_request_ms' => $avgTimePerRequest,
            'requests_per_second' => (count($concurrentRequests) / ($totalTime / 1000)),
        ];

        // Performance assertions
        $this->assertLessThan(1000, $totalTime, "Total time for 10 concurrent requests should be under 1 second");
        $this->assertGreaterThan(
            10,
            count($concurrentRequests) / ($totalTime / 1000),
            "Should handle at least 10 requests per second concurrently"
        );
    }

    /** @test */
    public function it_benchmarks_query_optimization(): void
    {
        $baseQuery = ['query' => ['match' => ['title' => 'optimization test']]];

        $optimizationStrategies = [
            'no_optimization' => $baseQuery,
            'with_filters' => [
                'query' => [
                    'bool' => [
                        'must' => $baseQuery['query'],
                        'filter' => [['term' => ['status' => 'published']]]
                    ]
                ]
            ],
            'with_source_filtering' => array_merge($baseQuery, [
                '_source' => ['title', 'category']
            ]),
            'with_limited_results' => $baseQuery,
        ];

        $results = [];

        foreach ($optimizationStrategies as $strategy => $query) {
            $expectedResponse = [
                'took' => $strategy === 'no_optimization' ? 50 : 25, // Simulate optimization benefits
                'hits' => array_fill(0, 20, ['_id' => 'test', '_score' => 1.0, '_source' => ['title' => 'Test']]),
                'total' => 1000
            ];

            $iterations = 3;
            $times = [];

            for ($i = 0; $i < $iterations; $i++) {
                $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

                $start = microtime(true);
                $size = $strategy === 'with_limited_results' ? 5 : 20;
                $result = $this->client->search($this->testIndex, $query, $size, 0);
                $end = microtime(true);

                $times[] = ($end - $start) * 1000;
            }

            $results[$strategy] = [
                'avg_time_ms' => array_sum($times) / count($times),
                'query_complexity' => $this->calculateQueryComplexity($query),
            ];
        }

        $this->benchmarkResults['query_optimization'] = $results;

        // Optimization assertions
        $baseTime = $results['no_optimization']['avg_time_ms'];
        $this->assertLessThan(
            $baseTime,
            $results['with_filters']['avg_time_ms'],
            "Filtered queries should be faster than unfiltered"
        );
        $this->assertLessThan(
            $baseTime,
            $results['with_limited_results']['avg_time_ms'],
            "Limited result queries should be faster"
        );
    }

    /** @test */
    public function it_benchmarks_cache_performance(): void
    {
        $cacheableQuery = ['query' => ['match' => ['title' => 'cache test']]];

        // Simulate cache miss (first request)
        $this->mockHandler->append(new Response(200, [], json_encode([
            'took' => 100, // Slower for cache miss
            'hits' => [['_id' => 'cache_test', '_score' => 1.0, '_source' => ['title' => 'Cache Test']]],
            'total' => 1
        ])));

        $start = microtime(true);
        $result1 = $this->client->search($this->testIndex, $cacheableQuery, 10, 0);
        $end = microtime(true);
        $firstRequestTime = ($end - $start) * 1000;

        // Simulate cache hit (subsequent requests)
        $cachedTimes = [];
        for ($i = 0; $i < 5; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                'took' => 5, // Much faster for cache hit
                'hits' => [['_id' => 'cache_test', '_score' => 1.0, '_source' => ['title' => 'Cache Test']]],
                'total' => 1
            ])));

            $start = microtime(true);
            $result = $this->client->search($this->testIndex, $cacheableQuery, 10, 0);
            $end = microtime(true);
            $cachedTimes[] = ($end - $start) * 1000;
        }

        $avgCachedTime = array_sum($cachedTimes) / count($cachedTimes);
        $cacheSpeedup = $firstRequestTime / $avgCachedTime;

        $this->benchmarkResults['cache_performance'] = [
            'first_request_time_ms' => $firstRequestTime,
            'avg_cached_time_ms' => $avgCachedTime,
            'cache_speedup_ratio' => $cacheSpeedup,
            'cache_hit_improvement_percent' => (($firstRequestTime - $avgCachedTime) / $firstRequestTime) * 100,
        ];

        // Cache performance assertions
        $this->assertGreaterThan(1.2, $cacheSpeedup, "Cache should provide at least 20% speedup");
        $this->assertLessThan($firstRequestTime, $avgCachedTime, "Cached requests should be faster");
    }

    /** @test */
    public function it_benchmarks_different_document_sizes(): void
    {
        $documentSizes = [
            'small' => ['title' => 'Small Document', 'content' => str_repeat('Small content. ', 10)],
            'medium' => ['title' => 'Medium Document', 'content' => str_repeat('Medium content. ', 100)],
            'large' => ['title' => 'Large Document', 'content' => str_repeat('Large content. ', 1000)],
            'extra_large' => ['title' => 'XL Document', 'content' => str_repeat('Extra large content. ', 5000)],
        ];

        $results = [];

        foreach ($documentSizes as $size => $document) {
            $this->mockHandler->append(new Response(200, [], json_encode(['_id' => $size, 'result' => 'created'])));

            $documentSize = strlen(json_encode($document));

            $start = microtime(true);
            $result = $this->client->indexDocument($this->testIndex, $document, $size);
            $end = microtime(true);

            $time = ($end - $start) * 1000;
            $throughput = $documentSize / ($time / 1000); // bytes per second

            $results[$size] = [
                'document_size_bytes' => $documentSize,
                'document_size_kb' => round($documentSize / 1024, 2),
                'indexing_time_ms' => $time,
                'throughput_bytes_per_sec' => $throughput,
                'throughput_kb_per_sec' => round($throughput / 1024, 2),
            ];
        }

        $this->benchmarkResults['document_size_performance'] = $results;

        // Document size performance assertions
        foreach ($results as $size => $metrics) {
            $this->assertGreaterThan(
                0,
                $metrics['throughput_kb_per_sec'],
                "Throughput should be positive for $size documents"
            );
            $this->assertLessThan(
                1000,
                $metrics['indexing_time_ms'],
                "Indexing time should be under 1 second for $size documents"
            );
        }
    }

    private function calculateQueryComplexity(array $query): int
    {
        $complexity = 0;

        if (isset($query['query']['bool'])) {
            $boolQuery = $query['query']['bool'];
            $complexity += count($boolQuery['must'] ?? []);
            $complexity += count($boolQuery['should'] ?? []);
            $complexity += count($boolQuery['must_not'] ?? []);
            $complexity += count($boolQuery['filter'] ?? []);
        } else {
            $complexity = 1; // Simple query
        }

        if (isset($query['aggs'])) {
            $complexity += count($query['aggs']);
        }

        if (isset($query['sort'])) {
            $complexity += count($query['sort']);
        }

        return $complexity;
    }

    private function outputBenchmarkResults(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "PERFORMANCE BENCHMARK RESULTS\n";
        echo str_repeat("=", 80) . "\n";

        foreach ($this->benchmarkResults as $testName => $results) {
            echo "\n" . strtoupper(str_replace('_', ' ', $testName)) . ":\n";
            echo str_repeat("-", 40) . "\n";

            if (is_array($results) && isset($results[0])) {
                // Handle arrays of results
                foreach ($results as $key => $result) {
                    if (is_array($result)) {
                        echo "  $key:\n";
                        foreach ($result as $metric => $value) {
                            echo "    $metric: " . (is_numeric($value) ? round($value, 2) : $value) . "\n";
                        }
                        echo "\n";
                    }
                }
            } else {
                // Handle single result sets
                foreach ($results as $metric => $value) {
                    if (is_array($value)) {
                        echo "  $metric:\n";
                        foreach ($value as $subKey => $subValue) {
                            if (is_array($subValue)) {
                                echo "    $subKey:\n";
                                foreach ($subValue as $subMetric => $subVal) {
                                    echo "      $subMetric: " . (is_numeric($subVal) ? round($subVal, 2) : $subVal) . "\n";
                                }
                            } else {
                                echo "    $subKey: " . (is_numeric($subValue) ? round($subValue, 2) : $subValue) . "\n";
                            }
                        }
                    } else {
                        echo "  $metric: " . (is_numeric($value) ? round($value, 2) : $value) . "\n";
                    }
                }
            }
        }

        echo "\n" . str_repeat("=", 80) . "\n";
    }
}
