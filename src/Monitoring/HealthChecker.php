<?php

namespace OginiScoutDriver\Monitoring;

use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Logging\OginiLogger;
use OginiScoutDriver\Exceptions\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Health checking for OginiSearch service.
 */
class HealthChecker
{
    protected OginiClient $client;
    protected OginiLogger $logger;
    protected PerformanceMonitor $monitor;
    protected array $healthChecks;

    /**
     * Create a new HealthChecker instance.
     *
     * @param OginiClient $client
     * @param OginiLogger|null $logger
     * @param PerformanceMonitor|null $monitor
     */
    public function __construct(
        OginiClient $client,
        ?OginiLogger $logger = null,
        ?PerformanceMonitor $monitor = null
    ) {
        $this->client = $client;
        $this->logger = $logger ?? new OginiLogger();
        $this->monitor = $monitor ?? new PerformanceMonitor($this->logger);
        $this->healthChecks = [];
    }

    /**
     * Perform a comprehensive health check.
     *
     * @return array Health check results
     */
    public function performHealthCheck(): array
    {
        $timerId = $this->monitor->startTimer('health_check');

        $results = [
            'status' => 'healthy',
            'timestamp' => microtime(true),
            'checks' => [],
            'overall_health' => 100,
        ];

        try {
            // Basic connectivity check
            $results['checks']['connectivity'] = $this->checkConnectivity();

            // Service availability check
            $results['checks']['service_availability'] = $this->checkServiceAvailability();

            // Performance check
            $results['checks']['performance'] = $this->checkPerformance();

            // Resource usage check
            $results['checks']['resources'] = $this->checkResources();

            // Index health check
            $results['checks']['indices'] = $this->checkIndices();

            // Cache health check
            $results['checks']['cache'] = $this->checkCache();

            // Calculate overall health score
            $results['overall_health'] = $this->calculateOverallHealth($results['checks']);

            // Determine overall status
            $results['status'] = $this->determineOverallStatus($results['overall_health']);
        } catch (\Throwable $e) {
            $results['status'] = 'unhealthy';
            $results['error'] = $e->getMessage();
            $results['overall_health'] = 0;

            $this->logger->logException($e, ['operation' => 'health_check']);
        }

        $this->monitor->stopTimer($timerId, ['health_status' => $results['status']]);

        // Cache results for quick access
        Cache::put('ogini:health_check', $results, 300); // 5 minutes

        return $results;
    }

    /**
     * Check basic connectivity to OginiSearch.
     *
     * @return array
     */
    protected function checkConnectivity(): array
    {
        $check = [
            'name' => 'Connectivity',
            'status' => 'healthy',
            'score' => 100,
            'details' => [],
        ];

        try {
            $startTime = microtime(true);

            // Attempt a simple ping/health endpoint
            $response = $this->client->get('/health');

            $duration = microtime(true) - $startTime;

            $check['details'] = [
                'response_time_ms' => round($duration * 1000, 2),
                'status_code' => $response['status'] ?? 200,
                'server_version' => $response['version'] ?? 'unknown',
            ];

            if ($duration > 2.0) {
                $check['status'] = 'degraded';
                $check['score'] = 70;
                $check['details']['warning'] = 'Slow response time';
            }
        } catch (\Throwable $e) {
            $check['status'] = 'unhealthy';
            $check['score'] = 0;
            $check['details']['error'] = $e->getMessage();
        }

        return $check;
    }

    /**
     * Check service availability and features.
     *
     * @return array
     */
    protected function checkServiceAvailability(): array
    {
        $check = [
            'name' => 'Service Availability',
            'status' => 'healthy',
            'score' => 100,
            'details' => [],
        ];

        try {
            // Test search functionality
            $searchResult = $this->testSearchFunctionality();
            $check['details']['search'] = $searchResult;

            // Test indexing functionality
            $indexResult = $this->testIndexingFunctionality();
            $check['details']['indexing'] = $indexResult;

            // Calculate score based on functionality
            $functionalityScore = 0;
            if ($searchResult['working']) $functionalityScore += 50;
            if ($indexResult['working']) $functionalityScore += 50;

            $check['score'] = $functionalityScore;

            if ($functionalityScore < 100) {
                $check['status'] = $functionalityScore > 0 ? 'degraded' : 'unhealthy';
            }
        } catch (\Throwable $e) {
            $check['status'] = 'unhealthy';
            $check['score'] = 0;
            $check['details']['error'] = $e->getMessage();
        }

        return $check;
    }

    /**
     * Check performance metrics.
     *
     * @return array
     */
    protected function checkPerformance(): array
    {
        $check = [
            'name' => 'Performance',
            'status' => 'healthy',
            'score' => 100,
            'details' => [],
        ];

        try {
            $metrics = $this->monitor->getMetricsSummary();
            $slowOps = $this->monitor->getSlowOperations(5);

            $check['details'] = [
                'memory_usage_mb' => $metrics['memory']['current_mb'],
                'peak_memory_mb' => $metrics['memory']['peak_mb'],
                'active_timers' => $metrics['active_timers'],
                'slow_operations_count' => count($slowOps),
                'performance_stats' => $metrics['performance_stats'],
            ];

            // Score based on performance
            $score = 100;

            // Deduct for high memory usage
            if ($metrics['memory']['current_mb'] > 500) {
                $score -= 20;
                $check['details']['warnings'][] = 'High memory usage';
            }

            // Deduct for slow operations
            if (count($slowOps) > 5) {
                $score -= 30;
                $check['details']['warnings'][] = 'Multiple slow operations detected';
            }

            $check['score'] = max(0, $score);
            $check['status'] = $score >= 80 ? 'healthy' : ($score >= 50 ? 'degraded' : 'unhealthy');
        } catch (\Throwable $e) {
            $check['status'] = 'unhealthy';
            $check['score'] = 0;
            $check['details']['error'] = $e->getMessage();
        }

        return $check;
    }

    /**
     * Check system resources.
     *
     * @return array
     */
    protected function checkResources(): array
    {
        $check = [
            'name' => 'System Resources',
            'status' => 'healthy',
            'score' => 100,
            'details' => [],
        ];

        try {
            $check['details'] = [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'memory_limit' => ini_get('memory_limit'),
                'disk_free_space' => disk_free_space(storage_path()),
                'disk_total_space' => disk_total_space(storage_path()),
            ];

            // Convert memory to MB for readability
            $check['details']['memory_usage_mb'] = round($check['details']['memory_usage'] / 1024 / 1024, 2);
            $check['details']['memory_peak_mb'] = round($check['details']['memory_peak'] / 1024 / 1024, 2);

            // Calculate disk usage percentage
            $diskUsagePercent = (1 - ($check['details']['disk_free_space'] / $check['details']['disk_total_space'])) * 100;
            $check['details']['disk_usage_percent'] = round($diskUsagePercent, 1);

            // Score based on resource usage
            $score = 100;

            if ($diskUsagePercent > 90) {
                $score -= 40;
                $check['details']['warnings'][] = 'Disk space critically low';
            } elseif ($diskUsagePercent > 80) {
                $score -= 20;
                $check['details']['warnings'][] = 'Disk space running low';
            }

            if ($check['details']['memory_usage_mb'] > 1000) {
                $score -= 30;
                $check['details']['warnings'][] = 'High memory usage';
            }

            $check['score'] = max(0, $score);
            $check['status'] = $score >= 80 ? 'healthy' : ($score >= 50 ? 'degraded' : 'unhealthy');
        } catch (\Throwable $e) {
            $check['status'] = 'unhealthy';
            $check['score'] = 0;
            $check['details']['error'] = $e->getMessage();
        }

        return $check;
    }

    /**
     * Check indices health.
     *
     * @return array
     */
    protected function checkIndices(): array
    {
        $check = [
            'name' => 'Indices Health',
            'status' => 'healthy',
            'score' => 100,
            'details' => [],
        ];

        try {
            // Get list of indices
            $indices = $this->client->get('/indices');

            $check['details'] = [
                'total_indices' => count($indices),
                'indices_status' => [],
            ];

            $healthyIndices = 0;
            foreach ($indices as $index) {
                $indexHealth = $this->checkIndexHealth($index['name']);
                $check['details']['indices_status'][$index['name']] = $indexHealth;

                if ($indexHealth['healthy']) {
                    $healthyIndices++;
                }
            }

            if (count($indices) > 0) {
                $healthPercentage = ($healthyIndices / count($indices)) * 100;
                $check['score'] = round($healthPercentage);
                $check['status'] = $healthPercentage >= 90 ? 'healthy' : ($healthPercentage >= 70 ? 'degraded' : 'unhealthy');
            }
        } catch (\Throwable $e) {
            $check['status'] = 'unhealthy';
            $check['score'] = 0;
            $check['details']['error'] = $e->getMessage();
        }

        return $check;
    }

    /**
     * Check cache health.
     *
     * @return array
     */
    protected function checkCache(): array
    {
        $check = [
            'name' => 'Cache Health',
            'status' => 'healthy',
            'score' => 100,
            'details' => [],
        ];

        try {
            $testKey = 'ogini:health_check:cache_test';
            $testValue = ['timestamp' => microtime(true), 'test' => true];

            // Test cache write
            $writeStart = microtime(true);
            Cache::put($testKey, $testValue, 60);
            $writeTime = microtime(true) - $writeStart;

            // Test cache read
            $readStart = microtime(true);
            $cachedValue = Cache::get($testKey);
            $readTime = microtime(true) - $readStart;

            // Test cache delete
            $deleteStart = microtime(true);
            Cache::forget($testKey);
            $deleteTime = microtime(true) - $deleteStart;

            $check['details'] = [
                'write_time_ms' => round($writeTime * 1000, 2),
                'read_time_ms' => round($readTime * 1000, 2),
                'delete_time_ms' => round($deleteTime * 1000, 2),
                'data_integrity' => $cachedValue === $testValue,
            ];

            // Score based on performance and integrity
            $score = 100;

            if (!$check['details']['data_integrity']) {
                $score -= 50;
                $check['details']['warnings'][] = 'Cache data integrity issue';
            }

            if ($writeTime > 0.1 || $readTime > 0.1) {
                $score -= 25;
                $check['details']['warnings'][] = 'Slow cache operations';
            }

            $check['score'] = max(0, $score);
            $check['status'] = $score >= 80 ? 'healthy' : ($score >= 50 ? 'degraded' : 'unhealthy');
        } catch (\Throwable $e) {
            $check['status'] = 'unhealthy';
            $check['score'] = 0;
            $check['details']['error'] = $e->getMessage();
        }

        return $check;
    }

    /**
     * Test search functionality.
     *
     * @return array
     */
    protected function testSearchFunctionality(): array
    {
        try {
            $startTime = microtime(true);

            // Perform a simple search
            $results = $this->client->search('test_index', [
                'query' => 'test',
                'limit' => 1,
            ]);

            $duration = microtime(true) - $startTime;

            return [
                'working' => true,
                'response_time_ms' => round($duration * 1000, 2),
                'results_count' => $results['total'] ?? 0,
            ];
        } catch (\Throwable $e) {
            return [
                'working' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test indexing functionality.
     *
     * @return array
     */
    protected function testIndexingFunctionality(): array
    {
        try {
            $startTime = microtime(true);

            // Try to index a test document
            $testDoc = [
                'id' => 'health_check_' . uniqid(),
                'title' => 'Health Check Test Document',
                'content' => 'This is a test document for health checking.',
                'timestamp' => microtime(true),
            ];

            $result = $this->client->index('health_check_index', $testDoc);

            $duration = microtime(true) - $startTime;

            // Clean up test document
            try {
                $this->client->delete('health_check_index', $testDoc['id']);
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }

            return [
                'working' => true,
                'response_time_ms' => round($duration * 1000, 2),
                'document_indexed' => !empty($result),
            ];
        } catch (\Throwable $e) {
            return [
                'working' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check health of a specific index.
     *
     * @param string $indexName
     * @return array
     */
    protected function checkIndexHealth(string $indexName): array
    {
        try {
            $stats = $this->client->get("/indices/{$indexName}/stats");

            return [
                'healthy' => true,
                'document_count' => $stats['documents'] ?? 0,
                'size_bytes' => $stats['size'] ?? 0,
                'last_updated' => $stats['last_updated'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate overall health score.
     *
     * @param array $checks
     * @return int
     */
    protected function calculateOverallHealth(array $checks): int
    {
        if (empty($checks)) {
            return 0;
        }

        $totalScore = 0;
        $weights = [
            'connectivity' => 0.25,
            'service_availability' => 0.30,
            'performance' => 0.20,
            'resources' => 0.15,
            'indices' => 0.05,
            'cache' => 0.05,
        ];

        foreach ($checks as $checkName => $check) {
            $weight = $weights[$checkName] ?? (1.0 / count($checks));
            $totalScore += $check['score'] * $weight;
        }

        return round($totalScore);
    }

    /**
     * Determine overall status based on health score.
     *
     * @param int $healthScore
     * @return string
     */
    protected function determineOverallStatus(int $healthScore): string
    {
        return match (true) {
            $healthScore >= 90 => 'healthy',
            $healthScore >= 70 => 'degraded',
            $healthScore >= 30 => 'unhealthy',
            default => 'critical'
        };
    }

    /**
     * Get cached health check results.
     *
     * @return array|null
     */
    public function getCachedHealthCheck(): ?array
    {
        return Cache::get('ogini:health_check');
    }

    /**
     * Get health check status summary.
     *
     * @return array
     */
    public function getHealthSummary(): array
    {
        $cached = $this->getCachedHealthCheck();

        if (!$cached) {
            return [
                'status' => 'unknown',
                'message' => 'No recent health check data available',
                'last_check' => null,
            ];
        }

        return [
            'status' => $cached['status'],
            'overall_health' => $cached['overall_health'],
            'last_check' => $cached['timestamp'],
            'last_check_ago_seconds' => round(microtime(true) - $cached['timestamp']),
        ];
    }
}
