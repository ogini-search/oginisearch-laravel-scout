# OginiSearch Laravel Scout Driver

A comprehensive Laravel Scout driver for OginiSearch, providing full-text search capabilities with advanced features and performance optimizations.

## Features

- Full Laravel Scout integration
- Advanced search capabilities
- Performance optimization with caching and connection pooling
- Asynchronous operations support
- Event-driven architecture
- Comprehensive error handling
- Query optimization and caching
- Synonym and stopword management
- Real-time search suggestions and autocomplete

## Requirements

- PHP >= 8.2
- Laravel >= 12.0
- Laravel Scout >= 10.0

## Installation

1. Install the package via Composer:

```bash
composer require ogini/oginisearch-laravel-scout
```

2. Publish the configuration file:

```bash
php artisan vendor:publish --tag=ogini-config
```

3. Set up your OginiSearch configuration in `config/ogini.php` or via environment variables:

```env
OGINI_BASE_URL=http://localhost:3000
OGINI_API_KEY=your-api-key-here
```

## Configuration

The package provides extensive configuration options in `config/ogini.php`:

```php
return [
    'base_url' => env('OGINI_BASE_URL', 'http://localhost:3000'),
    'api_key' => env('OGINI_API_KEY'),
    
    'client' => [
        'timeout' => 30,
        'retry_attempts' => 3,
        'retry_delay' => env('OGINI_BATCH_RETRY_DELAY', 100),
    ],
    
    'engine' => [
        'max_results' => 1000,
        'default_limit' => 15,
    ],
    
    'performance' => [
        'query_optimization' => [
            'enabled' => true,
            'min_term_length' => env('OGINI_MIN_TERM_LENGTH', 3),
            'max_complexity_score' => env('OGINI_MAX_COMPLEXITY_SCORE', 15),
            'performance_check_threshold' => env('OGINI_PERFORMANCE_CHECK_THRESHOLD', 100),
            'wildcard_penalty' => env('OGINI_WILDCARD_PENALTY', 5),
            'phrase_boost' => env('OGINI_PHRASE_BOOST', 1.5),
            'exact_match_boost' => env('OGINI_EXACT_MATCH_BOOST', 2.0),
            'fuzzy_match_boost' => env('OGINI_FUZZY_MATCH_BOOST', 1.0),
        ],
        'cache' => [
            'enabled' => true,
            'driver' => 'redis',
            'query_ttl' => 300,
            'result_ttl' => 1800,
            'suggestion_ttl' => 600,
        ],
        'connection_pool' => [
            'enabled' => true,
            'pool_size' => 5,
            'connection_timeout' => 5,
            'idle_timeout' => 30,
        ],
        'batch_processing' => [
            'enabled' => true,
            'batch_size' => 100,
            'max_retry_attempts' => 3,
            'retry_delay' => env('OGINI_BATCH_RETRY_DELAY', 100),
        ],
    ],
];
```

## Basic Usage

### Model Configuration

Configure your Eloquent models to use OginiSearch:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use Searchable;

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'author' => $this->author->name,
            'published_at' => $this->published_at,
        ];
    }

    /**
     * Get the index name for the model.
     */
    public function searchableAs(): string
    {
        return 'articles';
    }
}
```

### Searching

```php
// Basic search
$articles = Article::search('laravel scout')->get();

// Search with additional options
$articles = Article::search('laravel scout')
    ->options([
        'filter' => ['published' => true],
        'sort' => ['published_at' => 'desc'],
    ])
    ->paginate(15);
```

## Advanced Features

### 1. Advanced Client Methods

#### Query Suggestions

```php
use OginiScoutDriver\Facades\Ogini;

// Get query suggestions
$suggestions = Ogini::getQuerySuggestions('articles', 'larav', [
    'size' => 10,
    'fuzzy' => true,
    'highlight' => true,
]);

// Get autocomplete suggestions
$completions = Ogini::getAutocompleteSuggestions('articles', 'lar', [
    'size' => 5,
    'completion_field' => 'suggest',
]);
```

#### Synonym Management

```php
// Add synonyms
Ogini::addSynonyms('articles', [
    ['car', 'automobile', 'vehicle'],
    ['fast', 'quick', 'rapid'],
]);

// Get synonyms
$synonyms = Ogini::getSynonyms('articles');

// Update synonyms
Ogini::updateSynonyms('articles', [
    ['updated', 'modified', 'changed'],
]);

// Delete synonyms
Ogini::deleteSynonyms('articles');
```

#### Stopword Configuration

```php
// Configure stopwords
Ogini::configureStopwords('articles', ['the', 'a', 'an', 'and', 'or'], 'en');

// Get current stopwords
$stopwords = Ogini::getStopwords('articles');

// Update stopwords
Ogini::updateStopwords('articles', ['the', 'a', 'an']);

// Reset to default
Ogini::resetStopwords('articles', 'en');
```

### 2. Asynchronous Operations

#### Using the Async Client

```php
use OginiScoutDriver\Facades\AsyncOgini;

// Index documents asynchronously
$promise = AsyncOgini::indexDocumentAsync('articles', [
    'title' => 'Async Article',
    'content' => 'This article was indexed asynchronously',
]);

// Bulk index with callback
AsyncOgini::bulkIndexDocumentsAsync('articles', $documents, 
    function ($result) {
        Log::info('Bulk indexing completed', $result);
    },
    function ($error) {
        Log::error('Bulk indexing failed', ['error' => $error]);
    }
);

// Search asynchronously
$searchPromise = AsyncOgini::searchAsync('articles', [
    'query' => ['match' => ['title' => 'Laravel']],
]);

// Wait for all pending operations
$results = AsyncOgini::waitForAll();
```

#### Queue Integration

```php
// Enable queue integration
AsyncOgini::setQueueEnabled(true);

// Now operations will be queued instead of executed immediately
$jobId = AsyncOgini::indexDocumentAsync('articles', $document);
```

#### Parallel Execution

```php
// Execute multiple requests in parallel
$requests = [
    ['method' => 'POST', 'endpoint' => '/api/indices/articles/search', 'data' => $query1],
    ['method' => 'POST', 'endpoint' => '/api/indices/articles/search', 'data' => $query2],
    ['method' => 'POST', 'endpoint' => '/api/indices/articles/search', 'data' => $query3],
];

$results = AsyncOgini::executeParallel($requests, function ($completed, $total, $result) {
    echo "Progress: {$completed}/{$total}\n";
});
```

### 3. Event System

The package dispatches events for various operations, allowing you to listen and respond to search activities:

#### Available Events

- `IndexingCompleted` - When document indexing succeeds
- `IndexingFailed` - When document indexing fails
- `SearchCompleted` - When search operation succeeds
- `SearchFailed` - When search operation fails
- `DeletionCompleted` - When document deletion succeeds
- `DeletionFailed` - When document deletion fails

#### Listening to Events

```php
use OginiScoutDriver\Events\IndexingCompleted;
use OginiScoutDriver\Events\SearchCompleted;

// In your EventServiceProvider
protected $listen = [
    IndexingCompleted::class => [
        YourIndexingCompletedListener::class,
    ],
    SearchCompleted::class => [
        YourSearchCompletedListener::class,
    ],
];
```

#### Custom Event Listener

```php
<?php

namespace App\Listeners;

use OginiScoutDriver\Events\IndexingCompleted;

class LogIndexingSuccess
{
    public function handle(IndexingCompleted $event): void
    {
        \Log::info('Document indexed successfully', [
            'job_id' => $event->getJobId(),
            'index_name' => $event->getIndexName(),
            'document_id' => $event->getDocumentId(),
            'is_bulk' => $event->isBulk(),
        ]);
    }
}
```

### 4. Performance Features

#### Query Optimization

```php
// The package automatically optimizes queries based on configuration:
// - Removes short terms (< min_term_length)
// - Applies complexity scoring
// - Optimizes wildcard usage
// - Boosts phrase matches and exact matches
```

#### Caching

```php
// Query results are automatically cached based on configuration
// Cache keys are generated from query parameters and settings
// TTL values are configurable for different operation types
```

#### Connection Pooling

```php
// HTTP connections are pooled and reused for better performance
// Pool size and timeout settings are configurable
```

#### Batch Processing

```php
// Large operations are automatically batched
// Batch size and retry logic are configurable
```

## Error Handling

The package provides comprehensive error handling with detailed exception information:

```php
try {
    $results = Article::search('query')->get();
} catch (\OginiScoutDriver\Exceptions\OginiException $e) {
    Log::error('Search failed', [
        'message' => $e->getMessage(),
        'context' => $e->getContext(),
    ]);
}
```

## Update Management

The package includes an intelligent update checking system:

### Check for Updates

```bash
# Check for available updates
php artisan ogini:check-updates

# Clear cache and check
php artisan ogini:check-updates --clear-cache

# Security updates only
php artisan ogini:check-updates --security-only

# JSON output for automation
php artisan ogini:check-updates --json
```

### Programmatic Update Checking

```php
use OginiUpdateChecker;

// Check if updates are available
if (OginiUpdateChecker::hasUpdate()) {
    $updateInfo = OginiUpdateChecker::getUpdateInfo();
    
    if ($updateInfo['security_update']) {
        Log::warning('Security update available', $updateInfo);
    }
    
    if ($updateInfo['breaking_changes']) {
        Log::info('Major version update available', $updateInfo);
    }
}

// Get current and latest versions
$current = OginiUpdateChecker::getCurrentVersion();
$latest = OginiUpdateChecker::getLatestVersion();

// Clear update cache
OginiUpdateChecker::clearCache();
```

## Testing

The package includes comprehensive testing with enterprise-grade quality assurance:

### Test Coverage
- **430+ Tests**: Complete functionality coverage
- **90%+ Code Coverage**: Automated coverage validation
- **Edge Case Testing**: Boundary conditions and error scenarios
- **Security Testing**: Vulnerability and security validation
- **Laravel Compatibility**: Multi-version Laravel support (8.x-11.x)

### Run Tests

```bash
# Using the convenient test runner script
./run-tests.sh              # CI-safe tests (default)
./run-tests.sh unit          # Unit tests only
./run-tests.sh api-calls     # Real API call tests
./run-tests.sh all           # All tests (CI-safe + API calls if server available)
./run-tests.sh coverage      # Generate coverage report

# Or use composer/phpunit directly:
composer test                                              # CI-safe tests
vendor/bin/phpunit --group=real-api-calls                 # Real API call tests
vendor/bin/phpunit --configuration=phpunit.ci.xml --testsuite=CI-Safe  # CI config
```

#### Running Tests with Real API Calls

Some integration tests require a real Ogini server running locally. These tests are automatically skipped in CI/CD environments but can be run locally for comprehensive testing:

```bash
# 1. Start an Ogini server on localhost:3000
docker run -p 3000:3000 ogini/server

# 2. Run the real API call tests
vendor/bin/phpunit tests/Integration/DocumentCreationTest.php

# Or run all tests that require real API calls
vendor/bin/phpunit --group=real-api-calls
```

**Note**: Tests marked with `@group real-api-calls` are excluded from CI pipelines and `composer test` to ensure fast, reliable automated testing without external dependencies.

### Quality Assurance
- **PSR-12 Compliance**: PHP coding standards
- **Security Scanning**: Vulnerability detection
- **Code Quality Analysis**: Complexity and duplication checks
- **Documentation Coverage**: PHPDoc requirements

## Contributing

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass
5. Submit a pull request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE). 