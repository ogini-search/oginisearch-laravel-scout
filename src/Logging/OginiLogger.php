<?php

namespace OginiScoutDriver\Logging;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Centralized logging for OginiSearch operations.
 */
class OginiLogger
{
    protected LoggerInterface $logger;
    protected array $defaultContext;
    protected string $channel;

    /**
     * Create a new OginiLogger instance.
     *
     * @param LoggerInterface|null $logger
     * @param array $defaultContext
     * @param string $channel
     */
    public function __construct(
        ?LoggerInterface $logger = null,
        array $defaultContext = [],
        string $channel = 'ogini'
    ) {
        $this->logger = $logger ?? Log::channel($channel);
        $this->defaultContext = array_merge([
            'service' => 'ogini-scout-driver',
            'version' => config('ogini.version', '1.0.0'),
        ], $defaultContext);
        $this->channel = $channel;
    }

    /**
     * Log an emergency message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Log an alert message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log a notice message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log a message with a specific level.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $enrichedContext = $this->enrichContext($context);
        $this->logger->log($level, $message, $enrichedContext);
    }

    /**
     * Log a search operation.
     *
     * @param string $indexName
     * @param array $query
     * @param int $resultsCount
     * @param float $duration
     * @param array $additionalContext
     * @return void
     */
    public function logSearch(
        string $indexName,
        array $query,
        int $resultsCount,
        float $duration,
        array $additionalContext = []
    ): void {
        $this->info('Search operation completed', array_merge([
            'operation' => 'search',
            'index_name' => $indexName,
            'query' => $this->sanitizeQuery($query),
            'results_count' => $resultsCount,
            'duration_ms' => round($duration * 1000, 2),
            'performance_category' => $this->getPerformanceCategory($duration),
        ], $additionalContext));
    }

    /**
     * Log an indexing operation.
     *
     * @param string $indexName
     * @param int $documentsCount
     * @param float $duration
     * @param bool $success
     * @param array $additionalContext
     * @return void
     */
    public function logIndexing(
        string $indexName,
        int $documentsCount,
        float $duration,
        bool $success = true,
        array $additionalContext = []
    ): void {
        $level = $success ? LogLevel::INFO : LogLevel::ERROR;
        $message = $success ? 'Indexing operation completed' : 'Indexing operation failed';

        $this->log($level, $message, array_merge([
            'operation' => 'indexing',
            'index_name' => $indexName,
            'documents_count' => $documentsCount,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $success,
            'throughput_docs_per_sec' => $duration > 0 ? round($documentsCount / $duration, 2) : 0,
        ], $additionalContext));
    }

    /**
     * Log a deletion operation.
     *
     * @param string $indexName
     * @param string|array $documentIds
     * @param bool $success
     * @param array $additionalContext
     * @return void
     */
    public function logDeletion(
        string $indexName,
        string|array $documentIds,
        bool $success = true,
        array $additionalContext = []
    ): void {
        $level = $success ? LogLevel::INFO : LogLevel::ERROR;
        $message = $success ? 'Document deletion completed' : 'Document deletion failed';

        $idsArray = is_array($documentIds) ? $documentIds : [$documentIds];

        $this->log($level, $message, array_merge([
            'operation' => 'deletion',
            'index_name' => $indexName,
            'document_ids' => $idsArray,
            'documents_count' => count($idsArray),
            'success' => $success,
        ], $additionalContext));
    }

    /**
     * Log a connection operation.
     *
     * @param string $endpoint
     * @param bool $success
     * @param float $duration
     * @param array $additionalContext
     * @return void
     */
    public function logConnection(
        string $endpoint,
        bool $success,
        float $duration,
        array $additionalContext = []
    ): void {
        $level = $success ? LogLevel::DEBUG : LogLevel::WARNING;
        $message = $success ? 'Connection established' : 'Connection failed';

        $this->log($level, $message, array_merge([
            'operation' => 'connection',
            'endpoint' => $endpoint,
            'success' => $success,
            'duration_ms' => round($duration * 1000, 2),
        ], $additionalContext));
    }

    /**
     * Log a performance metric.
     *
     * @param string $metric
     * @param mixed $value
     * @param string $unit
     * @param array $additionalContext
     * @return void
     */
    public function logPerformance(
        string $metric,
        mixed $value,
        string $unit = '',
        array $additionalContext = []
    ): void {
        $this->info('Performance metric recorded', array_merge([
            'category' => 'performance',
            'metric' => $metric,
            'value' => $value,
            'unit' => $unit,
            'timestamp' => microtime(true),
        ], $additionalContext));
    }

    /**
     * Log an exception.
     *
     * @param \Throwable $exception
     * @param array $additionalContext
     * @return void
     */
    public function logException(\Throwable $exception, array $additionalContext = []): void
    {
        $this->error('Exception occurred', array_merge([
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->sanitizeTrace($exception->getTraceAsString()),
        ], $additionalContext));
    }

    /**
     * Log a slow operation warning.
     *
     * @param string $operation
     * @param float $duration
     * @param float $threshold
     * @param array $additionalContext
     * @return void
     */
    public function logSlowOperation(
        string $operation,
        float $duration,
        float $threshold,
        array $additionalContext = []
    ): void {
        $this->warning('Slow operation detected', array_merge([
            'category' => 'performance',
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'threshold_ms' => round($threshold * 1000, 2),
            'slowness_factor' => round($duration / $threshold, 2),
        ], $additionalContext));
    }

    /**
     * Enrich context with default values and metadata.
     *
     * @param array $context
     * @return array
     */
    protected function enrichContext(array $context): array
    {
        return array_merge(
            $this->defaultContext,
            [
                'timestamp' => microtime(true),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
            ],
            $context
        );
    }

    /**
     * Sanitize query data for logging.
     *
     * @param array $query
     * @return array
     */
    protected function sanitizeQuery(array $query): array
    {
        // Remove or truncate large values to prevent log bloat
        $sanitized = [];
        foreach ($query as $key => $value) {
            if (is_string($value) && strlen($value) > 500) {
                $sanitized[$key] = substr($value, 0, 500) . '... [truncated]';
            } elseif (is_array($value) && count($value) > 20) {
                $sanitized[$key] = array_slice($value, 0, 20) + ['...' => '[truncated]'];
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Sanitize stack trace for logging.
     *
     * @param string $trace
     * @return string
     */
    protected function sanitizeTrace(string $trace): string
    {
        // Limit trace length to prevent log bloat
        $lines = explode("\n", $trace);
        if (count($lines) > 10) {
            $lines = array_slice($lines, 0, 10);
            $lines[] = '... [truncated]';
        }
        return implode("\n", $lines);
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

    /**
     * Create a new logger instance with additional context.
     *
     * @param array $context
     * @return static
     */
    public function withContext(array $context): static
    {
        return new static(
            $this->logger,
            array_merge($this->defaultContext, $context),
            $this->channel
        );
    }

    /**
     * Get the underlying logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
