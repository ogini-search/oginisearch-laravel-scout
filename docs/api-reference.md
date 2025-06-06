# API Reference

Complete reference for all public methods and classes in the OginiSearch Laravel Scout Driver.

## Table of Contents

- [OginiEngine](#oginiengine)
- [OginiClient](#oginiclient)
- [Facades](#facades)
- [Performance Classes](#performance-classes)
- [Configuration](#configuration)
- [Events](#events)
- [Exceptions](#exceptions)

## OginiEngine

The main Scout engine implementation.

### update()

Updates documents in the search index.

```php
public function update(Collection $models): void
```

**Parameters:**
- `$models` (Collection): Collection of Eloquent models to update

**Example:**
```php
$posts = BlogPost::where('published', true)->get();
$engine->update($posts);
```

### delete()

Removes documents from the search index.

```php
public function delete(Collection $models): void
```

**Parameters:**
- `$models` (Collection): Collection of Eloquent models to delete

**Example:**
```php
$posts = BlogPost::where('published', false)->get();
$engine->delete($posts);
```

### search()

Performs a search query.

```php
public function search(Builder $builder): array
```

**Parameters:**
- `$builder` (Builder): Laravel Scout search builder

**Returns:**
- `array`: Search results with metadata

**Example:**
```php
$builder = BlogPost::search('Laravel');
$results = $engine->search($builder);
```

### paginate()

Performs a paginated search.

```php
public function paginate(Builder $builder, int $perPage, int $page): array
```

**Parameters:**
- `$builder` (Builder): Laravel Scout search builder
- `$perPage` (int): Number of results per page
- `$page` (int): Page number

**Returns:**
- `array`: Paginated search results

### mapIds()

Maps search results to model IDs.

```php
public function mapIds(array $results): Collection
```

**Parameters:**
- `$results` (array): Raw search results

**Returns:**
- `Collection`: Collection of model IDs

### map()

Maps search results to Eloquent models.

```php
public function map(Builder $builder, array $results, Model $model): Collection
```

**Parameters:**
- `$builder` (Builder): Search builder instance
- `$results` (array): Raw search results
- `$model` (Model): Model instance

**Returns:**
- `Collection`: Collection of Eloquent models

### getTotalCount()

Gets the total count of search results.

```php
public function getTotalCount(array $results): int
```

**Parameters:**
- `$results` (array): Raw search results

**Returns:**
- `int`: Total number of results

### flush()

Removes all documents for a model from the index.

```php
public function flush(Model $model): void
```

**Parameters:**
- `$model` (Model): Model instance

**Example:**
```php
$engine->flush(new BlogPost());
```

### createIndex()

Creates a new search index.

```php
public function createIndex(string $name, array $options = []): array
```

**Parameters:**
- `$name` (string): Index name
- `$options` (array): Index configuration options

**Returns:**
- `array`: Index creation response

### deleteIndex()

Deletes a search index.

```php
public function deleteIndex(string $name): array
```

**Parameters:**
- `$name` (string): Index name

**Returns:**
- `array`: Index deletion response

## OginiClient

Low-level client for OginiSearch API.

### __construct()

Creates a new client instance.

```php
public function __construct(string $baseUrl, string $apiKey, array $config = [])
```

**Parameters:**
- `$baseUrl` (string): Base URL of OginiSearch API
- `$apiKey` (string): API key for authentication
- `$config` (array): Additional configuration options

### createIndex()

Creates a new index.

```php
public function createIndex(string $indexName, array $configuration): array
```

**Parameters:**
- `$indexName` (string): Name of the index
- `$configuration` (array): Index configuration

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

**Example:**
```php
$client->createIndex('blog_posts', [
    'mappings' => [
        'properties' => [
            'title' => ['type' => 'text'],
            'content' => ['type' => 'text']
        ]
    ]
]);
```

### getIndex()

Retrieves index information.

```php
public function getIndex(string $indexName): array
```

**Parameters:**
- `$indexName` (string): Name of the index

**Returns:**
- `array`: Index information

**Throws:**
- `OginiException`: On API errors

### deleteIndex()

Deletes an index.

```php
public function deleteIndex(string $indexName): array
```

**Parameters:**
- `$indexName` (string): Name of the index

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### listIndices()

Lists all indices.

```php
public function listIndices(?string $status = null): array
```

**Parameters:**
- `$status` (string|null): Optional status filter

**Returns:**
- `array`: List of indices

**Throws:**
- `OginiException`: On API errors

### updateIndexSettings()

Updates index settings.

```php
public function updateIndexSettings(string $indexName, array $settings): array
```

**Parameters:**
- `$indexName` (string): Name of the index
- `$settings` (array): New settings

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### indexDocument()

Indexes a single document.

```php
public function indexDocument(string $indexName, array $document, ?string $documentId = null): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$document` (array): Document data
- `$documentId` (string|null): Optional document ID

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### getDocument()

Retrieves a document by ID.

```php
public function getDocument(string $indexName, string $documentId): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$documentId` (string): Document ID

**Returns:**
- `array`: Document data

**Throws:**
- `OginiException`: On API errors

### updateDocument()

Updates an existing document.

```php
public function updateDocument(string $indexName, string $documentId, array $document): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$documentId` (string): Document ID
- `$document` (array): Updated document data

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### deleteDocument()

Deletes a document.

```php
public function deleteDocument(string $indexName, string $documentId): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$documentId` (string): Document ID

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### bulkIndexDocuments()

Indexes multiple documents in a single request.

```php
public function bulkIndexDocuments(string $indexName, array $documents): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$documents` (array): Array of documents to index

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

**Example:**
```php
$client->bulkIndexDocuments('blog_posts', [
    ['id' => '1', 'document' => ['title' => 'Post 1']],
    ['id' => '2', 'document' => ['title' => 'Post 2']]
]);
```

### deleteByQuery()

Deletes documents matching a query.

```php
public function deleteByQuery(string $indexName, array $query): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$query` (array): Query to match documents

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### listDocuments()

Lists documents in an index.

```php
public function listDocuments(string $indexName, int $limit = 10, int $offset = 0, ?string $filter = null): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$limit` (int): Number of documents to return
- `$offset` (int): Starting offset
- `$filter` (string|null): Optional filter

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### search()

Performs a search query.

```php
public function search(string $indexName, array $searchQuery, ?int $size = null, ?int $from = null): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$searchQuery` (array): Search query parameters
- `$size` (int|null): Number of results to return
- `$from` (int|null): Starting offset

**Returns:**
- `array`: Search results

**Throws:**
- `OginiException`: On API errors

**Example:**
```php
$results = $client->search('blog_posts', [
    'query' => ['match' => ['title' => 'Laravel']],
    'filter' => ['term' => ['published' => true]],
    'sort' => ['published_at' => 'desc']
]);
```

### getSuggestions()

Gets search suggestions.

```php
public function getSuggestions(string $indexName, string $text, ?string $field = null, ?int $size = null): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$text` (string): Text to get suggestions for
- `$field` (string|null): Field to get suggestions from
- `$size` (int|null): Number of suggestions to return

**Returns:**
- `array`: Suggestions

**Throws:**
- `OginiException`: On API errors

### getQuerySuggestions()

Gets query suggestions for autocomplete.

```php
public function getQuerySuggestions(string $indexName, string $text, array $options = []): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$text` (string): Partial text input
- `$options` (array): Suggestion options

**Returns:**
- `array`: Suggestion results

**Throws:**
- `OginiException`: On API errors

### getAutocompleteSuggestions()

Gets autocomplete suggestions.

```php
public function getAutocompleteSuggestions(string $indexName, string $prefix, array $options = []): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$prefix` (string): Query prefix
- `$options` (array): Autocomplete options

**Returns:**
- `array`: Autocomplete results

**Throws:**
- `OginiException`: On API errors

### addSynonyms()

Adds synonym groups to an index.

```php
public function addSynonyms(string $indexName, array $synonyms): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$synonyms` (array): Array of synonym groups

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### getSynonyms()

Gets synonyms for an index.

```php
public function getSynonyms(string $indexName): array
```

**Parameters:**
- `$indexName` (string): Index name

**Returns:**
- `array`: Synonym data

**Throws:**
- `OginiException`: On API errors

### updateSynonyms()

Updates synonyms for an index.

```php
public function updateSynonyms(string $indexName, array $synonyms): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$synonyms` (array): Updated synonym groups

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### deleteSynonyms()

Deletes synonyms for an index.

```php
public function deleteSynonyms(string $indexName): array
```

**Parameters:**
- `$indexName` (string): Index name

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### addStopwords()

Adds stopwords to an index.

```php
public function addStopwords(string $indexName, array $stopwords): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$stopwords` (array): Array of stopwords

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### getStopwords()

Gets stopwords for an index.

```php
public function getStopwords(string $indexName): array
```

**Parameters:**
- `$indexName` (string): Index name

**Returns:**
- `array`: Stopword data

**Throws:**
- `OginiException`: On API errors

### updateStopwords()

Updates stopwords for an index.

```php
public function updateStopwords(string $indexName, array $stopwords): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$stopwords` (array): Updated stopwords

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

### deleteStopwords()

Deletes stopwords for an index.

```php
public function deleteStopwords(string $indexName): array
```

**Parameters:**
- `$indexName` (string): Index name

**Returns:**
- `array`: Response data

**Throws:**
- `OginiException`: On API errors

## Facades

### Ogini Facade

Provides convenient access to OginiClient methods.

```php
use OginiScoutDriver\Facades\Ogini;

// Search
$results = Ogini::search('blog_posts', ['query' => ['match_all' => []]]);

// Get suggestions
$suggestions = Ogini::getQuerySuggestions('blog_posts', 'larav');

// Index management
Ogini::createIndex('new_index', $configuration);
Ogini::deleteIndex('old_index');

// Document operations
Ogini::indexDocument('blog_posts', ['title' => 'New Post']);
Ogini::bulkIndexDocuments('blog_posts', $documents);

// Synonym management
Ogini::addSynonyms('blog_posts', [['car', 'automobile']]);
```

## Performance Classes

### QueryCache

Handles query result caching.

#### cacheSearch()

Caches search results.

```php
public function cacheSearch(string $indexName, array $searchQuery, array $options, callable $searchCallback): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$searchQuery` (array): Search query
- `$options` (array): Search options
- `$searchCallback` (callable): Callback to execute search

**Returns:**
- `array`: Cached or fresh search results

#### cacheSuggestions()

Caches suggestion results.

```php
public function cacheSuggestions(string $indexName, string $text, ?string $field, int $size, callable $suggestionCallback): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$text` (string): Suggestion text
- `$field` (string|null): Field name
- `$size` (int): Number of suggestions
- `$suggestionCallback` (callable): Callback to execute suggestions

**Returns:**
- `array`: Cached or fresh suggestions

#### invalidateCache()

Invalidates cache for an index.

```php
public function invalidateCache(string $indexName): bool
```

**Parameters:**
- `$indexName` (string): Index name

**Returns:**
- `bool`: Success status

### ConnectionPool

Manages HTTP connection pooling.

#### getConnection()

Gets a connection from the pool.

```php
public function getConnection(int $connectionId): Client
```

**Parameters:**
- `$connectionId` (int): Connection identifier

**Returns:**
- `Client`: Guzzle HTTP client instance

#### releaseConnection()

Releases a connection back to the pool.

```php
public function releaseConnection(int $connectionId): void
```

**Parameters:**
- `$connectionId` (int): Connection identifier

#### executeParallel()

Executes multiple requests in parallel.

```php
public function executeParallel(array $requests, int $concurrency = 5): array
```

**Parameters:**
- `$requests` (array): Array of request configurations
- `$concurrency` (int): Maximum concurrent requests

**Returns:**
- `array`: Array of responses

### BatchProcessor

Handles batch operations efficiently.

#### processBatch()

Processes a batch of models.

```php
public function processBatch(string $indexName, Collection $models, ?callable $progressCallback = null): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$models` (Collection): Collection of models
- `$progressCallback` (callable|null): Optional progress callback

**Returns:**
- `array`: Processing results

#### deleteBatch()

Deletes a batch of models.

```php
public function deleteBatch(string $indexName, Collection $models, ?callable $progressCallback = null): array
```

**Parameters:**
- `$indexName` (string): Index name
- `$models` (Collection): Collection of models
- `$progressCallback` (callable|null): Optional progress callback

**Returns:**
- `array`: Deletion results

## Configuration

### Configuration Methods

#### get()

Gets a configuration value.

```php
config('ogini.client.timeout')
```

#### set()

Sets a configuration value at runtime.

```php
config(['ogini.client.timeout' => 60]);
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `OGINI_BASE_URL` | OginiSearch API base URL | `http://localhost:3000` |
| `OGINI_API_KEY` | API authentication key | `null` |
| `OGINI_TIMEOUT` | Request timeout in seconds | `30` |
| `OGINI_RETRY_ATTEMPTS` | Number of retry attempts | `3` |
| `OGINI_CACHE_ENABLED` | Enable query caching | `true` |
| `OGINI_BATCH_SIZE` | Batch processing size | `100` |

## Events

### SearchPerformed

Fired when a search is performed.

```php
use OginiScoutDriver\Events\SearchPerformed;

Event::listen(SearchPerformed::class, function ($event) {
    Log::info('Search performed', [
        'index' => $event->indexName,
        'query' => $event->query,
        'results_count' => $event->resultsCount,
        'duration' => $event->duration
    ]);
});
```

**Properties:**
- `$indexName` (string): Index name
- `$query` (array): Search query
- `$resultsCount` (int): Number of results
- `$duration` (float): Query duration in milliseconds

### DocumentIndexed

Fired when documents are indexed.

```php
use OginiScoutDriver\Events\DocumentIndexed;

Event::listen(DocumentIndexed::class, function ($event) {
    Log::info('Documents indexed', [
        'index' => $event->indexName,
        'count' => $event->documentCount
    ]);
});
```

**Properties:**
- `$indexName` (string): Index name
- `$documentCount` (int): Number of documents indexed

### IndexCreated

Fired when an index is created.

```php
use OginiScoutDriver\Events\IndexCreated;

Event::listen(IndexCreated::class, function ($event) {
    Log::info('Index created', [
        'index' => $event->indexName,
        'configuration' => $event->configuration
    ]);
});
```

**Properties:**
- `$indexName` (string): Index name
- `$configuration` (array): Index configuration

## Exceptions

### OginiException

Base exception for all OginiSearch errors.

```php
use OginiScoutDriver\Exceptions\OginiException;

try {
    $client->search('invalid_index', []);
} catch (OginiException $e) {
    Log::error('Search failed', [
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
}
```

**Methods:**
- `getMessage()`: Error message
- `getCode()`: HTTP status code

### ConnectionException

Thrown when connection to OginiSearch fails.

```php
use OginiScoutDriver\Exceptions\ConnectionException;

try {
    $client->getIndex('test');
} catch (ConnectionException $e) {
    // Handle connection error
    Log::error('Connection failed: ' . $e->getMessage());
}
```

### ValidationException

Thrown when request validation fails.

```php
use OginiScoutDriver\Exceptions\ValidationException;

try {
    $client->createIndex('', []); // Invalid empty name
} catch (ValidationException $e) {
    // Handle validation error
    Log::error('Validation failed: ' . $e->getMessage());
}
```

### IndexNotFoundException

Thrown when an index is not found.

```php
use OginiScoutDriver\Exceptions\IndexNotFoundException;

try {
    $client->getIndex('nonexistent');
} catch (IndexNotFoundException $e) {
    // Handle missing index
    Log::warning('Index not found: ' . $e->getMessage());
}
```

## Usage Examples

### Basic Search

```php
use App\Models\BlogPost;

// Simple search
$posts = BlogPost::search('Laravel')->get();

// Search with filters
$posts = BlogPost::search('tutorial')
    ->options([
        'filter' => ['category' => 'Programming'],
        'sort' => ['published_at' => 'desc']
    ])
    ->paginate(15);
```

### Advanced Search

```php
use OginiScoutDriver\Facades\Ogini;

// Complex search with multiple criteria
$results = Ogini::search('blog_posts', [
    'query' => [
        'bool' => [
            'must' => [
                ['match' => ['title' => 'Laravel']]
            ],
            'filter' => [
                ['term' => ['published' => true]]
            ]
        ]
    ],
    'highlight' => [
        'fields' => ['title', 'content']
    ]
]);
```

### Batch Operations

```php
use OginiScoutDriver\Performance\BatchProcessor;

$processor = app(BatchProcessor::class);

// Index large number of models
$posts = BlogPost::where('published', true)->get();
$results = $processor->processBatch('blog_posts', $posts, function ($processed, $total) {
    echo "Processed {$processed}/{$total} posts\n";
});
```

### Error Handling

```php
use OginiScoutDriver\Exceptions\OginiException;
use OginiScoutDriver\Facades\Ogini;

try {
    $results = Ogini::search('blog_posts', $query);
} catch (OginiException $e) {
    // Log the error
    Log::error('Search failed', [
        'error' => $e->getMessage(),
        'query' => $query,
        'status_code' => $e->getCode()
    ]);
    
    // Return empty results or show error page
    return response()->json(['error' => 'Search temporarily unavailable'], 503);
}
```

This completes the API reference for the OginiSearch Laravel Scout Driver. 