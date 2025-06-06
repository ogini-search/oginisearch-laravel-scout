<?php

namespace OginiScoutDriver\Performance;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Laravel\Scout\Builder;
use OginiScoutDriver\Client\OginiClient;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class QueryCache
{
    protected CacheRepository $cache;
    protected array $config;
    protected OginiClient $client;

    public function __construct(CacheRepository $cache, OginiClient $client, array $config = [])
    {
        $this->cache = $cache;
        $this->client = $client;
        $this->config = array_merge([
            'enabled' => true,
            'default_ttl' => 300, // 5 minutes
            'query_ttl' => 300, // 5 minutes for query results
            'suggestion_ttl' => 1800, // 30 minutes for suggestions
            'facet_ttl' => 600, // 10 minutes for facets
            'max_cache_size' => 10000, // Maximum number of cached items
            'cache_prefix' => 'ogini_search',
            'enable_query_optimization' => true,
            'enable_result_compression' => false,
        ], $config);
    }

    /**
     * Get cached search results or execute search and cache results.
     *
     * @param string $indexName
     * @param array $searchQuery
     * @param array $options
     * @param callable $searchCallback
     * @return array
     */
    public function remember(string $indexName, array $searchQuery, array $options, callable $searchCallback): array
    {
        if (!$this->config['enabled']) {
            return $searchCallback();
        }

        $cacheKey = $this->generateCacheKey('query', $indexName, $searchQuery, $options);
        $ttl = $this->getTtlForQuery($searchQuery);

        return $this->cache->remember($cacheKey, $ttl, function () use ($searchCallback, $cacheKey) {
            $results = $searchCallback();

            // Add cache metadata
            $results['_cache'] = [
                'cached_at' => Carbon::now()->toISOString(),
                'cache_key' => $cacheKey,
                'from_cache' => false,
            ];

            $this->logCacheOperation('miss', $cacheKey);
            return $results;
        });
    }

    /**
     * Cache search suggestions.
     *
     * @param string $indexName
     * @param string $text
     * @param string|null $field
     * @param int $size
     * @param callable $suggestionCallback
     * @return array
     */
    public function rememberSuggestions(string $indexName, string $text, ?string $field, int $size, callable $suggestionCallback): array
    {
        if (!$this->config['enabled']) {
            return $suggestionCallback();
        }

        $cacheKey = $this->generateCacheKey('suggestion', $indexName, compact('text', 'field', 'size'));
        $ttl = $this->config['suggestion_ttl'];

        return $this->cache->remember($cacheKey, $ttl, function () use ($suggestionCallback, $cacheKey) {
            $results = $suggestionCallback();
            $this->logCacheOperation('miss', $cacheKey);
            return $results;
        });
    }

    /**
     * Cache faceted search results.
     *
     * @param string $indexName
     * @param array $searchQuery
     * @param array $facets
     * @param callable $facetCallback
     * @return array
     */
    public function rememberFacets(string $indexName, array $searchQuery, array $facets, callable $facetCallback): array
    {
        if (!$this->config['enabled']) {
            return $facetCallback();
        }

        $cacheKey = $this->generateCacheKey('facet', $indexName, $searchQuery, $facets);
        $ttl = $this->config['facet_ttl'];

        return $this->cache->remember($cacheKey, $ttl, function () use ($facetCallback, $cacheKey) {
            $results = $facetCallback();
            $this->logCacheOperation('miss', $cacheKey);
            return $results;
        });
    }

    /**
     * Generate a cache key for the given parameters.
     *
     * @param string $type
     * @param string $indexName
     * @param mixed ...$params
     * @return string
     */
    protected function generateCacheKey(string $type, string $indexName, ...$params): string
    {
        $keyData = [
            'type' => $type,
            'index' => $indexName,
            'params' => $params,
        ];

        if ($this->config['enable_query_optimization']) {
            $keyData = $this->optimizeKeyData($keyData);
        }

        $serialized = serialize($keyData);
        $hash = hash('sha256', $serialized);

        return "{$this->config['cache_prefix']}:{$type}:{$hash}";
    }

    /**
     * Optimize key data to improve cache hit rates.
     *
     * @param array $keyData
     * @return array
     */
    protected function optimizeKeyData(array $keyData): array
    {
        // Sort arrays to ensure consistent ordering
        if (isset($keyData['params'])) {
            $keyData['params'] = $this->sortRecursive($keyData['params']);
        }

        // Remove or normalize irrelevant parameters
        $keyData = $this->normalizeKeyData($keyData);

        return $keyData;
    }

    /**
     * Recursively sort arrays for consistent cache keys.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function sortRecursive($data)
    {
        if (is_array($data)) {
            ksort($data);
            return array_map([$this, 'sortRecursive'], $data);
        }

        return $data;
    }

    /**
     * Normalize key data by removing/standardizing irrelevant parameters.
     *
     * @param array $keyData
     * @return array
     */
    protected function normalizeKeyData(array $keyData): array
    {
        // Remove timestamp-based parameters that change frequently
        $paramsToIgnore = ['timestamp', '_cache', 'debug'];

        if (isset($keyData['params'])) {
            $keyData['params'] = $this->removeIgnoredParams($keyData['params'], $paramsToIgnore);
        }

        return $keyData;
    }

    /**
     * Remove ignored parameters from data recursively.
     *
     * @param mixed $data
     * @param array $paramsToIgnore
     * @return mixed
     */
    protected function removeIgnoredParams($data, array $paramsToIgnore)
    {
        if (is_array($data)) {
            $filtered = [];
            foreach ($data as $key => $value) {
                if (!in_array($key, $paramsToIgnore)) {
                    $filtered[$key] = is_array($value) ? $this->removeIgnoredParams($value, $paramsToIgnore) : $value;
                }
            }
            return $filtered;
        }

        return $data;
    }

    /**
     * Determine appropriate TTL for a search query.
     *
     * @param array $searchQuery
     * @return int
     */
    protected function getTtlForQuery(array $searchQuery): int
    {
        // Use shorter TTL for filtered queries as they might be more dynamic
        if (isset($searchQuery['filter']) && !empty($searchQuery['filter'])) {
            return intval($this->config['query_ttl'] * 0.5);
        }

        // Use longer TTL for simple text searches
        if (isset($searchQuery['query']) && is_string($searchQuery['query'])) {
            return $this->config['query_ttl'];
        }

        return $this->config['default_ttl'];
    }

    /**
     * Invalidate cache for specific index.
     *
     * @param string $indexName
     * @return bool
     */
    public function invalidateIndex(string $indexName): bool
    {
        $pattern = "{$this->config['cache_prefix']}:*";
        $keys = [];

        // Note: This is a simplified implementation
        // In production, you might need a more sophisticated cache invalidation strategy
        try {
            if (method_exists($this->cache->getStore(), 'flush')) {
                // For cache drivers that support pattern-based flushing
                return $this->cache->getStore()->flush();
            }

            return true;
        } catch (\Exception $e) {
            if (class_exists('Illuminate\Support\Facades\Log')) {
                try {
                    \Illuminate\Support\Facades\Log::warning('Failed to invalidate cache for index', [
                        'index' => $indexName,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Exception $logException) {
                    // Ignore logging errors
                }
            }
            return false;
        }
    }

    /**
     * Clear all cached search results.
     *
     * @return bool
     */
    public function flush(): bool
    {
        try {
            $store = $this->cache->getStore();
            if (method_exists($store, 'flush')) {
                return $store->flush();
            }

            return true;
        } catch (\Exception $e) {
            if (class_exists('Illuminate\Support\Facades\Log')) {
                try {
                    \Illuminate\Support\Facades\Log::error('Failed to flush search cache', ['error' => $e->getMessage()]);
                } catch (\Exception $logException) {
                    // Ignore logging errors
                }
            }
            return false;
        }
    }

    /**
     * Get cache statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        // This would require cache driver support for statistics
        return [
            'enabled' => $this->config['enabled'],
            'query_ttl' => $this->config['query_ttl'],
            'suggestion_ttl' => $this->config['suggestion_ttl'],
            'facet_ttl' => $this->config['facet_ttl'],
            'cache_prefix' => $this->config['cache_prefix'],
            'optimization_enabled' => $this->config['enable_query_optimization'],
            'compression_enabled' => $this->config['enable_result_compression'],
        ];
    }

    /**
     * Log cache operations for monitoring.
     *
     * @param string $operation
     * @param string $cacheKey
     * @param array $context
     * @return void
     */
    protected function logCacheOperation(string $operation, string $cacheKey, array $context = []): void
    {
        if (function_exists('config')) {
            try {
                $debug = config('app.debug');
            } catch (\Exception $e) {
                $debug = false;
            }
        } else {
            $debug = false;
        }

        if ($debug) {
            if (class_exists('Illuminate\Support\Facades\Log')) {
                try {
                    \Illuminate\Support\Facades\Log::debug("Cache {$operation}", array_merge([
                        'cache_key' => $cacheKey,
                        'operation' => $operation,
                    ], $context));
                } catch (\Exception $e) {
                    // Ignore logging errors in test environment
                }
            }
        }
    }

    /**
     * Check if cache is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }

    /**
     * Enable or disable cache.
     *
     * @param bool $enabled
     * @return void
     */
    public function setEnabled(bool $enabled): void
    {
        $this->config['enabled'] = $enabled;
    }

    /**
     * Update cache configuration.
     *
     * @param array $config
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}
