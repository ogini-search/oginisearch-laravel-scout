<?php

namespace OginiScoutDriver\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

/**
 * Custom log formatter for OginiSearch operations.
 */
class LogFormatter implements FormatterInterface
{
    protected bool $includeStacktraces;
    protected array $sensitiveFields;

    /**
     * Create a new LogFormatter instance.
     *
     * @param bool $includeStacktraces
     * @param array $sensitiveFields
     */
    public function __construct(bool $includeStacktraces = false, array $sensitiveFields = [])
    {
        $this->includeStacktraces = $includeStacktraces;
        $this->sensitiveFields = array_merge([
            'password',
            'api_key',
            'token',
            'secret',
            'auth',
            'authorization',
        ], $sensitiveFields);
    }

    /**
     * Format a log record.
     *
     * @param LogRecord $record
     * @return string
     */
    public function format(LogRecord $record): string
    {
        $data = $this->normalize($record);
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    /**
     * Format multiple log records.
     *
     * @param array $records
     * @return string
     */
    public function formatBatch(array $records): string
    {
        $formatted = '';
        foreach ($records as $record) {
            $formatted .= $this->format($record);
        }
        return $formatted;
    }

    /**
     * Normalize log record into structured format.
     *
     * @param LogRecord $record
     * @return array
     */
    protected function normalize(LogRecord $record): array
    {
        $normalized = [
            '@timestamp' => $record->datetime->format('Y-m-d\TH:i:s.u\Z'),
            'level' => strtolower($record->level->name),
            'level_name' => $record->level->name,
            'message' => $record->message,
            'channel' => $record->channel,
        ];

        // Add context data
        if (!empty($record->context)) {
            $normalized['context'] = $this->sanitizeContext($record->context);
        }

        // Add extra data
        if (!empty($record->extra)) {
            $normalized['extra'] = $this->sanitizeContext($record->extra);
        }

        // Add service metadata
        $normalized['service'] = [
            'name' => 'ogini-scout-driver',
            'version' => config('ogini.version', '1.0.0'),
            'environment' => config('app.env', 'production'),
        ];

        // Add request metadata if available
        if (function_exists('request') && request()) {
            $request = request();
            $normalized['request'] = [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_id' => $request->header('X-Request-ID') ?? uniqid(),
            ];
        }

        // Add performance metadata
        $normalized['performance'] = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - LARAVEL_START,
        ];

        return $normalized;
    }

    /**
     * Sanitize context data by removing sensitive information.
     *
     * @param array $context
     * @return array
     */
    protected function sanitizeContext(array $context): array
    {
        return $this->recursiveSanitize($context);
    }

    /**
     * Recursively sanitize array data.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function recursiveSanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && $this->isSensitiveField($key)) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = $this->recursiveSanitize($value);
                }
            }
            return $sanitized;
        }

        if (is_object($data)) {
            if ($data instanceof \Throwable) {
                return $this->formatException($data);
            }

            if (method_exists($data, 'toArray')) {
                return $this->recursiveSanitize($data->toArray());
            }

            if (method_exists($data, '__toString')) {
                return (string) $data;
            }

            return get_class($data);
        }

        return $data;
    }

    /**
     * Check if a field name is sensitive.
     *
     * @param string $fieldName
     * @return bool
     */
    protected function isSensitiveField(string $fieldName): bool
    {
        $fieldLower = strtolower($fieldName);

        foreach ($this->sensitiveFields as $sensitiveField) {
            if (str_contains($fieldLower, strtolower($sensitiveField))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Format exception data.
     *
     * @param \Throwable $exception
     * @return array
     */
    protected function formatException(\Throwable $exception): array
    {
        $formatted = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ];

        if ($this->includeStacktraces) {
            $formatted['trace'] = $this->formatStackTrace($exception->getTrace());
        }

        if ($exception->getPrevious()) {
            $formatted['previous'] = $this->formatException($exception->getPrevious());
        }

        return $formatted;
    }

    /**
     * Format stack trace.
     *
     * @param array $trace
     * @return array
     */
    protected function formatStackTrace(array $trace): array
    {
        $formatted = [];

        foreach (array_slice($trace, 0, 10) as $frame) {
            $formatted[] = [
                'file' => $frame['file'] ?? '[internal]',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '[unknown]',
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ];
        }

        return $formatted;
    }

    /**
     * Create a formatter for development environment.
     *
     * @return static
     */
    public static function forDevelopment(): static
    {
        return new static(includeStacktraces: true);
    }

    /**
     * Create a formatter for production environment.
     *
     * @return static
     */
    public static function forProduction(): static
    {
        return new static(includeStacktraces: false);
    }

    /**
     * Create a formatter with custom sensitive fields.
     *
     * @param array $sensitiveFields
     * @return static
     */
    public static function withSensitiveFields(array $sensitiveFields): static
    {
        return new static(sensitiveFields: $sensitiveFields);
    }
}
