<?php

namespace OginiScoutDriver\Helpers;

use Illuminate\Support\Facades\Config;

class ConfigHelpers
{
    /**
     * Get the OginiSearch configuration.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    public static function getConfig(?string $key = null, $default = null)
    {
        if ($key === null) {
            return Config::get('ogini', []);
        }

        return Config::get("ogini.{$key}", $default);
    }

    /**
     * Get the base URL for OginiSearch.
     *
     * @return string
     */
    public static function getBaseUrl(): string
    {
        return self::getConfig('base_url', 'http://localhost:3000');
    }

    /**
     * Get the API key for OginiSearch.
     *
     * @return string
     */
    public static function getApiKey(): string
    {
        return self::getConfig('api_key', '');
    }

    /**
     * Check if performance optimization is enabled.
     *
     * @param string|null $feature
     * @return bool
     */
    public static function isPerformanceEnabled(?string $feature = null): bool
    {
        if ($feature === null) {
            return self::getConfig('performance.enabled', true);
        }

        return self::getConfig("performance.{$feature}.enabled", false);
    }

    /**
     * Get query optimization settings.
     *
     * @param string|null $setting
     * @return mixed
     */
    public static function getQueryOptimization(?string $setting = null)
    {
        if ($setting === null) {
            return self::getConfig('performance.query_optimization', []);
        }

        return self::getConfig("performance.query_optimization.{$setting}");
    }

    /**
     * Get cache configuration.
     *
     * @param string|null $setting
     * @return mixed
     */
    public static function getCacheConfig(?string $setting = null)
    {
        if ($setting === null) {
            return self::getConfig('performance.cache', []);
        }

        return self::getConfig("performance.cache.{$setting}");
    }

    /**
     * Get connection pool configuration.
     *
     * @param string|null $setting
     * @return mixed
     */
    public static function getConnectionPoolConfig(?string $setting = null)
    {
        if ($setting === null) {
            return self::getConfig('performance.connection_pool', []);
        }

        return self::getConfig("performance.connection_pool.{$setting}");
    }

    /**
     * Get batch processing configuration.
     *
     * @param string|null $setting
     * @return mixed
     */
    public static function getBatchConfig(?string $setting = null)
    {
        if ($setting === null) {
            return self::getConfig('performance.batch_processing', []);
        }

        return self::getConfig("performance.batch_processing.{$setting}");
    }

    /**
     * Get engine configuration.
     *
     * @param string|null $setting
     * @return mixed
     */
    public static function getEngineConfig(?string $setting = null)
    {
        if ($setting === null) {
            return self::getConfig('engine', []);
        }

        return self::getConfig("engine.{$setting}");
    }

    /**
     * Get client configuration.
     *
     * @param string|null $setting
     * @return mixed
     */
    public static function getClientConfig(?string $setting = null)
    {
        if ($setting === null) {
            return self::getConfig('client', []);
        }

        return self::getConfig("client.{$setting}");
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool
     */
    public static function isCacheEnabled(): bool
    {
        return self::getCacheConfig('enabled') === true;
    }

    /**
     * Check if connection pooling is enabled.
     *
     * @return bool
     */
    public static function isConnectionPoolEnabled(): bool
    {
        return self::getConnectionPoolConfig('enabled') === true;
    }

    /**
     * Check if batch processing is enabled.
     *
     * @return bool
     */
    public static function isBatchProcessingEnabled(): bool
    {
        return self::getBatchConfig('enabled') === true;
    }

    /**
     * Get the cache TTL for a specific operation type.
     *
     * @param string $operationType
     * @return int
     */
    public static function getCacheTtl(string $operationType): int
    {
        $ttlMap = [
            'query' => 'query_ttl',
            'result' => 'result_ttl',
            'suggestion' => 'suggestion_ttl',
        ];

        $configKey = $ttlMap[$operationType] ?? 'query_ttl';
        return self::getCacheConfig($configKey) ?? 300;
    }

    /**
     * Get timeout configurations.
     *
     * @return array
     */
    public static function getTimeouts(): array
    {
        return [
            'client_timeout' => self::getClientConfig('timeout') ?? 30,
            'connection_timeout' => self::getConnectionPoolConfig('connection_timeout') ?? 5,
            'idle_timeout' => self::getConnectionPoolConfig('idle_timeout') ?? 30,
        ];
    }

    /**
     * Get retry configurations.
     *
     * @return array
     */
    public static function getRetryConfig(): array
    {
        return [
            'client_retry_attempts' => self::getClientConfig('retry_attempts') ?? 3,
            'client_retry_delay' => self::getClientConfig('retry_delay') ?? 100,
            'batch_retry_attempts' => self::getBatchConfig('max_retry_attempts') ?? 3,
            'batch_retry_delay' => self::getBatchConfig('retry_delay') ?? 100,
        ];
    }

    /**
     * Get all performance-related configuration values.
     *
     * @return array
     */
    public static function getPerformanceConfig(): array
    {
        return [
            'query_optimization' => self::getQueryOptimization(),
            'cache' => self::getCacheConfig(),
            'connection_pool' => self::getConnectionPoolConfig(),
            'batch_processing' => self::getBatchConfig(),
            'timeouts' => self::getTimeouts(),
            'retries' => self::getRetryConfig(),
        ];
    }

    /**
     * Validate the current configuration.
     *
     * @return array Array of validation errors, empty if valid
     */
    public static function validateConfig(): array
    {
        $errors = [];

        // Required fields
        if (empty(self::getBaseUrl())) {
            $errors[] = 'Base URL is required';
        }

        if (empty(self::getApiKey())) {
            $errors[] = 'API key is required';
        }

        // URL validation
        if (!filter_var(self::getBaseUrl(), FILTER_VALIDATE_URL)) {
            $errors[] = 'Base URL is not a valid URL';
        }

        // Numeric validations
        $numericFields = [
            'client.timeout' => self::getClientConfig('timeout'),
            'client.retry_attempts' => self::getClientConfig('retry_attempts'),
            'performance.cache.query_ttl' => self::getCacheConfig('query_ttl'),
            'performance.cache.result_ttl' => self::getCacheConfig('result_ttl'),
            'performance.connection_pool.pool_size' => self::getConnectionPoolConfig('pool_size'),
        ];

        foreach ($numericFields as $field => $value) {
            if ($value !== null && (!is_numeric($value) || $value < 0)) {
                $errors[] = "Field {$field} must be a positive number";
            }
        }

        return $errors;
    }

    /**
     * Get environment-specific configuration overrides.
     *
     * @param string $environment
     * @return array
     */
    public static function getEnvironmentOverrides(string $environment): array
    {
        $overrides = [];

        switch ($environment) {
            case 'local':
            case 'development':
                $overrides = [
                    'client.timeout' => 60,
                    'performance.cache.enabled' => false,
                    'performance.connection_pool.enabled' => false,
                ];
                break;

            case 'testing':
                $overrides = [
                    'client.timeout' => 10,
                    'client.retry_attempts' => 1,
                    'performance.cache.enabled' => false,
                    'performance.connection_pool.enabled' => false,
                    'performance.batch_processing.enabled' => false,
                ];
                break;

            case 'production':
                $overrides = [
                    'client.timeout' => 30,
                    'client.retry_attempts' => 3,
                    'performance.cache.enabled' => true,
                    'performance.connection_pool.enabled' => true,
                    'performance.batch_processing.enabled' => true,
                ];
                break;
        }

        return $overrides;
    }

    /**
     * Check if the configuration is optimized for production.
     *
     * @return bool
     */
    public static function isOptimizedForProduction(): bool
    {
        return self::isCacheEnabled() &&
            self::isConnectionPoolEnabled() &&
            self::isBatchProcessingEnabled() &&
            self::getClientConfig('retry_attempts') >= 3;
    }
}
