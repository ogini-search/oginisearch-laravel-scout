<?php

namespace OginiScoutDriver\Tests\Integration;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Client\OginiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;

class LoadTestingTest extends TestCase
{
    private OginiClient $client;
    private MockHandler $mockHandler;
    private string $testIndex = 'load_test_index';
    private array $loadTestResults = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->client = new OginiClient('http://localhost:3000', 'test-key');
        $this->client->setHttpClient($httpClient);
    }

    protected function tearDown(): void
    {
        $this->outputLoadTestResults();
        parent::tearDown();
    }

    /**
     * @test
     * @group load-tests
     */
    public function it_handles_concurrent_search_requests(): void
    {
        $concurrentLevels = [10, 25, 50, 100];
        $results = [];

        foreach ($concurrentLevels as $concurrency) {
            // Mock responses for concurrent requests
            for ($i = 0; $i < $concurrency; $i++) {
                $this->mockHandler->append(new Response(200, [], json_encode([
                    'hits' => [
                        ['_id' => "concurrent_$i", '_score' => 1.0, '_source' => ['title' => "Result $i"]]
                    ],
                    'total' => 1,
                    'took' => mt_rand(10, 50) // Random response time
                ])));
            }

            $query = ['query' => ['match' => ['title' => 'concurrent test']]];

            $start = microtime(true);

            // Simulate concurrent requests (sequential execution for test purposes)
            $responses = [];
            for ($i = 0; $i < $concurrency; $i++) {
                $responses[] = $this->client->search($this->testIndex, $query, 10, 0);
            }

            $end = microtime(true);
            $totalTime = ($end - $start) * 1000;

            $results[$concurrency] = [
                'concurrent_requests' => $concurrency,
                'total_time_ms' => $totalTime,
                'avg_time_per_request_ms' => $totalTime / $concurrency,
                'requests_per_second' => ($concurrency / $totalTime) * 1000,
                'success_rate' => (count($responses) / $concurrency) * 100,
            ];

            // Assertions for concurrent performance
            $this->assertCount($concurrency, $responses, "All concurrent requests should complete");
            $this->assertGreaterThan(0, $results[$concurrency]['requests_per_second'], "Should handle requests per second");
            $this->assertEquals(100, $results[$concurrency]['success_rate'], "Should have 100% success rate");
        }

        $this->loadTestResults['concurrent_search'] = $results;

        // Performance degradation check
        $baselineRps = $results[10]['requests_per_second'];
        $highLoadRps = $results[100]['requests_per_second'];
        $degradationPercent = (($baselineRps - $highLoadRps) / $baselineRps) * 100;

        $this->assertLessThan(80, $degradationPercent, "Performance should not degrade by more than 80% under high load");
    }

    /**
     * @test
     * @group load-tests
     */
    public function it_handles_concurrent_indexing_requests(): void
    {
        $concurrentDocuments = [10, 25, 50, 100];
        $results = [];

        foreach ($concurrentDocuments as $docCount) {
            // Mock responses for concurrent indexing
            for ($i = 0; $i < $docCount; $i++) {
                $this->mockHandler->append(new Response(200, [], json_encode([
                    '_id' => "index_doc_$i",
                    'result' => 'created',
                    'version' => 1
                ])));
            }

            $start = microtime(true);

            // Simulate concurrent indexing
            $indexingResults = [];
            for ($i = 0; $i < $docCount; $i++) {
                $document = [
                    'title' => "Concurrent Document $i",
                    'content' => "Content for concurrent indexing test $i",
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
                $indexingResults[] = $this->client->indexDocument($this->testIndex, $document, "doc_$i");
            }

            $end = microtime(true);
            $totalTime = ($end - $start) * 1000;

            $results[$docCount] = [
                'concurrent_documents' => $docCount,
                'total_time_ms' => $totalTime,
                'avg_time_per_document_ms' => $totalTime / $docCount,
                'documents_per_second' => ($docCount / $totalTime) * 1000,
                'success_rate' => (count($indexingResults) / $docCount) * 100,
            ];

            // Assertions for concurrent indexing
            $this->assertCount($docCount, $indexingResults, "All documents should be indexed");
            $this->assertGreaterThan(0, $results[$docCount]['documents_per_second'], "Should index documents per second");
            $this->assertEquals(100, $results[$docCount]['success_rate'], "Should have 100% success rate");
        }

        $this->loadTestResults['concurrent_indexing'] = $results;
    }

    /**
     * @test
     * @group load-tests
     */
    public function it_handles_batch_processing_stress_test(): void
    {
        $batchSizes = [50, 100, 200, 500];
        $results = [];

        foreach ($batchSizes as $batchSize) {
            // Mock bulk indexing response
            $bulkResponse = ['items' => []];
            for ($i = 0; $i < $batchSize; $i++) {
                $bulkResponse['items'][] = [
                    'index' => ['_id' => "bulk_$i", 'result' => 'created', 'status' => 201]
                ];
            }
            $this->mockHandler->append(new Response(200, [], json_encode($bulkResponse)));

            // Generate batch documents
            $documents = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $documents[] = [
                    'id' => "batch_doc_$i",
                    'document' => [
                        'title' => "Batch Document $i",
                        'content' => "Batch processing content $i " . str_repeat("test ", 10),
                        'tags' => ['batch', 'stress', 'test'],
                        'metadata' => [
                            'created_at' => date('Y-m-d H:i:s'),
                            'batch_id' => $batchSize,
                            'sequence' => $i
                        ]
                    ]
                ];
            }

            $start = microtime(true);
            $result = $this->client->bulkIndexDocuments($this->testIndex, $documents);
            $end = microtime(true);

            $totalTime = ($end - $start) * 1000;
            $memoryUsed = memory_get_peak_usage(true) / 1024 / 1024; // MB

            $results[$batchSize] = [
                'batch_size' => $batchSize,
                'processing_time_ms' => $totalTime,
                'documents_per_second' => ($batchSize / $totalTime) * 1000,
                'time_per_document_ms' => $totalTime / $batchSize,
                'memory_usage_mb' => $memoryUsed,
                'processed_items' => count($result['items'] ?? []),
                'success_rate' => (count($result['items'] ?? []) / $batchSize) * 100,
            ];

            // Stress test assertions
            $this->assertGreaterThan(
                $batchSize * 0.9,
                count($result['items'] ?? []),
                "At least 90% of documents should be processed successfully"
            );
            $this->assertLessThan(
                30000,
                $totalTime,
                "Batch processing should complete within 30 seconds"
            );
            $this->assertLessThan(
                200,
                $memoryUsed,
                "Memory usage should stay under 200MB"
            );
        }

        $this->loadTestResults['batch_processing_stress'] = $results;
    }

    /**
     * @test
     * @group load-tests
     */
    public function it_handles_mixed_workload_stress_test(): void
    {
        $workloadMix = [
            'search_operations' => 60,
            'index_operations' => 30,
            'delete_operations' => 10,
        ];

        $totalOperations = 100;
        $results = [];

        // Calculate operations per type
        $searchOps = intval($totalOperations * ($workloadMix['search_operations'] / 100));
        $indexOps = intval($totalOperations * ($workloadMix['index_operations'] / 100));
        $deleteOps = intval($totalOperations * ($workloadMix['delete_operations'] / 100));

        // Mock responses for mixed workload
        for ($i = 0; $i < $searchOps; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                'hits' => [['_id' => "search_$i", '_score' => 1.0, '_source' => ['title' => "Result $i"]]],
                'total' => 1
            ])));
        }

        for ($i = 0; $i < $indexOps; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                '_id' => "index_$i",
                'result' => 'created'
            ])));
        }

        for ($i = 0; $i < $deleteOps; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                '_id' => "delete_$i",
                'result' => 'deleted'
            ])));
        }

        $start = microtime(true);
        $memoryStart = memory_get_usage(true);

        $operations = [];

        // Execute search operations
        for ($i = 0; $i < $searchOps; $i++) {
            $query = ['query' => ['match' => ['title' => "search term $i"]]];
            $operations[] = ['type' => 'search', 'result' => $this->client->search($this->testIndex, $query)];
        }

        // Execute index operations
        for ($i = 0; $i < $indexOps; $i++) {
            $document = ['title' => "Mixed workload doc $i", 'content' => "Content $i"];
            $operations[] = ['type' => 'index', 'result' => $this->client->indexDocument($this->testIndex, $document)];
        }

        // Execute delete operations
        for ($i = 0; $i < $deleteOps; $i++) {
            $operations[] = ['type' => 'delete', 'result' => $this->client->deleteDocument($this->testIndex, "delete_$i")];
        }

        $end = microtime(true);
        $memoryEnd = memory_get_usage(true);

        $totalTime = ($end - $start) * 1000;
        $memoryUsed = ($memoryEnd - $memoryStart) / 1024 / 1024; // MB

        $results = [
            'total_operations' => $totalOperations,
            'search_operations' => $searchOps,
            'index_operations' => $indexOps,
            'delete_operations' => $deleteOps,
            'total_time_ms' => $totalTime,
            'operations_per_second' => ($totalOperations / $totalTime) * 1000,
            'avg_time_per_operation_ms' => $totalTime / $totalOperations,
            'memory_used_mb' => $memoryUsed,
            'success_rate' => (count($operations) / $totalOperations) * 100,
        ];

        $this->loadTestResults['mixed_workload_stress'] = $results;

        // Mixed workload assertions
        $this->assertCount($totalOperations, $operations, "All operations should complete");
        $this->assertGreaterThan(100, $results['operations_per_second'], "Should handle at least 100 ops/sec");
        $this->assertLessThan(20000, $totalTime, "Mixed workload should complete within 20 seconds");
        $this->assertEquals(100, $results['success_rate'], "Should have 100% success rate");
    }

    /**
     * @test
     * @group load-tests
     */
    public function it_handles_sustained_load_test(): void
    {
        $duration = 2; // seconds (reduced for faster testing)
        $requestsPerSecond = 20;
        $totalRequests = $duration * $requestsPerSecond;

        // Mock responses for sustained load
        for ($i = 0; $i < $totalRequests; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                'hits' => [['_id' => "sustained_$i", '_score' => 1.0, '_source' => ['title' => "Result $i"]]],
                'total' => 1,
                'took' => mt_rand(5, 25)
            ])));
        }

        $start = microtime(true);
        $memoryStart = memory_get_usage(true);
        $responseTimes = [];
        $errors = 0;

        for ($i = 0; $i < $totalRequests; $i++) {
            $requestStart = microtime(true);

            try {
                $query = ['query' => ['match' => ['title' => "sustained load $i"]]];
                $result = $this->client->search($this->testIndex, $query);

                $requestEnd = microtime(true);
                $responseTimes[] = ($requestEnd - $requestStart) * 1000;
            } catch (\Exception $e) {
                $errors++;
            }

            // Simulate sustained load timing (reduced delay for faster testing)
            if ($i < $totalRequests - 1) {
                usleep(10000); // 10ms delay for faster testing
            }
        }

        $end = microtime(true);
        $memoryEnd = memory_get_usage(true);

        $totalTime = ($end - $start) * 1000;
        $avgResponseTime = array_sum($responseTimes) / count($responseTimes);
        $memoryUsed = ($memoryEnd - $memoryStart) / 1024 / 1024; // MB

        $results = [
            'duration_seconds' => $duration,
            'target_rps' => $requestsPerSecond,
            'total_requests' => $totalRequests,
            'successful_requests' => count($responseTimes),
            'failed_requests' => $errors,
            'actual_rps' => count($responseTimes) / ($totalTime / 1000),
            'avg_response_time_ms' => $avgResponseTime,
            'min_response_time_ms' => min($responseTimes),
            'max_response_time_ms' => max($responseTimes),
            'p95_response_time_ms' => $this->calculatePercentile($responseTimes, 95),
            'memory_used_mb' => $memoryUsed,
            'success_rate' => (count($responseTimes) / $totalRequests) * 100,
        ];

        $this->loadTestResults['sustained_load'] = $results;

        // Sustained load assertions
        $this->assertGreaterThan(95, $results['success_rate'], "Should maintain >95% success rate");
        $this->assertLessThan(200, $results['avg_response_time_ms'], "Average response time should be <200ms");
        $this->assertLessThan(500, $results['p95_response_time_ms'], "95th percentile should be <500ms");
        $this->assertEquals(0, $errors, "Should have no errors during sustained load");
    }

    /**
     * @test
     * @group load-tests
     */
    public function it_handles_spike_load_test(): void
    {
        $normalLoad = 10;
        $spikeLoad = 100;
        $spikeDuration = 2; // seconds

        $results = [];

        // Test normal load first
        for ($i = 0; $i < $normalLoad; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                'hits' => [['_id' => "normal_$i", '_score' => 1.0, '_source' => ['title' => "Normal $i"]]],
                'total' => 1
            ])));
        }

        $start = microtime(true);
        $normalResponses = [];
        for ($i = 0; $i < $normalLoad; $i++) {
            $query = ['query' => ['match' => ['title' => "normal load $i"]]];
            $normalResponses[] = $this->client->search($this->testIndex, $query);
        }
        $normalEnd = microtime(true);

        $normalTime = ($normalEnd - $start) * 1000;
        $normalRps = ($normalLoad / $normalTime) * 1000;

        // Test spike load
        for ($i = 0; $i < $spikeLoad; $i++) {
            $this->mockHandler->append(new Response(200, [], json_encode([
                'hits' => [['_id' => "spike_$i", '_score' => 1.0, '_source' => ['title' => "Spike $i"]]],
                'total' => 1
            ])));
        }

        $spikeStart = microtime(true);
        $spikeResponses = [];
        for ($i = 0; $i < $spikeLoad; $i++) {
            $query = ['query' => ['match' => ['title' => "spike load $i"]]];
            $spikeResponses[] = $this->client->search($this->testIndex, $query);
        }
        $spikeEnd = microtime(true);

        $spikeTime = ($spikeEnd - $spikeStart) * 1000;
        $spikeRps = ($spikeLoad / $spikeTime) * 1000;

        $results = [
            'normal_load_requests' => $normalLoad,
            'normal_load_rps' => $normalRps,
            'normal_load_time_ms' => $normalTime,
            'spike_load_requests' => $spikeLoad,
            'spike_load_rps' => $spikeRps,
            'spike_load_time_ms' => $spikeTime,
            'performance_degradation_percent' => (($normalRps - $spikeRps) / $normalRps) * 100,
            'spike_handling_success_rate' => (count($spikeResponses) / $spikeLoad) * 100,
        ];

        $this->loadTestResults['spike_load'] = $results;

        // Spike load assertions
        $this->assertCount($spikeLoad, $spikeResponses, "Should handle all spike requests");
        $this->assertLessThan(90, $results['performance_degradation_percent'], "Performance should not degrade >90%");
        $this->assertEquals(100, $results['spike_handling_success_rate'], "Should handle spike with 100% success");
    }

    private function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;
        return $values[$index] ?? 0;
    }

    private function outputLoadTestResults(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "LOAD TESTING RESULTS\n";
        echo str_repeat("=", 80) . "\n";

        foreach ($this->loadTestResults as $testName => $results) {
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
                            echo "    $subKey: " . (is_numeric($subValue) ? round($subValue, 2) : $subValue) . "\n";
                        }
                    } else {
                        echo "  $metric: " . (is_numeric($value) ? round($value, 2) : $value) . "\n";
                    }
                }
            }
            echo "\n";
        }

        echo str_repeat("=", 80) . "\n";
    }
}
