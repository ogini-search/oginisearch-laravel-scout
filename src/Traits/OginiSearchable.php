<?php

namespace OginiScoutDriver\Traits;

use Laravel\Scout\Searchable;
use Laravel\Scout\Builder;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\Search\Facets\FacetDefinition;
use OginiScoutDriver\Search\Facets\FacetResult;
use OginiScoutDriver\Search\Facets\FacetCollection;
use OginiScoutDriver\Search\Filters\FilterBuilder;
use OginiScoutDriver\Search\Sorting\SortBuilder;
use OginiScoutDriver\Search\Highlighting\HighlightBuilder;

trait OginiSearchable
{
    use Searchable;

    /**
     * Stored highlights from search results.
     *
     * @var array|null
     */
    protected ?array $searchHighlights = null;

    /**
     * Get the search highlights for this model.
     *
     * @return array|null
     */
    public function getHighlights(): ?array
    {
        return $this->searchHighlights;
    }

    /**
     * Set the search highlights for this model.
     *
     * @param array|null $highlights
     * @return static
     */
    public function setHighlights(?array $highlights): static
    {
        $this->searchHighlights = $highlights;
        return $this;
    }

    /**
     * Get the highlighted value for a specific field.
     *
     * @param string $field
     * @param string|null $default
     * @return string|null
     */
    public function getHighlight(string $field, ?string $default = null): ?string
    {
        if (!$this->searchHighlights || !isset($this->searchHighlights[$field])) {
            return $default;
        }

        return implode(' ... ', $this->searchHighlights[$field]);
    }

    /**
     * Get the index configuration for this model.
     * Override this method in your model to provide custom index settings.
     *
     * @return array
     */
    public function getOginiIndexConfiguration(): array
    {
        return [
            'settings' => [
                'numberOfShards' => 1,
                'refreshInterval' => '1s',
            ],
            'mappings' => [
                'properties' => $this->getOginiFieldMappings(),
            ],
        ];
    }

    /**
     * Get the field mappings for the search index.
     * Override this method in your model to define custom field types.
     *
     * @return array
     */
    public function getOginiFieldMappings(): array
    {
        $searchableArray = $this->toSearchableArray();
        $mappings = [];

        foreach ($searchableArray as $field => $value) {
            $mappings[$field] = $this->inferFieldType($field, $value);
        }

        return $mappings;
    }

    /**
     * Get the fields that should be searched by default.
     * Override this method in your model to define searchable fields.
     *
     * @return array
     */
    public function getSearchFields(): array
    {
        // Default to all text fields if not specified
        $mappings = $this->getOginiFieldMappings();
        $textFields = [];

        foreach ($mappings as $field => $config) {
            if (($config['type'] ?? 'text') === 'text') {
                $textFields[] = $field;
            }
        }

        return $textFields ?: ['*']; // Fallback to all fields
    }

    /**
     * Perform a search with highlighting enabled.
     *
     * @param string $query
     * @param callable|null $callback
     * @return Builder
     */
    public static function searchWithHighlights(string $query, ?callable $callback = null): Builder
    {
        $highlightCallback = function ($searchQuery, $builder) use ($callback) {
            // Enable highlighting
            $searchQuery['highlight'] = true;

            // Apply any additional callback if provided
            if ($callback) {
                $searchQuery = $callback($searchQuery, $builder);
            }

            return $searchQuery;
        };

        return static::search($query, $highlightCallback);
    }

    /**
     * Get suggestions for autocomplete.
     *
     * @param string $text
     * @param string|null $field
     * @param int $size
     * @return array
     */
    public static function suggest(string $text, ?string $field = null, int $size = 5): array
    {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('Suggest method requires OginiEngine');
        }

        $indexName = $model->searchableAs();
        $field = $field ?? $model->getSearchFields()[0] ?? null;

        return $engine->getClient()->suggest($indexName, $text, $field, $size);
    }

    /**
     * Create or update the search index with proper configuration.
     *
     * @return array
     */
    public static function createSearchIndex(): array
    {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('createSearchIndex method requires OginiEngine');
        }

        $indexName = $model->searchableAs();
        $configuration = $model->getOginiIndexConfiguration();

        return $engine->createIndex($indexName, $configuration);
    }

    /**
     * Delete the search index.
     *
     * @return array
     */
    public static function deleteSearchIndex(): array
    {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('deleteSearchIndex method requires OginiEngine');
        }

        return $engine->deleteIndex($model->searchableAs());
    }

    /**
     * Get statistics about the search index.
     *
     * @return array
     */
    public static function getSearchIndexInfo(): array
    {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('getSearchIndexInfo method requires OginiEngine');
        }

        return $engine->getClient()->getIndex($model->searchableAs());
    }

    /**
     * Infer the field type based on the value.
     *
     * @param string $field
     * @param mixed $value
     * @return array
     */
    protected function inferFieldType(string $field, $value): array
    {
        // Check if it's a date field (common Laravel date fields)
        if (
            in_array($field, ['created_at', 'updated_at', 'deleted_at']) ||
            str_ends_with($field, '_at') ||
            str_ends_with($field, '_date')
        ) {
            return ['type' => 'date'];
        }

        // Check if it's an ID field
        if ($field === 'id' || str_ends_with($field, '_id')) {
            return ['type' => 'keyword'];
        }

        // Check if it's a status or category-like field (typically used for filtering)
        if (
            in_array($field, ['status', 'state', 'type', 'category', 'role', 'level', 'priority']) ||
            str_ends_with($field, '_status') ||
            str_ends_with($field, '_state') ||
            str_ends_with($field, '_type') ||
            str_ends_with($field, '_category')
        ) {
            return ['type' => 'keyword'];
        }

        // Infer type from value
        if (is_bool($value)) {
            return ['type' => 'boolean'];
        }

        if (is_int($value)) {
            return ['type' => 'integer'];
        }

        if (is_float($value)) {
            return ['type' => 'float'];
        }

        if (is_array($value)) {
            return ['type' => 'keyword']; // Arrays are typically used for tags/categories
        }

        // Default to text for searchable content
        return [
            'type' => 'text',
            'analyzer' => 'standard',
        ];
    }

    /**
     * Perform a search with facets.
     *
     * @param string $query
     * @param array $facets Array of FacetDefinition objects or facet configurations
     * @param callable|null $callback
     * @return array
     */
    public static function searchWithFacets(string $query, array $facets, ?callable $callback = null): array
    {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('searchWithFacets method requires OginiEngine');
        }

        $builder = static::search($query, $callback);
        $results = $engine->searchWithFacets($builder, $facets);

        return [
            'results' => $results,
            'facets' => $engine->processFacetResults($results),
        ];
    }

    /**
     * Get facet definitions for this model.
     * Override this method in your model to define custom facets.
     *
     * @return array
     */
    public function getFacetDefinitions(): array
    {
        $mappings = $this->getOginiFieldMappings();
        $facets = [];

        foreach ($mappings as $field => $config) {
            $type = $config['type'] ?? 'text';

            // Create appropriate facets based on field type
            switch ($type) {
                case 'keyword':
                    $facets[$field] = FacetDefinition::terms($field, 10);
                    break;
                case 'integer':
                case 'float':
                    // Create histogram facets for numeric fields
                    $facets[$field] = FacetDefinition::histogram($field, 1.0);
                    break;
                case 'date':
                    // Create date histogram facets for date fields
                    $facets[$field] = FacetDefinition::dateHistogram($field, '1d');
                    break;
                case 'boolean':
                    // Create terms facet for boolean fields
                    $facets[$field] = FacetDefinition::terms($field, 2);
                    break;
            }
        }

        return $facets;
    }

    /**
     * Search with automatic facets based on field mappings.
     *
     * @param string $query
     * @param array $facetFields Specific fields to create facets for
     * @param callable|null $callback
     * @return array
     */
    public static function searchWithAutoFacets(string $query, array $facetFields = [], ?callable $callback = null): array
    {
        $model = new static();
        $allFacets = $model->getFacetDefinitions();

        // Filter to specific fields if provided
        if (!empty($facetFields)) {
            $facets = array_intersect_key($allFacets, array_flip($facetFields));
        } else {
            $facets = $allFacets;
        }

        return static::searchWithFacets($query, $facets, $callback);
    }

    /**
     * Create a terms facet for a specific field.
     *
     * @param string $field
     * @param int $size
     * @param array $options
     * @return FacetDefinition
     */
    public static function createTermsFacet(string $field, int $size = 10, array $options = []): FacetDefinition
    {
        return FacetDefinition::terms($field, $size, $options);
    }

    /**
     * Create a range facet for a specific field.
     *
     * @param string $field
     * @param array $ranges
     * @param array $options
     * @return FacetDefinition
     */
    public static function createRangeFacet(string $field, array $ranges, array $options = []): FacetDefinition
    {
        return FacetDefinition::range($field, $ranges, $options);
    }

    /**
     * Create a date histogram facet for a specific field.
     *
     * @param string $field
     * @param string $interval
     * @param array $options
     * @return FacetDefinition
     */
    public static function createDateHistogramFacet(string $field, string $interval = '1d', array $options = []): FacetDefinition
    {
        return FacetDefinition::dateHistogram($field, $interval, $options);
    }

    /**
     * Perform an advanced search with filters.
     *
     * @param string $query
     * @param callable $filterCallback
     * @param callable|null $searchCallback
     * @return array
     */
    public static function searchWithFilters(string $query, callable $filterCallback, ?callable $searchCallback = null): array
    {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('searchWithFilters method requires OginiEngine');
        }

        $builder = static::search($query, $searchCallback);
        return $engine->searchWithFilters($builder, $filterCallback);
    }

    /**
     * Perform an advanced search with custom sorting.
     *
     * @param string $query
     * @param callable $sortCallback
     * @param callable|null $searchCallback
     * @return array
     */
    public static function searchWithSorting(string $query, callable $sortCallback, ?callable $searchCallback = null): array
    {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('searchWithSorting method requires OginiEngine');
        }

        $builder = static::search($query, $searchCallback);
        return $engine->searchWithSorting($builder, $sortCallback);
    }

    /**
     * Perform an advanced search with custom highlighting.
     *
     * @param string $query
     * @param callable $highlightCallback
     * @param callable|null $searchCallback
     * @return array
     */
    public static function searchWithAdvancedHighlighting(string $query, callable $highlightCallback, ?callable $searchCallback = null): array
    {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('searchWithAdvancedHighlighting method requires OginiEngine');
        }

        $builder = static::search($query, $searchCallback);
        return $engine->searchWithAdvancedHighlighting($builder, $highlightCallback);
    }

    /**
     * Perform a comprehensive advanced search.
     *
     * @param string $query
     * @param callable|null $filterCallback
     * @param callable|null $sortCallback
     * @param callable|null $highlightCallback
     * @param array $facets
     * @param callable|null $searchCallback
     * @return array
     */
    public static function advancedSearch(
        string $query,
        ?callable $filterCallback = null,
        ?callable $sortCallback = null,
        ?callable $highlightCallback = null,
        array $facets = [],
        ?callable $searchCallback = null
    ): array {
        $model = new static();
        $engine = $model->searchableUsing();

        if (!$engine instanceof OginiEngine) {
            throw new \InvalidArgumentException('advancedSearch method requires OginiEngine');
        }

        $builder = static::search($query, $searchCallback);

        // Build filters
        $filters = null;
        if ($filterCallback) {
            $filters = new FilterBuilder();
            $filterCallback($filters);
        }

        // Build sorting
        $sorting = null;
        if ($sortCallback) {
            $sorting = new SortBuilder();
            $sortCallback($sorting);
        }

        // Build highlighting
        $highlighting = null;
        if ($highlightCallback) {
            $highlighting = new HighlightBuilder();
            $highlightCallback($highlighting);
        }

        $results = $engine->advancedSearch($builder, $filters, $sorting, $highlighting, $facets);

        return [
            'results' => $results,
            'facets' => $engine->processFacetResults($results),
        ];
    }

    /**
     * Create a filter builder for this model.
     *
     * @return FilterBuilder
     */
    public static function createFilterBuilder(): FilterBuilder
    {
        return new FilterBuilder();
    }

    /**
     * Create a sort builder for this model.
     *
     * @return SortBuilder
     */
    public static function createSortBuilder(): SortBuilder
    {
        return new SortBuilder();
    }

    /**
     * Create a highlight builder for this model.
     *
     * @return HighlightBuilder
     */
    public static function createHighlightBuilder(): HighlightBuilder
    {
        return new HighlightBuilder();
    }

    /**
     * Boot the trait.
     */
    public static function bootOginiSearchable(): void
    {
        // Automatically create index when first model is created
        static::created(function ($model) {
            try {
                $autoCreate = false;

                // Try to get config, but don't fail if not available (e.g., in tests)
                if (function_exists('config')) {
                    $autoCreate = config('ogini.auto_create_index', false);
                }

                if ($autoCreate) {
                    static::createSearchIndex();
                }
            } catch (\Exception $e) {
                // Log error but don't fail the model creation
                if (function_exists('logger')) {
                    try {
                        logger()->warning('Failed to auto-create search index', [
                            'model' => get_class($model),
                            'error' => $e->getMessage(),
                        ]);
                    } catch (\Exception $logException) {
                        // Ignore logging errors in test environment
                    }
                }
            }
        });
    }
}
