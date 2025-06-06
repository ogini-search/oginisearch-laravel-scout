<?php

namespace OginiScoutDriver\Examples;

use OginiScoutDriver\Search\Facets\FacetDefinition;
use OginiScoutDriver\Search\Filters\FilterBuilder;
use OginiScoutDriver\Search\Sorting\SortBuilder;
use OginiScoutDriver\Search\Highlighting\HighlightBuilder;

/**
 * Advanced Search Examples for OginiScoutDriver
 * 
 * This file demonstrates how to use the advanced search features
 * including faceted search, advanced filtering, sorting, and highlighting.
 */
class AdvancedSearchExamples
{
    /**
     * Example 1: Basic Faceted Search
     * 
     * This example shows how to perform a search with facets
     * to get aggregated data about your search results.
     */
    public function basicFacetedSearch()
    {
        // Define facets for different field types
        $facets = [
            'category' => FacetDefinition::terms('category', 10),
            'price_ranges' => FacetDefinition::range('price', [
                ['to' => 100],
                ['from' => 100, 'to' => 500],
                ['from' => 500, 'to' => 1000],
                ['from' => 1000],
            ]),
            'created_monthly' => FacetDefinition::dateHistogram('created_at', '1M'),
            'rating_histogram' => FacetDefinition::histogram('rating', 1.0),
        ];

        // Perform search with facets
        $results = Product::searchWithFacets('smartphone', $facets);

        // Access search results
        $products = $results['results'];

        // Access facet results
        $facetResults = $results['facets'];

        // Get category facet buckets
        $categoryFacet = $facetResults->get('category');
        $categoryBuckets = $categoryFacet->buckets();

        // Get top categories
        $topCategories = $categoryFacet->topBuckets(5);

        return [
            'products' => $products,
            'categories' => $topCategories,
            'price_ranges' => $facetResults->get('price_ranges')->buckets(),
            'monthly_trends' => $facetResults->get('created_monthly')->buckets(),
        ];
    }

    /**
     * Example 2: Advanced Filtering
     * 
     * This example demonstrates complex filtering capabilities
     * including range filters, boolean logic, and geo-filtering.
     */
    public function advancedFiltering()
    {
        // Search with complex filters
        $results = Product::searchWithFilters('laptop', function (FilterBuilder $filters) {
            // Price range filter
            $filters->between('price', 500, 2000);

            // Category filter
            $filters->terms('category', ['Electronics', 'Computers']);

            // Boolean filters
            $filters->term('is_featured', true);
            $filters->exists('description');

            // Text pattern filters
            $filters->prefix('brand', 'Apple');
            $filters->wildcard('model', '*Pro*');

            // Nested boolean logic
            $filters->should(function (FilterBuilder $shouldFilters) {
                $shouldFilters->term('rating', 5);
                $shouldFilters->greaterThan('review_count', 100, true);
            });

            // Exclude certain conditions
            $filters->mustNot(function (FilterBuilder $mustNotFilters) {
                $mustNotFilters->term('status', 'discontinued');
                $mustNotFilters->missing('image');
            });
        });

        return $results;
    }

    /**
     * Example 3: Advanced Sorting
     * 
     * This example shows multi-field sorting with custom options
     * including relevance scoring and geo-distance sorting.
     */
    public function advancedSorting()
    {
        // Search with complex sorting
        $results = Product::searchWithSorting('electronics', function (SortBuilder $sort) {
            // Primary sort by relevance score
            $sort->score('desc');

            // Secondary sort by featured status
            $sort->desc('is_featured');

            // Tertiary sort by price with missing value handling
            $sort->withMissing('price', 'asc', '_last');

            // Sort by rating with mode for multi-value fields
            $sort->withMode('ratings', 'desc', 'avg');

            // Random sort with seed for consistent pagination
            $sort->random(12345);

            // Geo-distance sorting (if you have location data)
            $sort->geoDistance('location', ['lat' => 40.7128, 'lon' => -74.0060], 'asc');
        });

        return $results;
    }

    /**
     * Example 4: Advanced Highlighting
     * 
     * This example demonstrates sophisticated text highlighting
     * with custom tags, fragment control, and multiple highlighter types.
     */
    public function advancedHighlighting()
    {
        // Search with advanced highlighting
        $results = Product::searchWithAdvancedHighlighting('smartphone camera', function (HighlightBuilder $highlight) {
            // Configure global highlighting options
            $highlight->htmlTag('mark', ['class' => 'search-highlight'])
                ->fragmentSize(200)
                ->numberOfFragments(3)
                ->unified();

            // Add specific fields with custom options
            $highlight->fieldWithOptions('title', 100, 1)
                ->fieldWithOptions('description', 300, 2)
                ->field('features', ['fragment_size' => 150]);

            // Advanced highlighting options
            $highlight->orderByScore()
                ->requireFieldMatch(true)
                ->boundaryScanner('sentence')
                ->noMatchSize(50);
        });

        return $results;
    }

    /**
     * Example 5: Comprehensive Advanced Search
     * 
     * This example combines all advanced features into a single
     * powerful search with facets, filters, sorting, and highlighting.
     */
    public function comprehensiveAdvancedSearch()
    {
        // Define facets
        $facets = [
            'brands' => FacetDefinition::terms('brand', 15),
            'price_ranges' => FacetDefinition::range('price', [
                ['to' => 200],
                ['from' => 200, 'to' => 500],
                ['from' => 500, 'to' => 1000],
                ['from' => 1000],
            ]),
            'availability' => FacetDefinition::terms('availability', 5),
        ];

        // Perform comprehensive search
        $results = Product::advancedSearch(
            'wireless headphones',
            // Filters
            function (FilterBuilder $filters) {
                $filters->terms('category', ['Audio', 'Electronics'])
                    ->between('price', 50, 500)
                    ->term('in_stock', true)
                    ->greaterThan('rating', 3.5, true);
            },
            // Sorting
            function (SortBuilder $sort) {
                $sort->score('desc')
                    ->desc('is_featured')
                    ->asc('price');
            },
            // Highlighting
            function (HighlightBuilder $highlight) {
                $highlight->htmlTag('em', ['class' => 'highlight'])
                    ->fields(['title', 'description'])
                    ->fragmentSize(150)
                    ->numberOfFragments(2);
            },
            // Facets
            $facets
        );

        return [
            'products' => $results['results'],
            'facets' => $results['facets'],
            'brand_facets' => $results['facets']->get('brands')->topBuckets(10),
            'price_distribution' => $results['facets']->get('price_ranges')->buckets(),
        ];
    }

    /**
     * Example 6: Auto-Generated Facets
     * 
     * This example shows how to use automatically generated facets
     * based on your model's field mappings.
     */
    public function autoGeneratedFacets()
    {
        // Search with auto-generated facets for specific fields
        $results = Product::searchWithAutoFacets('laptop', ['brand', 'category', 'price']);

        // Or search with all available facets
        $allFacetsResults = Product::searchWithAutoFacets('smartphone');

        return [
            'specific_facets' => $results,
            'all_facets' => $allFacetsResults,
        ];
    }

    /**
     * Example 7: Builder Pattern Usage
     * 
     * This example demonstrates using the builder classes directly
     * for more complex scenarios.
     */
    public function builderPatternUsage()
    {
        // Create builders manually
        $filterBuilder = Product::createFilterBuilder();
        $sortBuilder = Product::createSortBuilder();
        $highlightBuilder = Product::createHighlightBuilder();

        // Configure filters
        $filterBuilder->term('status', 'published')
            ->between('price', 100, 1000)
            ->exists('image');

        // Configure sorting
        $sortBuilder->desc('created_at')
            ->asc('title');

        // Configure highlighting
        $highlightBuilder->fields(['title', 'description'])
            ->tags('<strong>', '</strong>');

        // Use in search (this would require extending the trait or engine)
        // This is a conceptual example - actual implementation may vary
        return [
            'filters' => $filterBuilder->toArray(),
            'sorting' => $sortBuilder->toArray(),
            'highlighting' => $highlightBuilder->toArray(),
        ];
    }

    /**
     * Example 8: Geo-Spatial Search
     * 
     * This example demonstrates geo-spatial filtering and sorting
     * for location-based searches.
     */
    public function geoSpatialSearch()
    {
        // Search with geo-distance filtering
        $results = Store::searchWithFilters('coffee shop', function (FilterBuilder $filters) {
            // Find stores within 5km of a location
            $filters->geoDistance('location', ['lat' => 40.7128, 'lon' => -74.0060], '5km');

            // Find stores within a bounding box
            $filters->geoBoundingBox('location', [
                'top_left' => ['lat' => 40.8, 'lon' => -74.1],
                'bottom_right' => ['lat' => 40.6, 'lon' => -73.9],
            ]);
        });

        // Search with geo-distance sorting
        $sortedResults = Store::searchWithSorting('restaurant', function (SortBuilder $sort) {
            // Sort by distance from user location
            $sort->geoDistance('location', ['lat' => 40.7128, 'lon' => -74.0060], 'asc');

            // Secondary sort by rating
            $sort->desc('rating');
        });

        return [
            'nearby_stores' => $results,
            'sorted_by_distance' => $sortedResults,
        ];
    }

    /**
     * Example 9: Nested Field Search
     * 
     * This example shows how to search and filter on nested objects
     * like product variants or user profiles.
     */
    public function nestedFieldSearch()
    {
        // Search with nested filters
        $results = Product::searchWithFilters('smartphone', function (FilterBuilder $filters) {
            // Filter on nested variant properties
            $filters->nested('variants', function (FilterBuilder $nestedFilters) {
                $nestedFilters->term('color', 'black')
                    ->between('storage', 128, 512)
                    ->term('available', true);
            });

            // Filter on nested review properties
            $filters->nested('reviews', function (FilterBuilder $nestedFilters) {
                $nestedFilters->greaterThan('rating', 4, true)
                    ->exists('verified_purchase');
            });
        });

        return $results;
    }

    /**
     * Example 10: Performance Optimized Search
     * 
     * This example shows best practices for performance-optimized
     * searches with minimal data transfer and efficient queries.
     */
    public function performanceOptimizedSearch()
    {
        // Optimized search with minimal facets and efficient filters
        $results = Product::advancedSearch(
            'laptop',
            // Use efficient filters
            function (FilterBuilder $filters) {
                // Use term filters for exact matches (faster than text search)
                $filters->term('category_id', 123)
                    ->terms('brand_id', [1, 2, 3, 4])
                    ->range('price', ['gte' => 500, 'lte' => 2000]);
            },
            // Minimal sorting for better performance
            function (SortBuilder $sort) {
                $sort->field('_score', 'desc')  // Use built-in score
                    ->field('created_at', 'desc');  // Simple field sort
            },
            // Targeted highlighting
            function (HighlightBuilder $highlight) {
                $highlight->fields(['title'])  // Only highlight essential fields
                    ->fragmentSize(100)   // Smaller fragments
                    ->numberOfFragments(1); // Fewer fragments
            },
            // Essential facets only
            [
                'brands' => FacetDefinition::terms('brand_id', 5),  // Use IDs instead of names
                'price_ranges' => FacetDefinition::range('price', [
                    ['to' => 1000],
                    ['from' => 1000, 'to' => 2000],
                    ['from' => 2000],
                ]),
            ]
        );

        return $results;
    }
}

/**
 * Example Model Classes
 * 
 * These are example model classes showing how to use the OginiSearchable trait
 * with the advanced search features.
 */

class Product extends \Illuminate\Database\Eloquent\Model
{
    use \OginiScoutDriver\Traits\OginiSearchable;

    protected $fillable = [
        'title',
        'description',
        'price',
        'category',
        'brand',
        'rating',
        'is_featured',
        'in_stock',
        'created_at',
        'image',
        'status'
    ];

    public function searchableAs(): string
    {
        return 'products';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (float) $this->price,
            'category' => $this->category,
            'brand' => $this->brand,
            'rating' => (float) $this->rating,
            'is_featured' => (bool) $this->is_featured,
            'in_stock' => (bool) $this->in_stock,
            'created_at' => $this->created_at?->toISOString(),
            'has_image' => !empty($this->image),
            'status' => $this->status,
        ];
    }

    public function getOginiFieldMappings(): array
    {
        return [
            'title' => ['type' => 'text', 'analyzer' => 'standard'],
            'description' => ['type' => 'text', 'analyzer' => 'standard'],
            'price' => ['type' => 'float'],
            'category' => ['type' => 'keyword'],
            'brand' => ['type' => 'keyword'],
            'rating' => ['type' => 'float'],
            'is_featured' => ['type' => 'boolean'],
            'in_stock' => ['type' => 'boolean'],
            'created_at' => ['type' => 'date'],
            'has_image' => ['type' => 'boolean'],
            'status' => ['type' => 'keyword'],
        ];
    }

    public function getSearchFields(): array
    {
        return ['title', 'description'];
    }
}

class Store extends \Illuminate\Database\Eloquent\Model
{
    use \OginiScoutDriver\Traits\OginiSearchable;

    protected $fillable = [
        'name',
        'description',
        'location',
        'rating',
        'category'
    ];

    public function searchableAs(): string
    {
        return 'stores';
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'location' => $this->location, // Should be ['lat' => float, 'lon' => float]
            'rating' => (float) $this->rating,
            'category' => $this->category,
        ];
    }

    public function getOginiFieldMappings(): array
    {
        return [
            'name' => ['type' => 'text', 'analyzer' => 'standard'],
            'description' => ['type' => 'text', 'analyzer' => 'standard'],
            'location' => ['type' => 'geo_point'],
            'rating' => ['type' => 'float'],
            'category' => ['type' => 'keyword'],
        ];
    }
}
