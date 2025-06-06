# Advanced Search Features

The Ogini Scout Driver provides powerful advanced search capabilities that go beyond basic text search. This document covers all the advanced features including faceted search, complex filtering, advanced sorting, and enhanced highlighting.

## Table of Contents

1. [Faceted Search](#faceted-search)
2. [Advanced Filtering](#advanced-filtering)
3. [Advanced Sorting](#advanced-sorting)
4. [Enhanced Highlighting](#enhanced-highlighting)
5. [Comprehensive Search](#comprehensive-search)
6. [Performance Optimization](#performance-optimization)
7. [Examples](#examples)

## Faceted Search

Faceted search allows you to get aggregated data about your search results, enabling users to filter and explore data interactively.

### Basic Facet Types

#### Terms Facet
Get the most common values for a field:

```php
use OginiScoutDriver\Search\Facets\FacetDefinition;

$facets = [
    'categories' => FacetDefinition::terms('category', 10), // Top 10 categories
    'brands' => FacetDefinition::terms('brand', 15, ['min_doc_count' => 2]),
];

$results = Product::searchWithFacets('smartphone', $facets);
```

#### Range Facet
Group results into predefined ranges:

```php
$facets = [
    'price_ranges' => FacetDefinition::range('price', [
        ['to' => 100],                    // Under $100
        ['from' => 100, 'to' => 500],     // $100-$500
        ['from' => 500, 'to' => 1000],    // $500-$1000
        ['from' => 1000],                 // Over $1000
    ]),
];
```

#### Date Histogram Facet
Group results by date intervals:

```php
$facets = [
    'monthly_trends' => FacetDefinition::dateHistogram('created_at', '1M'),
    'daily_activity' => FacetDefinition::dateHistogram('updated_at', '1d'),
    'yearly_summary' => FacetDefinition::dateHistogram('published_at', '1y'),
];
```

#### Histogram Facet
Create numeric histograms:

```php
$facets = [
    'rating_distribution' => FacetDefinition::histogram('rating', 0.5), // 0.5 intervals
    'price_histogram' => FacetDefinition::histogram('price', 100),      // $100 intervals
];
```

### Working with Facet Results

```php
$results = Product::searchWithFacets('laptop', $facets);

// Access facet results
$facetResults = $results['facets'];

// Get specific facet
$categoryFacet = $facetResults->get('categories');

// Get all buckets
$buckets = $categoryFacet->buckets();

// Get top N buckets
$topCategories = $categoryFacet->topBuckets(5);

// Check if facet has results
if ($categoryFacet->hasResults()) {
    foreach ($categoryFacet->buckets() as $bucket) {
        echo "{$bucket['key']}: {$bucket['count']} items\n";
    }
}
```

## Advanced Filtering

The FilterBuilder provides sophisticated filtering capabilities with support for boolean logic, ranges, patterns, and geo-spatial queries.

### Basic Filters

```php
use OginiScoutDriver\Search\Filters\FilterBuilder;

$results = Product::searchWithFilters('smartphone', function (FilterBuilder $filters) {
    // Exact match
    $filters->term('status', 'published');
    
    // Multiple values (OR logic)
    $filters->terms('category', ['Electronics', 'Mobile']);
    
    // Range filters
    $filters->between('price', 100, 500);
    $filters->greaterThan('rating', 4.0, true); // inclusive
    $filters->lessThan('stock', 10, false);     // exclusive
    
    // Field existence
    $filters->exists('description');
    $filters->missing('discontinued_at');
    
    // Pattern matching
    $filters->prefix('title', 'iPhone');
    $filters->wildcard('model', '*Pro*');
});
```

### Boolean Logic

```php
$results = Product::searchWithFilters('laptop', function (FilterBuilder $filters) {
    // Must match all conditions (AND logic)
    $filters->must(function (FilterBuilder $mustFilters) {
        $mustFilters->term('status', 'published');
        $mustFilters->exists('image');
    });
    
    // Should match at least one condition (OR logic)
    $filters->should(function (FilterBuilder $shouldFilters) {
        $shouldFilters->term('featured', true);
        $shouldFilters->greaterThan('rating', 4.5, true);
    });
    
    // Must not match any condition (NOT logic)
    $filters->mustNot(function (FilterBuilder $mustNotFilters) {
        $mustNotFilters->term('status', 'discontinued');
        $mustNotFilters->missing('price');
    });
});
```

### Geo-Spatial Filtering

```php
$results = Store::searchWithFilters('coffee', function (FilterBuilder $filters) {
    // Distance from a point
    $filters->geoDistance('location', ['lat' => 40.7128, 'lon' => -74.0060], '5km');
    
    // Within a bounding box
    $filters->geoBoundingBox('location', [
        'top_left' => ['lat' => 40.8, 'lon' => -74.1],
        'bottom_right' => ['lat' => 40.6, 'lon' => -73.9],
    ]);
    
    // Within a polygon
    $filters->geoPolygon('location', [
        ['lat' => 40.7, 'lon' => -74.0],
        ['lat' => 40.8, 'lon' => -74.0],
        ['lat' => 40.8, 'lon' => -73.9],
        ['lat' => 40.7, 'lon' => -73.9],
    ]);
});
```

### Nested Object Filtering

```php
$results = Product::searchWithFilters('smartphone', function (FilterBuilder $filters) {
    // Filter on nested objects
    $filters->nested('variants', function (FilterBuilder $nestedFilters) {
        $nestedFilters->term('color', 'black');
        $nestedFilters->between('storage', 128, 512);
        $nestedFilters->term('available', true);
    });
});
```

## Advanced Sorting

The SortBuilder provides flexible sorting options including multi-field sorting, geo-distance sorting, and custom scoring.

### Basic Sorting

```php
use OginiScoutDriver\Search\Sorting\SortBuilder;

$results = Product::searchWithSorting('laptop', function (SortBuilder $sort) {
    // Simple field sorting
    $sort->asc('price');
    $sort->desc('created_at');
    
    // Relevance score
    $sort->score('desc');
    
    // Multiple fields with priority
    $sort->desc('is_featured')    // Primary sort
         ->asc('price')           // Secondary sort
         ->desc('rating');        // Tertiary sort
});
```

### Advanced Sorting Options

```php
$results = Product::searchWithSorting('electronics', function (SortBuilder $sort) {
    // Handle missing values
    $sort->withMissing('price', 'asc', '_last');  // Put missing values last
    $sort->withMissing('rating', 'desc', '_first'); // Put missing values first
    
    // Multi-value field sorting
    $sort->withMode('tags', 'asc', 'min');    // Use minimum value
    $sort->withMode('ratings', 'desc', 'avg'); // Use average value
    
    // Random sorting with seed (for consistent pagination)
    $sort->random(12345);
    
    // Script-based sorting
    $sort->script([
        'type' => 'number',
        'script' => [
            'source' => 'doc["price"].value * doc["rating"].value',
        ],
        'order' => 'desc',
    ]);
});
```

### Geo-Distance Sorting

```php
$results = Store::searchWithSorting('restaurant', function (SortBuilder $sort) {
    // Sort by distance from user location
    $sort->geoDistance('location', ['lat' => 40.7128, 'lon' => -74.0060], 'asc');
    
    // Secondary sort by rating
    $sort->desc('rating');
});
```

## Enhanced Highlighting

The HighlightBuilder provides sophisticated text highlighting with customizable tags, fragment control, and multiple highlighter types.

### Basic Highlighting

```php
use OginiScoutDriver\Search\Highlighting\HighlightBuilder;

$results = Product::searchWithAdvancedHighlighting('smartphone camera', function (HighlightBuilder $highlight) {
    // Simple highlighting
    $highlight->fields(['title', 'description'])
             ->tags('<em>', '</em>')
             ->fragmentSize(150)
             ->numberOfFragments(3);
});
```

### Advanced Highlighting Options

```php
$results = Product::searchWithAdvancedHighlighting('wireless headphones', function (HighlightBuilder $highlight) {
    // HTML tags with attributes
    $highlight->htmlTag('mark', ['class' => 'search-highlight', 'style' => 'background: yellow']);
    
    // Field-specific options
    $highlight->fieldWithOptions('title', 100, 1)           // Short title highlights
             ->fieldWithOptions('description', 300, 2)      // Longer description highlights
             ->field('features', ['fragment_size' => 150]); // Custom options
    
    // Highlighter type
    $highlight->unified();        // Fast and accurate
    // $highlight->plain();       // Simple highlighter
    // $highlight->fastVector();  // For large texts
    
    // Fragment ordering
    $highlight->orderByScore();   // Order by relevance
    // $highlight->orderByPosition(); // Order by position in text
    
    // Boundary detection
    $highlight->boundaryScanner('sentence')
             ->boundaryChars('.,!? \t\n')
             ->boundaryMaxScan(20);
    
    // Advanced options
    $highlight->requireFieldMatch(true)  // Only highlight matched fields
             ->noMatchSize(50)           // Show text even without matches
             ->phraseLimit(256);         // Limit phrase highlighting
});
```

### Pre-built Highlighting Styles

```php
// Simple highlighting with common settings
$highlight = HighlightBuilder::simple(['title', 'description']);

// HTML highlighting with custom styling
$highlight = HighlightBuilder::html(['title', 'description'], 'mark', ['class' => 'highlight']);
```

## Comprehensive Search

Combine all advanced features in a single search:

```php
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
    [
        'brands' => FacetDefinition::terms('brand', 15),
        'price_ranges' => FacetDefinition::range('price', [
            ['to' => 200],
            ['from' => 200, 'to' => 500],
            ['from' => 500],
        ]),
    ]
);

// Access results
$products = $results['results'];
$facets = $results['facets'];
$brandFacets = $facets->get('brands')->topBuckets(10);
```

## Performance Optimization

### Efficient Filtering

```php
// Use term filters for exact matches (faster than text search)
$filters->term('category_id', 123)
        ->terms('brand_id', [1, 2, 3, 4])
        ->range('price', ['gte' => 500, 'lte' => 2000]);

// Avoid wildcard filters on large datasets
// $filters->wildcard('title', '*search*'); // Slow
$filters->prefix('title', 'search');        // Faster
```

### Minimal Data Transfer

```php
// Only request essential facets
$facets = [
    'brands' => FacetDefinition::terms('brand_id', 5),  // Use IDs instead of names
];

// Limit highlighting to essential fields
$highlight->fields(['title'])              // Only highlight title
          ->fragmentSize(100)              // Smaller fragments
          ->numberOfFragments(1);          // Fewer fragments
```

### Caching Strategies

```php
// Cache facet results for popular searches
$cacheKey = 'facets:' . md5($query . serialize($filters));
$facets = Cache::remember($cacheKey, 300, function () use ($query, $filters) {
    return Product::searchWithFacets($query, $filters);
});
```

## Examples

See the [AdvancedSearchExamples.php](../examples/AdvancedSearchExamples.php) file for comprehensive examples of all advanced search features.

### Quick Start Examples

#### E-commerce Product Search

```php
$results = Product::advancedSearch(
    'smartphone',
    function (FilterBuilder $filters) {
        $filters->between('price', 200, 1000)
               ->terms('brand', ['Apple', 'Samsung', 'Google'])
               ->term('in_stock', true);
    },
    function (SortBuilder $sort) {
        $sort->desc('is_featured')->asc('price');
    },
    function (HighlightBuilder $highlight) {
        $highlight->fields(['title', 'description']);
    },
    [
        'brands' => FacetDefinition::terms('brand', 10),
        'price_ranges' => FacetDefinition::range('price', [
            ['to' => 300], ['from' => 300, 'to' => 600], ['from' => 600]
        ]),
    ]
);
```

#### Location-based Store Search

```php
$results = Store::searchWithFilters('coffee', function (FilterBuilder $filters) {
    $filters->geoDistance('location', ['lat' => 40.7128, 'lon' => -74.0060], '2km')
           ->greaterThan('rating', 4.0, true)
           ->term('open_now', true);
});
```

#### Content Search with Highlighting

```php
$results = Article::searchWithAdvancedHighlighting('machine learning', function (HighlightBuilder $highlight) {
    $highlight->htmlTag('mark', ['class' => 'search-highlight'])
             ->fieldWithOptions('title', 100, 1)
             ->fieldWithOptions('content', 300, 3)
             ->orderByScore()
             ->requireFieldMatch(true);
});
```

## Error Handling

```php
try {
    $results = Product::advancedSearch($query, $filters, $sorting, $highlighting, $facets);
} catch (\OginiScoutDriver\Exceptions\OginiException $e) {
    if ($e->isClientError()) {
        // Handle client errors (400-499)
        Log::warning('Search client error: ' . $e->getMessage());
    } elseif ($e->isServerError()) {
        // Handle server errors (500-599)
        Log::error('Search server error: ' . $e->getMessage());
    } else {
        // Handle connection errors
        Log::error('Search connection error: ' . $e->getMessage());
    }
    
    // Return fallback results
    return ['results' => [], 'facets' => new FacetCollection()];
}
```

## Best Practices

1. **Use appropriate facet sizes**: Don't request more facet buckets than you need
2. **Optimize filters**: Use term filters for exact matches, avoid expensive wildcard queries
3. **Limit highlighting**: Only highlight fields that will be displayed to users
4. **Cache results**: Cache expensive facet queries and search results when appropriate
5. **Monitor performance**: Use the Ogini dashboard to monitor query performance
6. **Test with real data**: Test your search queries with production-like data volumes

## API Reference

For detailed API documentation, see:
- [FacetDefinition API](../src/Search/Facets/FacetDefinition.php)
- [FilterBuilder API](../src/Search/Filters/FilterBuilder.php)
- [SortBuilder API](../src/Search/Sorting/SortBuilder.php)
- [HighlightBuilder API](../src/Search/Highlighting/HighlightBuilder.php)
- [OginiSearchable Trait API](../src/Traits/OginiSearchable.php) 