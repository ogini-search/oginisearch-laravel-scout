# Frequently Asked Questions (FAQ)

## Installation & Setup

### Q: How do I install the OginiSearch Laravel Scout Driver?

**A:** Install via Composer:

```bash
composer require ogini/oginisearch-laravel-scout
```

Then publish the configuration:

```bash
php artisan vendor:publish --tag=ogini-config
```

### Q: What are the minimum requirements?

**A:** 
- PHP 8.2 or higher
- Laravel 10.0 or higher
- Laravel Scout 10.0 or higher
- OginiSearch server instance

### Q: How do I configure the connection to OginiSearch?

**A:** Set your environment variables in `.env`:

```env
SCOUT_DRIVER=ogini
OGINI_BASE_URL=http://localhost:3000
OGINI_API_KEY=your-api-key-here
```

## Configuration

### Q: Where is the configuration file located?

**A:** After publishing, the configuration file is located at `config/ogini.php`. You can customize all aspects of the driver behavior here.

### Q: How do I enable caching?

**A:** Caching is enabled by default. You can configure it in `config/ogini.php`:

```php
'performance' => [
    'cache' => [
        'enabled' => true,
        'driver' => 'redis', // or 'file', 'database'
        'query_ttl' => 300,
        'result_ttl' => 1800,
    ],
],
```

### Q: How do I configure connection pooling?

**A:** Connection pooling is enabled by default for better performance:

```php
'performance' => [
    'connection_pool' => [
        'enabled' => true,
        'pool_size' => 5,
        'connection_timeout' => 5,
        'idle_timeout' => 30,
    ],
],
```

## Search Implementation

### Q: How do I make my model searchable?

**A:** Add the `Searchable` trait to your model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'author' => $this->author->name,
        ];
    }
}
```

### Q: How do I perform a basic search?

**A:** Use Laravel Scout's standard search methods:

```php
// Basic search
$articles = Article::search('laravel')->get();

// Search with pagination
$articles = Article::search('laravel')->paginate(15);

// Search with additional options
$articles = Article::search('laravel')
    ->options([
        'filter' => ['published' => true],
        'sort' => ['created_at' => 'desc'],
    ])
    ->get();
```

### Q: How do I implement faceted search?

**A:** Use the `options` method with facets:

```php
$results = Article::search('technology')
    ->options([
        'facets' => ['category', 'author', 'tags'],
        'filter' => ['category' => 'Programming'],
    ])
    ->get();
```

### Q: How do I get search suggestions?

**A:** Use the Ogini facade:

```php
use OginiScoutDriver\Facades\Ogini;

$suggestions = Ogini::getQuerySuggestions('articles', 'larav', [
    'size' => 10,
    'fuzzy' => true,
]);
```

## Performance

### Q: How can I improve search performance?

**A:** Several optimization strategies:

1. **Enable caching** (enabled by default)
2. **Use connection pooling** (enabled by default)
3. **Configure batch processing** for bulk operations
4. **Optimize your searchable arrays** - only include necessary fields
5. **Use appropriate filters** to reduce result sets

### Q: How do I handle large datasets?

**A:** Use batch processing for indexing:

```php
// Configure in config/ogini.php
'performance' => [
    'batch_processing' => [
        'enabled' => true,
        'batch_size' => 500, // Adjust based on your needs
        'max_retry_attempts' => 3,
    ],
],
```

### Q: Can I use async operations?

**A:** Yes! Use the AsyncOgini facade:

```php
use OginiScoutDriver\Facades\AsyncOgini;

// Async indexing
$promise = AsyncOgini::indexDocumentAsync('articles', $document);

// Async search
$searchPromise = AsyncOgini::searchAsync('articles', $query);

// Wait for completion
$results = AsyncOgini::waitForAll();
```

## Troubleshooting

### Q: I'm getting connection errors. What should I check?

**A:** Check these common issues:

1. **OginiSearch server is running** and accessible
2. **Base URL is correct** in your configuration
3. **API key is valid** (if authentication is enabled)
4. **Network connectivity** between your app and OginiSearch server
5. **Firewall settings** aren't blocking the connection

### Q: Search results are empty but I have data. What's wrong?

**A:** Common causes:

1. **Data not indexed**: Run `php artisan scout:import "App\Models\YourModel"`
2. **Wrong index name**: Check your model's `searchableAs()` method
3. **Query syntax**: Verify your search query format
4. **Filters too restrictive**: Check your filter conditions

### Q: How do I debug search queries?

**A:** Enable logging in your configuration:

```php
'client' => [
    'debug' => true, // Enable debug logging
    'log_queries' => true, // Log all queries
],
```

Check your Laravel logs for detailed query information.

### Q: I'm getting memory errors during bulk indexing. How do I fix this?

**A:** Reduce batch size and use chunking:

```php
// In config/ogini.php
'performance' => [
    'batch_processing' => [
        'batch_size' => 100, // Reduce from default
    ],
],

// When importing
php artisan scout:import "App\Models\Article" --chunk=100
```

## Advanced Features

### Q: How do I use the event system?

**A:** Listen to search events:

```php
use OginiScoutDriver\Events\SearchCompleted;

// In your EventServiceProvider
protected $listen = [
    SearchCompleted::class => [
        YourSearchListener::class,
    ],
];
```

### Q: How do I manage synonyms?

**A:** Use the Ogini facade:

```php
use OginiScoutDriver\Facades\Ogini;

// Add synonyms
Ogini::addSynonyms('articles', [
    ['car', 'automobile', 'vehicle'],
    ['fast', 'quick', 'rapid'],
]);

// Get current synonyms
$synonyms = Ogini::getSynonyms('articles');
```

### Q: How do I configure stopwords?

**A:** Use the stopword management methods:

```php
// Configure stopwords
Ogini::configureStopwords('articles', ['the', 'a', 'an'], 'en');

// Update stopwords
Ogini::updateStopwords('articles', ['custom', 'stopwords']);
```

## Updates & Maintenance

### Q: How do I check for package updates?

**A:** Use the built-in update checker:

```bash
# Check for updates
php artisan ogini:check-updates

# Check for security updates only
php artisan ogini:check-updates --security-only

# Get JSON output
php artisan ogini:check-updates --json
```

### Q: How do I update to a new version?

**A:** Use Composer:

```bash
# Update to latest version
composer update ogini/oginisearch-laravel-scout

# Update with dependencies
composer update ogini/oginisearch-laravel-scout --with-dependencies
```

Always check the CHANGELOG.md for breaking changes before updating.

### Q: How do I monitor package health?

**A:** The package includes health monitoring:

```php
use OginiScoutDriver\Facades\Ogini;

// Check connection health
$health = Ogini::healthCheck();

// Get performance metrics
$metrics = Ogini::getPerformanceMetrics();
```

## Integration

### Q: Can I use this with other search packages?

**A:** Yes, but you should configure Scout to use only one driver at a time. You can switch drivers per model if needed:

```php
// In your model
public function searchableUsing()
{
    return app(\OginiScoutDriver\OginiEngine::class);
}
```

### Q: How do I integrate with queues?

**A:** Enable queue integration for async operations:

```php
// Enable in config
'async' => [
    'queue_enabled' => true,
    'queue_connection' => 'redis',
    'queue_name' => 'search',
],
```

### Q: Can I use this in a multi-tenant application?

**A:** Yes, configure different indices per tenant:

```php
// In your model
public function searchableAs(): string
{
    return 'tenant_' . auth()->user()->tenant_id . '_articles';
}
```

## Error Handling

### Q: How do I handle search errors gracefully?

**A:** Use try-catch blocks:

```php
try {
    $results = Article::search('query')->get();
} catch (\OginiScoutDriver\Exceptions\OginiException $e) {
    Log::error('Search failed', [
        'message' => $e->getMessage(),
        'context' => $e->getContext(),
    ]);
    
    // Fallback to database search or show error message
    return Article::where('title', 'like', '%query%')->get();
}
```

### Q: What exceptions can be thrown?

**A:** Common exceptions:

- `OginiConnectionException` - Connection issues
- `OginiSearchException` - Search-related errors
- `OginiIndexException` - Indexing problems
- `OginiConfigurationException` - Configuration errors

## Getting Help

### Q: Where can I get more help?

**A:** Multiple support channels:

- **GitHub Issues**: [Report bugs or request features](https://github.com/ogini-search/oginisearch-laravel-scout/issues)
- **Documentation**: [Complete documentation](https://github.com/ogini-search/oginisearch-laravel-scout#readme)
- **Discord**: [Join our community](https://discord.gg/ogini)
- **Stack Overflow**: [Ask questions with the `ogini-search` tag](https://stackoverflow.com/questions/tagged/ogini-search)

### Q: How do I report a bug?

**A:** Use our bug report template on GitHub Issues. Include:

- Package version
- Laravel version
- PHP version
- Steps to reproduce
- Expected vs actual behavior
- Error logs (if any)

### Q: How can I contribute?

**A:** We welcome contributions! See our [CONTRIBUTING.md](../CONTRIBUTING.md) guide for:

- Development setup
- Coding standards
- Testing requirements
- Pull request process

---

**Still have questions?** [Open an issue](https://github.com/ogini-search/oginisearch-laravel-scout/issues/new/choose) and we'll help you out! 