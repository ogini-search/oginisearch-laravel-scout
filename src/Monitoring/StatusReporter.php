<?php

namespace OginiScoutDriver\Monitoring;

use OginiScoutDriver\Logging\OginiLogger;
use OginiScoutDriver\Exceptions\ErrorCodes;
use Illuminate\Support\Facades\Cache;

/**
 * Status reporting for OginiSearch operations.
 */
class StatusReporter
{
    protected HealthChecker $healthChecker;
    protected PerformanceMonitor $monitor;
    protected OginiLogger $logger;

    /**
     * Create a new StatusReporter instance.
     *
     * @param HealthChecker $healthChecker
     * @param PerformanceMonitor $monitor
     * @param OginiLogger|null $logger
     */
    public function __construct(
        HealthChecker $healthChecker,
        PerformanceMonitor $monitor,
        ?OginiLogger $logger = null
    ) {
        $this->healthChecker = $healthChecker;
        $this->monitor = $monitor;
        $this->logger = $logger ?? new OginiLogger();
    }

    /**
     * Generate comprehensive status report.
     *
     * @return array
     */
    public function generateStatusReport(): array
    {
        $timerId = $this->monitor->startTimer('status_report_generation');

        try {
            $report = [
                'timestamp' => microtime(true),
                'service' => [
                    'name' => 'OginiSearch Scout Driver',
                    'version' => config('ogini.version', '1.0.0'),
                    'environment' => config('app.env', 'production'),
                    'uptime' => $this->getServiceUptime(),
                ],
                'health' => $this->getHealthStatus(),
                'performance' => $this->getPerformanceStatus(),
                'errors' => $this->getErrorStatus(),
                'statistics' => $this->getStatistics(),
                'configuration' => $this->getConfigurationStatus(),
            ];

            $this->monitor->stopTimer($timerId, ['status' => 'success']);

            // Cache the report for quick access
            Cache::put('ogini:status_report', $report, 300); // 5 minutes

            return $report;
        } catch (\Throwable $e) {
            $this->logger->logException($e, ['operation' => 'status_report_generation']);
            $this->monitor->stopTimer($timerId, ['status' => 'error']);

            return [
                'timestamp' => microtime(true),
                'error' => $e->getMessage(),
                'status' => 'error',
            ];
        }
    }

    /**
     * Get health status information.
     *
     * @return array
     */
    protected function getHealthStatus(): array
    {
        $healthCheck = $this->healthChecker->getCachedHealthCheck();

        if (!$healthCheck) {
            $healthCheck = $this->healthChecker->performHealthCheck();
        }

        return [
            'overall_status' => $healthCheck['status'],
            'overall_health_score' => $healthCheck['overall_health'],
            'last_check' => $healthCheck['timestamp'],
            'checks' => $healthCheck['checks'],
            'summary' => $this->generateHealthSummary($healthCheck),
        ];
    }

    /**
     * Get performance status information.
     *
     * @return array
     */
    protected function getPerformanceStatus(): array
    {
        $metrics = $this->monitor->getMetricsSummary();
        $slowOps = $this->monitor->getSlowOperations(10);

        return [
            'memory_usage' => $metrics['memory'],
            'active_operations' => $metrics['active_timers'],
            'total_operations' => $metrics['total_operations'],
            'performance_stats' => $metrics['performance_stats'] ?? [],
            'slow_operations' => array_slice($slowOps, 0, 5), // Top 5 slowest
            'thresholds_exceeded' => count($slowOps),
            'counters' => $metrics['counters'],
        ];
    }

    /**
     * Get error status information.
     *
     * @return array
     */
    protected function getErrorStatus(): array
    {
        $recentErrors = $this->getRecentErrors();
        $errorCounts = $this->getErrorCounts();

        return [
            'recent_errors' => $recentErrors,
            'error_counts' => $errorCounts,
            'error_rate' => $this->calculateErrorRate(),
            'most_common_errors' => $this->getMostCommonErrors($recentErrors),
        ];
    }

    /**
     * Get service statistics.
     *
     * @return array
     */
    protected function getStatistics(): array
    {
        $cacheKeys = [
            'ogini:stats:searches_today',
            'ogini:stats:documents_indexed_today',
            'ogini:stats:errors_today',
            'ogini:stats:cache_hits_today',
            'ogini:stats:cache_misses_today',
        ];

        $stats = [];
        foreach ($cacheKeys as $key) {
            $statName = str_replace(['ogini:stats:', '_today'], '', $key);
            $stats[$statName] = Cache::get($key, 0);
        }

        // Calculate additional metrics
        $totalCacheOps = $stats['cache_hits'] + $stats['cache_misses'];
        $stats['cache_hit_rate'] = $totalCacheOps > 0 ?
            round(($stats['cache_hits'] / $totalCacheOps) * 100, 2) : 0;

        $stats['error_rate'] = $stats['searches'] > 0 ?
            round(($stats['errors'] / $stats['searches']) * 100, 2) : 0;

        return [
            'today' => $stats,
            'uptime_stats' => $this->getUptimeStats(),
            'throughput' => $this->calculateThroughput(),
        ];
    }

    /**
     * Get configuration status.
     *
     * @return array
     */
    protected function getConfigurationStatus(): array
    {
        return [
            'connection' => [
                'host' => config('ogini.host', 'localhost'),
                'port' => config('ogini.port', 3000),
                'secure' => config('ogini.secure', false),
                'timeout' => config('ogini.timeout', 30),
            ],
            'performance' => [
                'cache_enabled' => config('ogini.cache.enabled', true),
                'cache_ttl' => config('ogini.cache.ttl', 3600),
                'batch_size' => config('ogini.batch_size', 100),
                'connection_pool_size' => config('ogini.connection_pool.size', 10),
            ],
            'logging' => [
                'level' => config('logging.channels.ogini.level', 'info'),
                'enabled' => config('logging.channels.ogini.enabled', true),
            ],
        ];
    }

    /**
     * Generate health summary.
     *
     * @param array $healthCheck
     * @return array
     */
    protected function generateHealthSummary(array $healthCheck): array
    {
        $issues = [];
        $recommendations = [];

        foreach ($healthCheck['checks'] as $checkName => $check) {
            if ($check['status'] !== 'healthy') {
                $issues[] = [
                    'check' => $checkName,
                    'status' => $check['status'],
                    'score' => $check['score'],
                    'details' => $check['details']['error'] ?? $check['details']['warning'] ?? 'Unknown issue',
                ];

                // Add recommendations based on the issue
                $recommendations = array_merge($recommendations, $this->getRecommendations($checkName, $check));
            }
        }

        return [
            'total_checks' => count($healthCheck['checks']),
            'healthy_checks' => count(array_filter($healthCheck['checks'], fn($c) => $c['status'] === 'healthy')),
            'issues_found' => count($issues),
            'issues' => $issues,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Get recommendations based on health check issues.
     *
     * @param string $checkName
     * @param array $check
     * @return array
     */
    protected function getRecommendations(string $checkName, array $check): array
    {
        $recommendations = [];

        switch ($checkName) {
            case 'connectivity':
                if ($check['status'] === 'unhealthy') {
                    $recommendations[] = 'Check OginiSearch server status and network connectivity';
                    $recommendations[] = 'Verify configuration settings (host, port, SSL)';
                } elseif ($check['status'] === 'degraded') {
                    $recommendations[] = 'Monitor network latency and server performance';
                    $recommendations[] = 'Consider using a closer server or improving network connection';
                }
                break;

            case 'performance':
                if (isset($check['details']['warnings'])) {
                    foreach ($check['details']['warnings'] as $warning) {
                        if (str_contains($warning, 'memory')) {
                            $recommendations[] = 'Consider increasing PHP memory limit or optimizing queries';
                            $recommendations[] = 'Review and optimize batch sizes for bulk operations';
                        }
                        if (str_contains($warning, 'slow')) {
                            $recommendations[] = 'Optimize slow queries and add proper indexing';
                            $recommendations[] = 'Consider implementing query caching';
                        }
                    }
                }
                break;

            case 'resources':
                if (isset($check['details']['warnings'])) {
                    foreach ($check['details']['warnings'] as $warning) {
                        if (str_contains($warning, 'disk')) {
                            $recommendations[] = 'Free up disk space or increase storage capacity';
                            $recommendations[] = 'Implement log rotation and cleanup policies';
                        }
                    }
                }
                break;

            case 'cache':
                if ($check['status'] !== 'healthy') {
                    $recommendations[] = 'Check cache system configuration and connectivity';
                    $recommendations[] = 'Verify Redis/Memcached service is running properly';
                }
                break;
        }

        return $recommendations;
    }

    /**
     * Get recent errors from logs.
     *
     * @param int $hours
     * @return array
     */
    protected function getRecentErrors(int $hours = 24): array
    {
        $errors = [];
        $cacheKey = 'ogini:recent_errors:' . date('Y-m-d');
        $cachedErrors = Cache::get($cacheKey, []);

        $cutoffTime = microtime(true) - ($hours * 3600);

        foreach ($cachedErrors as $error) {
            if ($error['timestamp'] >= $cutoffTime) {
                $errors[] = $error;
            }
        }

        // Sort by timestamp descending
        usort($errors, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($errors, 0, 20); // Last 20 errors
    }

    /**
     * Get error counts by category.
     *
     * @return array
     */
    protected function getErrorCounts(): array
    {
        $counts = [];
        $recentErrors = $this->getRecentErrors();

        foreach ($recentErrors as $error) {
            $category = $error['category'] ?? 'unknown';
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Calculate error rate.
     *
     * @return float
     */
    protected function calculateErrorRate(): float
    {
        $totalOperations = Cache::get('ogini:stats:total_operations_today', 0);
        $totalErrors = Cache::get('ogini:stats:errors_today', 0);

        return $totalOperations > 0 ? round(($totalErrors / $totalOperations) * 100, 2) : 0.0;
    }

    /**
     * Get most common errors.
     *
     * @param array $recentErrors
     * @return array
     */
    protected function getMostCommonErrors(array $recentErrors): array
    {
        $errorCounts = [];

        foreach ($recentErrors as $error) {
            $errorCode = $error['error_code'] ?? 'UNKNOWN';
            $errorCounts[$errorCode] = ($errorCounts[$errorCode] ?? 0) + 1;
        }

        arsort($errorCounts);

        $commonErrors = [];
        foreach (array_slice($errorCounts, 0, 5, true) as $errorCode => $count) {
            $commonErrors[] = [
                'error_code' => $errorCode,
                'count' => $count,
                'description' => ErrorCodes::getDescription($errorCode),
                'category' => ErrorCodes::getCategory($errorCode),
            ];
        }

        return $commonErrors;
    }

    /**
     * Get service uptime.
     *
     * @return array
     */
    protected function getServiceUptime(): array
    {
        $startTime = Cache::rememberForever('ogini:service_start_time', fn() => microtime(true));
        $uptime = microtime(true) - $startTime;

        return [
            'start_time' => $startTime,
            'uptime_seconds' => round($uptime),
            'uptime_human' => $this->formatDuration($uptime),
        ];
    }

    /**
     * Get uptime statistics.
     *
     * @return array
     */
    protected function getUptimeStats(): array
    {
        return [
            'availability_24h' => Cache::get('ogini:stats:availability_24h', 100.0),
            'availability_7d' => Cache::get('ogini:stats:availability_7d', 100.0),
            'availability_30d' => Cache::get('ogini:stats:availability_30d', 100.0),
            'downtime_incidents_24h' => Cache::get('ogini:stats:downtime_incidents_24h', 0),
        ];
    }

    /**
     * Calculate throughput metrics.
     *
     * @return array
     */
    protected function calculateThroughput(): array
    {
        $searchesToday = Cache::get('ogini:stats:searches_today', 0);
        $indexingToday = Cache::get('ogini:stats:documents_indexed_today', 0);

        $secondsInDay = 86400;
        $currentSecond = time() % $secondsInDay;
        $hoursToday = $currentSecond / 3600;

        return [
            'searches_per_hour' => $hoursToday > 0 ? round($searchesToday / $hoursToday, 2) : 0,
            'indexing_per_hour' => $hoursToday > 0 ? round($indexingToday / $hoursToday, 2) : 0,
            'total_operations_per_hour' => $hoursToday > 0 ? round(($searchesToday + $indexingToday) / $hoursToday, 2) : 0,
        ];
    }

    /**
     * Format duration in human-readable format.
     *
     * @param float $seconds
     * @return string
     */
    protected function formatDuration(float $seconds): string
    {
        $units = [
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1,
        ];

        $parts = [];
        foreach ($units as $unit => $value) {
            if ($seconds >= $value) {
                $count = floor($seconds / $value);
                $parts[] = $count . ' ' . $unit . ($count > 1 ? 's' : '');
                $seconds %= $value;
            }
        }

        return empty($parts) ? '0 seconds' : implode(', ', $parts);
    }

    /**
     * Get cached status report.
     *
     * @return array|null
     */
    public function getCachedStatusReport(): ?array
    {
        return Cache::get('ogini:status_report');
    }
}
