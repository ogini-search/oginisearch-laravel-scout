<?php

namespace OginiScoutDriver\Engine;

use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Exceptions\OginiException;
use OginiScoutDriver\Search\Facets\FacetDefinition;
use OginiScoutDriver\Search\Facets\FacetResult;
use OginiScoutDriver\Search\Facets\FacetCollection;
use OginiScoutDriver\Search\Filters\FilterBuilder;
use OginiScoutDriver\Search\Sorting\SortBuilder;
use OginiScoutDriver\Search\Highlighting\HighlightBuilder;
use OginiScoutDriver\Performance\BatchProcessor;
use OginiScoutDriver\Performance\QueryCache;
use OginiScoutDriver\Performance\ConnectionPool;
use OginiScoutDriver\Performance\QueryOptimizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\LazyCollection;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class OginiEngine extends Engine
{
    protected OginiClient $client;
    protected array $config;
    protected ?BatchProcessor $batchProcessor = null;
    protected ?QueryCache $queryCache = null;
    protected ?ConnectionPool $connectionPool = null;
    protected ?QueryOptimizer $queryOptimizer = null;

    /**
     * Create a new OginiEngine instance.
     *
     * @param OginiClient $client
     * @param array $config
     * @param CacheRepository|null $cache
     */
    public function __construct(OginiClient $client, array $config = [], ?CacheRepository $cache = null)
    {
        $this->client = $client;
        $this->config = $config;

        $this->initializePerformanceComponents($cache);
    }

    /**
     * Initialize performance optimization components.
     *
     * @param CacheRepository|null $cache
     * @return void
     */
    protected function initializePerformanceComponents(?CacheRepository $cache = null): void
    {
        $performanceConfig = $this->config['performance'] ?? [];

        // Initialize batch processor
        if (isset($performanceConfig['batch'])) {
            $this->batchProcessor = new BatchProcessor($this->client, $performanceConfig['batch']);
        }

        // Initialize query cache
        if ($cache && isset($performanceConfig['cache']) && $performanceConfig['cache']['enabled']) {
            $this->queryCache = new QueryCache($cache, $this->client, $performanceConfig['cache']);
        }

        // Initialize connection pool
        if (isset($performanceConfig['connection_pool']) && $performanceConfig['connection_pool']['enabled']) {
            $poolConfig = array_merge($performanceConfig['connection_pool'], [
                'base_url' => $this->config['base_url'] ?? 'http://localhost:3000',
                'api_key' => $this->config['api_key'] ?? '',
            ]);
            $this->connectionPool = new ConnectionPool($poolConfig);
        }

        // Initialize query optimizer
        if (isset($performanceConfig['query_optimization']) && $performanceConfig['query_optimization']['enabled']) {
            $this->queryOptimizer = new QueryOptimizer($performanceConfig['query_optimization']);
        }
    }

    /**
     * Update the given model in the index.
     *
     * @param Collection $models
     * @return void
     * @throws OginiException
     */
    public function update($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $indexName = $models->first()->searchableAs();

        // Use batch processor if available for better performance
        if ($this->batchProcessor) {
            $result = $this->batchProcessor->bulkIndex($indexName, $models);

            if (!empty($result['errors'])) {
                $this->logError('Batch indexing completed with errors', [
                    'processed' => $result['processed'],
                    'total' => $result['total'],
                    'success_rate' => $result['success_rate'],
                    'error_count' => count($result['errors']),
                ]);
            }

            return;
        }

        // Handle individual documents with proper update/create logic
        foreach ($models as $model) {
            $documentId = (string)$model->getScoutKey();
            $documentData = $model->toSearchableArray();

            try {
                // Try to update first (for existing documents)
                $this->client->updateDocument($indexName, $documentId, $documentData);
            } catch (OginiException $updateException) {
                // If update fails, try to index as new document
                try {
                    $this->client->indexDocument($indexName, $documentId, $documentData);
                } catch (OginiException $indexException) {
                    $this->logError('Failed to index/update document', [
                        'index' => $indexName,
                        'id' => $documentId,
                        'update_error' => $updateException->getMessage(),
                        'index_error' => $indexException->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Remove the given model from the index.
     *
     * @param Collection $models
     * @return void
     * @throws OginiException
     */
    public function delete($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        $indexName = $models->first()->searchableAs();

        foreach ($models as $model) {
            try {
                $this->client->deleteDocument($indexName, $model->getScoutKey());
            } catch (OginiException $e) {
                // Log error but continue with other deletions
                $this->logError('Failed to delete document from Ogini index', [
                    'index' => $indexName,
                    'id' => $model->getScoutKey(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @return mixed
     * @throws OginiException
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, [
            'size' => $builder->limit,
            'from' => 0,
        ]);
    }

    /**
     * Perform the given search on the engine for pagination.
     *
     * @param Builder $builder
     * @param int $perPage
     * @param int $page
     * @return mixed
     * @throws OginiException
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'size' => $perPage,
            'from' => ($page - 1) * $perPage,
        ]);
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param mixed $results
     * @param Model $model
     * @return Collection
     */
    public function map(Builder $builder, $results, $model): Collection
    {
        if (!isset($results['data']['hits']) || empty($results['data']['hits'])) {
            return $model->newCollection();
        }

        $objectIds = collect($results['data']['hits'])->pluck('id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->getScoutModelsByIds(
            $builder,
            $objectIds
        )->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param Builder $builder
     * @param mixed $results
     * @param Model $model
     * @return LazyCollection
     */
    public function lazyMap(Builder $builder, $results, $model): LazyCollection
    {
        if (!isset($results['data']['hits']) || empty($results['data']['hits'])) {
            return LazyCollection::make($model->newCollection());
        }

        $objectIds = collect($results['data']['hits'])->pluck('id')->values()->all();
        $objectIdPositions = array_flip($objectIds);

        return $model->queryScoutModelsByIds(
            $builder,
            $objectIds
        )->cursor()->filter(function ($model) use ($objectIds) {
            return in_array($model->getScoutKey(), $objectIds);
        })->sortBy(function ($model) use ($objectIdPositions) {
            return $objectIdPositions[$model->getScoutKey()];
        })->values();
    }

    /**
     * Create a search index.
     *
     * @param string $name
     * @param array $options
     * @return mixed
     * @throws OginiException
     */
    public function createIndex($name, array $options = [])
    {
        return $this->client->createIndex($name, $options);
    }

    /**
     * Delete a search index.
     *
     * @param string $name
     * @return mixed
     * @throws OginiException
     */
    public function deleteIndex($name)
    {
        return $this->client->deleteIndex($name);
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
     * @return void
     * @throws OginiException
     */
    public function flush($model): void
    {
        $indexName = $model->searchableAs();

        try {
            // Try to delete all documents using delete by query
            $this->client->deleteByQuery($indexName, [
                'match_all' => []
            ]);
        } catch (OginiException $e) {
            // If delete by query is not available, delete the entire index and recreate it
            try {
                $this->client->deleteIndex($indexName);

                // If the model has index configuration, recreate with that
                if (method_exists($model, 'getOginiIndexConfiguration')) {
                    $this->client->createIndex($indexName, $model->getOginiIndexConfiguration());
                } else {
                    $this->client->createIndex($indexName);
                }
            } catch (OginiException $recreateException) {
                // Log the error but don't throw as the flush operation should be resilient
                $this->logError('Failed to flush Ogini index', [
                    'index' => $indexName,
                    'error' => $recreateException->getMessage(),
                ]);
            }
        }
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     * @return int
     */
    public function getTotalCount($results): int
    {
        return $results['data']['total'] ?? 0;
    }

    /**
     * Get the results of the query as a collection of primary keys.
     *
     * @param Builder $builder
     * @return BaseCollection
     * @throws OginiException
     */
    public function keys(Builder $builder): BaseCollection
    {
        $results = $this->search($builder);

        if (!isset($results['data']['hits'])) {
            return collect();
        }

        return collect($results['data']['hits'])->pluck('id');
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     * @return BaseCollection
     */
    public function mapIds($results): BaseCollection
    {
        if (!isset($results['data']['hits'])) {
            return collect();
        }

        return collect($results['data']['hits'])->pluck('id')->values();
    }

    /**
     * Perform the actual search query.
     *
     * @param Builder $builder
     * @param array $options
     * @return array
     * @throws OginiException
     */
    protected function performSearch(Builder $builder, array $options = []): array
    {
        $indexName = $builder->model->searchableAs();
        $searchQuery = $this->buildSearchQuery($builder);

        // Use query cache if available
        if ($this->queryCache && $this->queryCache->isEnabled()) {
            return $this->queryCache->remember(
                $indexName,
                $searchQuery,
                $options,
                function () use ($indexName, $searchQuery, $options, $builder) {
                    return $this->client->search(
                        $indexName,
                        $builder->query ?: '',
                        array_merge($searchQuery, $options)
                    );
                }
            );
        }

        return $this->client->search(
            $indexName,
            $builder->query ?: '',
            array_merge($searchQuery, $options)
        );
    }

    /**
     * Build the search query from the Scout builder.
     *
     * @param Builder $builder
     * @return array
     */
    protected function buildSearchQuery(Builder $builder): array
    {
        $query = [];

        // Handle the main search query
        if (!empty($builder->query)) {
            if (method_exists($builder->model, 'getSearchFields')) {
                // Multi-field search if model defines search fields
                $query['query'] = [
                    'match' => [
                        'value' => $builder->query,
                    ],
                ];
                $query['fields'] = $builder->model->getSearchFields();
            } else {
                // Default to searching all text fields
                $query['query'] = [
                    'match' => [
                        'value' => $builder->query,
                    ],
                ];
            }
        } else {
            // Match all documents if no query
            $query['query'] = [
                'match_all' => [],
            ];
        }

        // Handle where clauses (filters)
        if (!empty($builder->wheres)) {
            $filters = [];

            foreach ($builder->wheres as $field => $value) {
                $filters[] = [
                    'term' => [
                        'field' => $field,
                        'value' => $value,
                    ],
                ];
            }

            if (count($filters) === 1) {
                $query['filter'] = $filters[0];
            } else {
                $query['filter'] = [
                    'bool' => [
                        'must' => $filters,
                    ],
                ];
            }
        }

        // Handle sorting
        if (!empty($builder->orders)) {
            $sortString = '';
            foreach ($builder->orders as $order) {
                if (!empty($sortString)) {
                    $sortString .= ',';
                }
                $sortString .= $order['column'] . ':' . $order['direction'];
            }
            $query['sort'] = $sortString;
        }

        // Handle additional options
        if (isset($builder->callback)) {
            $query = call_user_func($builder->callback, $query, $builder);
        }

        // Apply query optimization if available
        if ($this->queryOptimizer) {
            $query = $this->queryOptimizer->optimizeQuery($query);
        }

        return $query;
    }

    /**
     * Log an error message safely.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        if (function_exists('logger')) {
            try {
                logger()->warning($message, $context);
            } catch (\Exception $e) {
                // Ignore logging errors in test environment
            }
        }
    }

    /**
     * Get the OginiClient instance.
     *
     * @return OginiClient
     */
    public function getClient(): OginiClient
    {
        return $this->client;
    }

    /**
     * Perform a search with facets.
     *
     * @param Builder $builder
     * @param array $facets Array of FacetDefinition objects or facet configurations
     * @param array $options
     * @return array
     * @throws OginiException
     */
    public function searchWithFacets(Builder $builder, array $facets, array $options = []): array
    {
        $indexName = $builder->model->searchableAs();
        $searchQuery = $this->buildSearchQuery($builder);

        // Add facets to the query
        $searchQuery['facets'] = $this->buildFacetsQuery($facets);

        return $this->client->search(
            $indexName,
            $builder->query ?: '',
            array_merge($searchQuery, [
                'size' => $options['size'] ?? $builder->limit,
                'from' => $options['from'] ?? 0
            ])
        );
    }

    /**
     * Build facets query from facet definitions.
     *
     * @param array $facets
     * @return array
     */
    protected function buildFacetsQuery(array $facets): array
    {
        $facetsQuery = [];

        foreach ($facets as $name => $facet) {
            if ($facet instanceof FacetDefinition) {
                $facetsQuery[$name] = $facet->toArray();
            } elseif (is_array($facet)) {
                $facetsQuery[$name] = $facet;
            }
        }

        return $facetsQuery;
    }

    /**
     * Process facet results from search response.
     *
     * @param array $results
     * @return FacetCollection
     */
    public function processFacetResults(array $results): FacetCollection
    {
        $facetsData = $results['facets'] ?? [];
        return FacetCollection::fromResponse($facetsData);
    }

    /**
     * Get suggestions for autocomplete.
     *
     * @param string $indexName
     * @param string $text
     * @param string|null $field
     * @param int $size
     * @return array
     * @throws OginiException
     */
    public function getSuggestions(string $indexName, string $text, ?string $field = null, int $size = 5): array
    {
        return $this->client->suggest($indexName, $text, $field, $size);
    }

    /**
     * Perform an advanced search with filters, sorting, and highlighting.
     *
     * @param Builder $builder
     * @param FilterBuilder|null $filters
     * @param SortBuilder|null $sorting
     * @param HighlightBuilder|null $highlighting
     * @param array $facets
     * @param array $options
     * @return array
     * @throws OginiException
     */
    public function advancedSearch(
        Builder $builder,
        ?FilterBuilder $filters = null,
        ?SortBuilder $sorting = null,
        ?HighlightBuilder $highlighting = null,
        array $facets = [],
        array $options = []
    ): array {
        $indexName = $builder->model->searchableAs();
        $searchQuery = $this->buildAdvancedSearchQuery($builder, $filters, $sorting, $highlighting, $facets);

        return $this->client->search(
            $indexName,
            $builder->query ?: '',
            array_merge($searchQuery, [
                'size' => $options['size'] ?? $builder->limit,
                'from' => $options['from'] ?? 0
            ])
        );
    }

    /**
     * Build an advanced search query.
     *
     * @param Builder $builder
     * @param FilterBuilder|null $filters
     * @param SortBuilder|null $sorting
     * @param HighlightBuilder|null $highlighting
     * @param array $facets
     * @return array
     */
    protected function buildAdvancedSearchQuery(
        Builder $builder,
        ?FilterBuilder $filters = null,
        ?SortBuilder $sorting = null,
        ?HighlightBuilder $highlighting = null,
        array $facets = []
    ): array {
        // Start with the basic search query
        $query = $this->buildSearchQuery($builder);

        // Add advanced filters
        if ($filters && $filters->hasFilters()) {
            $filterQuery = $filters->build();

            if (isset($query['filter'])) {
                // Combine with existing filters
                $query['filter'] = [
                    'type' => 'bool',
                    'bool' => [
                        'must' => [$query['filter'], $filterQuery],
                    ],
                ];
            } else {
                $query['filter'] = $filterQuery;
            }
        }

        // Add advanced sorting
        if ($sorting && $sorting->hasSorts()) {
            $sortQuery = $sorting->build();

            // Check if we can use simple string format
            if ($sorting->count() === count(array_filter($sortQuery, function ($sort) {
                return isset($sort['field']) && !isset($sort['type']);
            }))) {
                $query['sort'] = $sorting->buildString();
            } else {
                $query['advanced_sort'] = $sortQuery;
            }
        }

        // Add highlighting
        if ($highlighting && $highlighting->hasFields()) {
            $query['highlight'] = $highlighting->build();
        }

        // Add facets
        if (!empty($facets)) {
            $query['facets'] = $this->buildFacetsQuery($facets);
        }

        return $query;
    }

    /**
     * Search with advanced filters.
     *
     * @param Builder $builder
     * @param callable $filterCallback
     * @param array $options
     * @return array
     * @throws OginiException
     */
    public function searchWithFilters(Builder $builder, callable $filterCallback, array $options = []): array
    {
        $filters = new FilterBuilder();
        $filterCallback($filters);

        return $this->advancedSearch($builder, $filters, null, null, [], $options);
    }

    /**
     * Search with advanced sorting.
     *
     * @param Builder $builder
     * @param callable $sortCallback
     * @param array $options
     * @return array
     * @throws OginiException
     */
    public function searchWithSorting(Builder $builder, callable $sortCallback, array $options = []): array
    {
        $sorting = new SortBuilder();
        $sortCallback($sorting);

        return $this->advancedSearch($builder, null, $sorting, null, [], $options);
    }

    /**
     * Search with advanced highlighting.
     *
     * @param Builder $builder
     * @param callable $highlightCallback
     * @param array $options
     * @return array
     * @throws OginiException
     */
    public function searchWithAdvancedHighlighting(Builder $builder, callable $highlightCallback, array $options = []): array
    {
        $highlighting = new HighlightBuilder();
        $highlightCallback($highlighting);

        return $this->advancedSearch($builder, null, null, $highlighting, [], $options);
    }

    /**
     * Perform a health check on the search engine.
     *
     * @param bool $detailed Whether to perform detailed health checks
     * @return array Health check results
     */
    public function healthCheck(bool $detailed = false): array
    {
        return $this->client->healthCheck($detailed);
    }

    /**
     * Quick health check to determine if the search engine is accessible.
     *
     * @return bool True if the engine is healthy and accessible
     */
    public function isHealthy(): bool
    {
        return $this->client->isHealthy();
    }
}
