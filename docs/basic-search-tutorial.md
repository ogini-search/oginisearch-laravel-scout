# Basic Search Tutorial

This tutorial covers fundamental search operations with the OginiSearch Laravel Scout Driver. You'll learn how to perform various types of searches, use filters, and implement common search patterns.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Basic Search Operations](#basic-search-operations)
- [Search with Filters](#search-with-filters)
- [Sorting and Pagination](#sorting-and-pagination)
- [Working with Search Results](#working-with-search-results)
- [Common Search Patterns](#common-search-patterns)
- [Performance Tips](#performance-tips)
- [Testing Your Searches](#testing-your-searches)

## Prerequisites

Before starting this tutorial, ensure you have:

- Completed the [Getting Started Guide](./getting-started.md)
- A working Laravel application with OginiSearch configured
- At least one searchable model set up

## Basic Search Operations

### Simple Text Search

The most basic search operation looks for text across all searchable fields:

```php
use App\Models\BlogPost;

// Search for posts containing "Laravel"
$posts = BlogPost::search('Laravel')->get();

// Search for multiple terms
$posts = BlogPost::search('Laravel PHP framework')->get();

// Search with quotes for exact phrases
$posts = BlogPost::search('"web development"')->get();
```

### Field-Specific Search

Search within specific fields for more targeted results:

```php
// Using Scout's options to specify fields
$posts = BlogPost::search('John Doe')
    ->options([
        'fields' => ['author_name']
    ])
    ->get();

// Search in multiple specific fields
$posts = BlogPost::search('tutorial')
    ->options([
        'fields' => ['title', 'category']
    ])
    ->get();
```

### Fuzzy Search

Find results even with typos or similar terms:

```php
$posts = BlogPost::search('Laravell')  // Note the typo
    ->options([
        'fuzzy' => true,
        'fuzziness' => 'AUTO'  // or 1, 2 for specific edit distance
    ])
    ->get();
```

### Wildcard Search

Use wildcards for partial matching:

```php
// Find posts with titles starting with "Prog"
$posts = BlogPost::search('Prog*')
    ->options([
        'fields' => ['title']
    ])
    ->get();

// Find posts with "develop" anywhere in the word
$posts = BlogPost::search('*develop*')->get();
```

## Search with Filters

Filters narrow down search results without affecting relevance scoring:

### Basic Filtering

```php
// Filter by exact field value
$posts = BlogPost::search('programming')
    ->options([
        'filter' => [
            'category' => 'Tutorial'
        ]
    ])
    ->get();

// Multiple filters (AND condition)
$posts = BlogPost::search('Laravel')
    ->options([
        'filter' => [
            'category' => 'Programming',
            'is_published' => true
        ]
    ])
    ->get();
```

### Range Filters

Filter by numeric or date ranges:

```php
// Filter by date range
$posts = BlogPost::search('tutorial')
    ->options([
        'filter' => [
            'published_at' => [
                'gte' => '2023-01-01',
                'lte' => '2023-12-31'
            ]
        ]
    ])
    ->get();

// Filter by numeric range (if you have a rating field)
$posts = BlogPost::search('review')
    ->options([
        'filter' => [
            'rating' => [
                'gte' => 4.0
            ]
        ]
    ])
    ->get();
```

### Array Filters (OR conditions)

Filter by multiple possible values:

```php
// Posts in any of these categories
$posts = BlogPost::search('web')
    ->options([
        'filter' => [
            'category' => ['Programming', 'Tutorial', 'Guide']
        ]
    ])
    ->get();
```

### Complex Filters

Combine different filter types:

```php
$posts = BlogPost::search('Laravel')
    ->options([
        'filter' => [
            'category' => ['Programming', 'Tutorial'],
            'is_published' => true,
            'published_at' => [
                'gte' => '2023-01-01'
            ]
        ]
    ])
    ->get();
```

## Sorting and Pagination

### Basic Sorting

```php
// Sort by publish date (newest first)
$posts = BlogPost::search('tutorial')
    ->options([
        'sort' => ['published_at' => 'desc']
    ])
    ->get();

// Sort by title alphabetically
$posts = BlogPost::search('programming')
    ->options([
        'sort' => ['title' => 'asc']
    ])
    ->get();
```

### Multi-field Sorting

```php
// Sort by category first, then by publish date
$posts = BlogPost::search('web development')
    ->options([
        'sort' => [
            ['category' => 'asc'],
            ['published_at' => 'desc']
        ]
    ])
    ->get();
```

### Sorting with Relevance

```php
// Combine relevance score with date sorting
$posts = BlogPost::search('Laravel framework')
    ->options([
        'sort' => [
            '_score',  // Relevance first
            ['published_at' => 'desc']  // Then by date
        ]
    ])
    ->get();
```

### Pagination

```php
// Basic pagination
$posts = BlogPost::search('tutorial')->paginate(10);

// Pagination with custom parameters
$posts = BlogPost::search('programming')
    ->options([
        'filter' => ['category' => 'Programming']
    ])
    ->paginate(15);

// Simple pagination
$posts = BlogPost::search('Laravel')->simplePaginate(10);
```

### Manual Pagination

```php
// Using take() and skip()
$posts = BlogPost::search('web development')
    ->take(10)
    ->skip(20)  // Skip first 20 results
    ->get();

// Using Scout's raw pagination
$posts = BlogPost::search('tutorial')
    ->options([
        'size' => 10,
        'from' => 0
    ])
    ->get();
```

## Working with Search Results

### Accessing Result Data

```php
$posts = BlogPost::search('Laravel')->get();

foreach ($posts as $post) {
    echo "Title: " . $post->title . "\n";
    echo "Author: " . $post->author_name . "\n";
    echo "Published: " . $post->published_at->format('Y-m-d') . "\n";
    echo "---\n";
}
```

### Search Metadata

```php
// Get search results with metadata
$results = BlogPost::search('programming')
    ->options([
        'include_meta' => true
    ])
    ->raw();

echo "Total results: " . $results['total'] . "\n";
echo "Search took: " . $results['took'] . "ms\n";

foreach ($results['hits'] as $hit) {
    echo "Score: " . $hit['_score'] . "\n";
    echo "Title: " . $hit['_source']['title'] . "\n";
    echo "---\n";
}
```

### Result Counts

```php
// Count total results without fetching
$totalPosts = BlogPost::search('Laravel')->count();

// Check if results exist
$hasResults = BlogPost::search('tutorial')->exists();

// Get first result only
$firstPost = BlogPost::search('programming')->first();
```

### Converting to Collections

```php
// Get as Laravel Collection
$posts = BlogPost::search('web development')->get();

// Transform results
$titles = $posts->pluck('title');
$categories = $posts->groupBy('category');

// Filter results further
$recentPosts = $posts->filter(function ($post) {
    return $post->published_at->isAfter(now()->subDays(30));
});
```

## Common Search Patterns

### Auto-suggest/Autocomplete

```php
use OginiScoutDriver\Facades\Ogini;

// Get search suggestions
public function getSuggestions(Request $request)
{
    $query = $request->get('q', '');
    
    if (strlen($query) < 2) {
        return response()->json([]);
    }
    
    $suggestions = Ogini::getQuerySuggestions('blog_posts', $query, [
        'size' => 5,
        'fuzzy' => true
    ]);
    
    return response()->json($suggestions);
}
```

### Search with Highlighting

```php
// Search with result highlighting
$posts = BlogPost::search('Laravel framework')
    ->options([
        'highlight' => [
            'fields' => ['title', 'content'],
            'pre_tags' => ['<mark>'],
            'post_tags' => ['</mark>']
        ]
    ])
    ->raw();

foreach ($posts['hits'] as $hit) {
    $highlights = $hit['highlight'] ?? [];
    $title = $highlights['title'][0] ?? $hit['_source']['title'];
    echo "Title: " . $title . "\n";
}
```

### Search as You Type

```php
// Controller method for live search
public function liveSearch(Request $request)
{
    $query = $request->get('q', '');
    
    if (strlen($query) < 3) {
        return response()->json(['results' => []]);
    }
    
    $posts = BlogPost::search($query)
        ->options([
            'filter' => ['is_published' => true],
            'sort' => ['_score', ['published_at' => 'desc']],
            'fields' => ['title', 'author_name', 'category']
        ])
        ->take(10)
        ->get();
    
    return response()->json([
        'results' => $posts->map(function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'author' => $post->author_name,
                'category' => $post->category,
                'url' => route('posts.show', $post)
            ];
        })
    ]);
}
```

### Category-based Search

```php
// Search within specific categories
public function searchByCategory($category, Request $request)
{
    $query = $request->get('q', '');
    
    $posts = BlogPost::search($query)
        ->options([
            'filter' => [
                'category' => $category,
                'is_published' => true
            ],
            'sort' => ['published_at' => 'desc']
        ])
        ->paginate(15);
    
    return view('posts.category-search', compact('posts', 'category', 'query'));
}
```

### Multi-Model Search

```php
// Search across multiple models
use App\Models\BlogPost;
use App\Models\Product;
use App\Models\User;

public function globalSearch(Request $request)
{
    $query = $request->get('q', '');
    
    $results = [
        'posts' => BlogPost::search($query)->take(5)->get(),
        'products' => Product::search($query)->take(5)->get(),
        'users' => User::search($query)->take(5)->get(),
    ];
    
    return response()->json($results);
}
```

## Performance Tips

### Optimize Your Queries

```php
// Use specific fields for better performance
$posts = BlogPost::search('Laravel')
    ->options([
        'fields' => ['title', 'content'],  // Limit search fields
        'size' => 20,  // Limit result size
        'filter' => ['is_published' => true]  // Use filters
    ])
    ->get();
```

### Use Caching for Repeated Searches

```php
use Illuminate\Support\Facades\Cache;

public function cachedSearch($query)
{
    $cacheKey = 'search:posts:' . md5($query);
    
    return Cache::remember($cacheKey, 300, function () use ($query) {
        return BlogPost::search($query)
            ->options(['filter' => ['is_published' => true]])
            ->get();
    });
}
```

### Batch Operations

```php
// Index multiple models efficiently
$posts = collect([
    new BlogPost(['title' => 'Post 1', 'content' => 'Content 1']),
    new BlogPost(['title' => 'Post 2', 'content' => 'Content 2']),
    // ... more posts
]);

// Save all posts first
$posts->each->save();

// Then import to search index
BlogPost::makeAllSearchable();
```

## Testing Your Searches

### Unit Tests

```php
use Tests\TestCase;
use App\Models\BlogPost;

class SearchTest extends TestCase
{
    public function test_basic_search_returns_results()
    {
        // Create test data
        $post = BlogPost::factory()->create([
            'title' => 'Laravel Testing Guide',
            'content' => 'How to test Laravel applications',
            'is_published' => true
        ]);
        
        // Perform search
        $results = BlogPost::search('Laravel')->get();
        
        // Assert results
        $this->assertGreaterThan(0, $results->count());
        $this->assertTrue($results->contains('id', $post->id));
    }
    
    public function test_search_with_filters()
    {
        // Create test data
        BlogPost::factory()->create(['category' => 'Programming', 'is_published' => true]);
        BlogPost::factory()->create(['category' => 'Design', 'is_published' => true]);
        
        // Search with filter
        $results = BlogPost::search('*')
            ->options(['filter' => ['category' => 'Programming']])
            ->get();
        
        // Assert all results are from Programming category
        $this->assertTrue($results->every(fn($post) => $post->category === 'Programming'));
    }
}
```

### Manual Testing

```php
// Create a test command
php artisan make:command TestSearch

// In the command class:
public function handle()
{
    $query = $this->ask('Enter search query:');
    
    $results = BlogPost::search($query)
        ->options([
            'highlight' => ['fields' => ['title', 'content']]
        ])
        ->get();
    
    $this->info("Found {$results->count()} results:");
    
    foreach ($results as $post) {
        $this->line("- {$post->title} by {$post->author_name}");
    }
}
```

### Debug Search Queries

```php
// Enable query logging
use OginiScoutDriver\Facades\Ogini;

// Log raw search queries
$results = BlogPost::search('Laravel')
    ->options([
        'debug' => true,  // This will log the query
        'filter' => ['category' => 'Programming']
    ])
    ->raw();

// Check what was actually sent to OginiSearch
dd($results);
```

## Next Steps

Now that you understand basic search operations, you can explore:

1. **[Advanced Search Tutorial](./advanced-search-tutorial.md)** - Complex queries, aggregations, and advanced features
2. **[Custom Configuration Tutorial](./custom-configuration-tutorial.md)** - Fine-tune your search setup
3. **[API Reference](./api-reference.md)** - Complete method documentation
4. **[Performance Optimization](./performance-optimization.md)** - Scale your search for production

## Common Issues and Solutions

### Search Returns No Results

```php
// Debug steps:
1. Check if model is indexed:
   BlogPost::search('*')->raw();

2. Verify model configuration:
   $post = BlogPost::first();
   dd($post->toSearchableArray());

3. Re-index if needed:
   php artisan scout:import "App\Models\BlogPost"
```

### Search is Too Slow

```php
// Optimization techniques:
1. Use filters instead of full-text search when possible
2. Limit the number of fields searched
3. Implement result caching
4. Use pagination for large result sets
```

### Relevance Scoring Issues

```php
// Improve relevance:
1. Use exact phrase matching with quotes
2. Boost important fields in your model
3. Use filters to eliminate irrelevant results
4. Consider custom scoring strategies
```

Ready to implement more advanced search features? Continue with our [Advanced Search Tutorial](./advanced-search-tutorial.md)! 