<?php

namespace OginiScoutDriver\Monitoring;

use OginiScoutDriver\Logging\OginiLogger;
use Illuminate\Support\Facades\Cache;

/**
 * Performance monitoring for OginiSearch operations.
 */
class PerformanceMonitor
{
    protected OginiLogger $logger;
    protected array $metrics;
    protected array $timers;
    protected array $counters;
    protected array $thresholds;

    /**
     * Create a new PerformanceMonitor instance.
     *
     * @param OginiLogger|null $logger
     */
    public function __construct(?OginiLogger $logger = null)
    {
        $this->logger = $logger ?? new OginiLogger();
        $this->metrics = [];
        $this->timers = [];
        $this->counters = [];
        $this->thresholds = [
            'search_duration' => 1.0, // 1 second
            'indexing_duration' => 5.0, // 5 seconds
            'connection_duration' => 2.0, // 2 seconds
            'memory_usage' => 128 * 1024 * 1024, // 128MB
        ];
    }

    /**
     * Start timing an operation.
     *
     * @param string $operation
     * @param array $context
     * @return string Timer ID
     */
    public function startTimer(string $operation, array $context = []): string
    {
        $timerId = uniqid($operation . '_');

        $this->timers[$timerId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'context' => $context,
        ];

        $this->logger->debug("Operation started: {$operation}", [
            'timer_id' => $timerId,
            'operation' => $operation,
            'context' => $context,
        ]);

        return $timerId;
    }

    /**
     * Stop timing an operation.
     *
     * @param string $timerId
     * @param array $additionalContext
     * @return array Performance metrics
     */
    public function stopTimer(string $timerId, array $additionalContext = []): array
    {
        if (!isset($this->timers[$timerId])) {
            throw new \InvalidArgumentException("Timer {$timerId} not found");
        }

        $timer = $this->timers[$timerId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'operation' => $timer['operation'],
            'duration' => $endTime - $timer['start_time'],
            'memory_used' => $endMemory - $timer['start_memory'],
            'memory_peak' => memory_get_peak_usage(true),
            'start_time' => $timer['start_time'],
            'end_time' => $endTime,
            'context' => array_merge($timer['context'], $additionalContext),
        ];

        // Store metrics
        $this->recordMetric($timer['operation'], $metrics);

        // Check thresholds
        $this->checkThresholds($metrics);

        // Log completion
        $this->logger->info("Operation completed: {$timer['operation']}", [
            'timer_id' => $timerId,
            'duration_ms' => round($metrics['duration'] * 1000, 2),
            'memory_used_mb' => round($metrics['memory_used'] / 1024 / 1024, 2),
            'performance_category' => $this->getPerformanceCategory($metrics['duration']),
            'context' => $metrics['context'],
        ]);

        unset($this->timers[$timerId]);

        return $metrics;
    }

    /**
     * Record a custom metric.
     *
     * @param string $metric
     * @param mixed $value
     * @param array $context
     * @return void
     */
    public function recordMetric(string $metric, mixed $value, array $context = []): void
    {
        $timestamp = microtime(true);

        $this->metrics[] = [
            'metric' => $metric,
            'value' => $value,
            'timestamp' => $timestamp,
            'context' => $context,
        ];

        // Keep only last 1000 metrics in memory
        if (count($this->metrics) > 1000) {
            $this->metrics = array_slice($this->metrics, -1000);
        }

        // Store in cache for persistence
        $this->storeMetricInCache($metric, $value, $timestamp, $context);

        $this->logger->logPerformance($metric, $value, '', $context);
    }

    /**
     * Increment a counter.
     *
     * @param string $counter
     * @param int $increment
     * @param array $context
     * @return int New counter value
     */
    public function incrementCounter(string $counter, int $increment = 1, array $context = []): int
    {
        if (!isset($this->counters[$counter])) {
            $this->counters[$counter] = 0;
        }

        $this->counters[$counter] += $increment;

        $this->recordMetric($counter, $this->counters[$counter], $context);

        return $this->counters[$counter];
    }

    /**
     * Get current metrics summary.
     *
     * @return array
     */
    public function getMetricsSummary(): array
    {
        $summary = [
            'total_operations' => count($this->metrics),
            'active_timers' => count($this->timers),
            'counters' => $this->counters,
            'memory' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ],
            'performance_stats' => $this->calculatePerformanceStats(),
        ];

        return $summary;
    }

    /**
     * Get metrics for a specific operation.
     *
     * @param string $operation
     * @param int $limit
     * @return array
     */
    public function getOperationMetrics(string $operation, int $limit = 100): array
    {
        $operationMetrics = array_filter($this->metrics, function ($metric) use ($operation) {
            return is_array($metric['value']) &&
                isset($metric['value']['operation']) &&
                $metric['value']['operation'] === $operation;
        });

        return array_slice($operationMetrics, -$limit);
    }

    /**
     * Get slow operations report.
     *
     * @param int $limit
     * @return array
     */
    public function getSlowOperations(int $limit = 10): array
    {
        $slowOps = [];

        foreach ($this->metrics as $metric) {
            if (is_array($metric['value']) && isset($metric['value']['duration'])) {
                $operation = $metric['value']['operation'] ?? 'unknown';
                $duration = $metric['value']['duration'];
                $threshold = $this->thresholds[$operation . '_duration'] ?? 1.0;

                if ($duration > $threshold) {
                    $slowOps[] = [
                        'operation' => $operation,
                        'duration' => $duration,
                        'threshold' => $threshold,
                        'slowness_factor' => $duration / $threshold,
                        'timestamp' => $metric['timestamp'],
                        'context' => $metric['value']['context'] ?? [],
                    ];
                }
            }
        }

        // Sort by slowness factor descending
        usort($slowOps, fn($a, $b) => $b['slowness_factor'] <=> $a['slowness_factor']);

        return array_slice($slowOps, 0, $limit);
    }

    /**
     * Clear all metrics and timers.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->metrics = [];
        $this->timers = [];
        $this->counters = [];

        $this->logger->info('Performance monitor reset');
    }

    /**
     * Set performance threshold for an operation.
     *
     * @param string $operation
     * @param float $threshold
     * @return void
     */
    public function setThreshold(string $operation, float $threshold): void
    {
        $this->thresholds[$operation] = $threshold;
    }

    /**
     * Check if metrics exceed thresholds.
     *
     * @param array $metrics
     * @return void
     */
    protected function checkThresholds(array $metrics): void
    {
        $operation = $metrics['operation'];

        // Check duration threshold
        $durationThreshold = $this->thresholds[$operation . '_duration'] ??
            $this->thresholds['search_duration'];

        if ($metrics['duration'] > $durationThreshold) {
            $this->logger->logSlowOperation(
                $operation,
                $metrics['duration'],
                $durationThreshold,
                $metrics['context']
            );
        }

        // Check memory threshold
        if ($metrics['memory_peak'] > $this->thresholds['memory_usage']) {
            $this->logger->warning('High memory usage detected', [
                'operation' => $operation,
                'memory_peak_mb' => round($metrics['memory_peak'] / 1024 / 1024, 2),
                'threshold_mb' => round($this->thresholds['memory_usage'] / 1024 / 1024, 2),
                'context' => $metrics['context'],
            ]);
        }
    }

    /**
     * Calculate performance statistics.
     *
     * @return array
     */
    protected function calculatePerformanceStats(): array
    {
        $durations = [];
        $memoryUsages = [];

        foreach ($this->metrics as $metric) {
            if (is_array($metric['value'])) {
                if (isset($metric['value']['duration'])) {
                    $durations[] = $metric['value']['duration'];
                }
                if (isset($metric['value']['memory_used'])) {
                    $memoryUsages[] = $metric['value']['memory_used'];
                }
            }
        }

        $stats = [];

        if (!empty($durations)) {
            $stats['duration'] = [
                'avg' => array_sum($durations) / count($durations),
                'min' => min($durations),
                'max' => max($durations),
                'p95' => $this->calculatePercentile($durations, 95),
                'p99' => $this->calculatePercentile($durations, 99),
            ];
        }

        if (!empty($memoryUsages)) {
            $stats['memory'] = [
                'avg_mb' => round(array_sum($memoryUsages) / count($memoryUsages) / 1024 / 1024, 2),
                'max_mb' => round(max($memoryUsages) / 1024 / 1024, 2),
            ];
        }

        return $stats;
    }

    /**
     * Calculate percentile value.
     *
     * @param array $values
     * @param int $percentile
     * @return float
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);

        if (floor($index) == $index) {
            return $values[$index];
        }

        $lower = $values[floor($index)];
        $upper = $values[ceil($index)];

        return $lower + ($upper - $lower) * ($index - floor($index));
    }

    /**
     * Store metric in cache for persistence.
     *
     * @param string $metric
     * @param mixed $value
     * @param float $timestamp
     * @param array $context
     * @return void
     */
    protected function storeMetricInCache(string $metric, mixed $value, float $timestamp, array $context): void
    {
        $cacheKey = "ogini:metrics:{$metric}:" . date('Y-m-d-H', $timestamp);
        $cachedMetrics = Cache::get($cacheKey, []);

        $cachedMetrics[] = [
            'value' => $value,
            'timestamp' => $timestamp,
            'context' => $context,
        ];

        // Keep only last 100 entries per hour
        if (count($cachedMetrics) > 100) {
            $cachedMetrics = array_slice($cachedMetrics, -100);
        }

        Cache::put($cacheKey, $cachedMetrics, 86400); // 24 hours
    }

    /**
     * Get performance category based on duration.
     *
     * @param float $duration
     * @return string
     */
    protected function getPerformanceCategory(float $duration): string
    {
        return match (true) {
            $duration < 0.1 => 'excellent',
            $duration < 0.5 => 'good',
            $duration < 1.0 => 'acceptable',
            $duration < 2.0 => 'slow',
            default => 'very_slow'
        };
    }
}
