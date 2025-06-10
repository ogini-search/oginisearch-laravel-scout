# OginiSearch Scout Driver - Bulk Processing Guide

## 🚀 Enhanced Bulk Processing Features

This package now includes revolutionary bulk processing capabilities with dynamic model discovery that works universally with any Laravel application.

### ✅ What's New

- **Dynamic Model Discovery**: Automatically finds all searchable models in any Laravel app
- **Universal Compatibility**: Works with standard, legacy, and custom application structures  
- **BatchProcessor Class**: Handles bulk API calls with configurable batch sizes
- **BulkImportCommand**: Intelligent Artisan command with flexible model resolution
- **BulkScoutImportJob**: Queue jobs for chunked processing with dynamic model support
- **Enhanced OginiEngine**: Automatic bulk processing for Scout operations

---

## 📊 Performance Improvements

| Operation | Before (Individual) | After (Bulk) | Improvement |
|-----------|-------------------|--------------|-------------|
| 1K records | 1K API calls | 2 API calls | 500x fewer calls |
| 10K records | 10K API calls | 20 API calls | 500x fewer calls |
| 100K records | 100K API calls | 200 API calls | 500x fewer calls |

**Expected Performance:**
- 🔥 **90% reduction** in processing time
- 🌐 **500x fewer** HTTP requests
- 💾 **Reduced** queue overhead
- ⚡ **Improved** memory efficiency

---

## ⚙️ Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Basic OginiSearch settings
OGINI_BASE_URL=http://localhost:3000
OGINI_API_KEY=your-api-key

# Bulk Processing Settings
OGINI_BATCH_ENABLED=true
OGINI_BATCH_SIZE=500
OGINI_BATCH_TIMEOUT=120
OGINI_BATCH_RETRY_ATTEMPTS=3
OGINI_BATCH_DELAY=100

# Queue Settings for Bulk Processing
OGINI_QUEUE_CONNECTION=redis
OGINI_QUEUE_NAME=ogini-bulk
OGINI_QUEUE_TIMEOUT=600
OGINI_QUEUE_RETRY_TIMES=3
```

### Model Setup

Your models need to use the Scout `Searchable` trait:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Business extends Model
{
    use Searchable;

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'location' => $this->location,
        ];
    }

    public function searchableAs()
    {
        return 'businesses';
    }
}
```

---

## 🛠️ Usage

### 1. Standard Scout Operations (Automatic Bulk)

The enhanced OginiEngine automatically uses bulk processing for standard Scout operations:

```php
// These now automatically use bulk processing when OGINI_BATCH_ENABLED=true
Business::take(1000)->get()->searchable();  // Bulk index
Business::where('status', 'inactive')->unsearchable();  // Bulk delete
```

### 2. Dynamic Model Discovery & Import Command

The revolutionary `ogini:bulk-import` command now works with any Laravel application through automatic model discovery:

#### Discover Available Models

```bash
# List all searchable models in your application
php artisan ogini:bulk-import --list

# Validate a specific model configuration
php artisan ogini:bulk-import User --validate
```

#### Universal Model Resolution

The command accepts multiple model naming formats:

```bash
# Short name (automatically resolved)
php artisan ogini:bulk-import User

# Full class name  
php artisan ogini:bulk-import "App\Models\User"

# Legacy namespace
php artisan ogini:bulk-import "App\User"

# Custom namespace
php artisan ogini:bulk-import "Custom\Namespace\Product"
```

#### Basic Usage

```bash
# Import with flexible options
php artisan ogini:bulk-import User --limit=1000

# Dry run to test
php artisan ogini:bulk-import Product --limit=10 --dry-run

# Flush existing index first  
php artisan ogini:bulk-import Article --force
```

#### Queue-Based Processing

```bash
# Queue bulk import jobs
php artisan ogini:bulk-import Business --queue --batch-size=1000

# Process the queue
php artisan queue:work redis --queue=ogini-bulk --timeout=600
```

#### Advanced Options

```bash
# Custom batch and chunk sizes
php artisan ogini:bulk-import Business \
  --batch-size=500 \
  --chunk-size=2000 \
  --limit=50000

# Queue with custom settings
php artisan ogini:bulk-import Business \
  --queue \
  --batch-size=1000 \
  --chunk-size=5000
```

### 3. Programmatic Bulk Operations

#### Using BatchProcessor Directly

```php
<?php

use OginiScoutDriver\Performance\BatchProcessor;
use OginiScoutDriver\Client\OginiClient;

$client = app(OginiClient::class);
$batchProcessor = new BatchProcessor($client, [
    'batch_size' => 500,
    'delay_between_batches' => 100,
]);

$businesses = Business::take(1000)->get();
$result = $batchProcessor->bulkIndex('businesses', $businesses);

echo "Processed: {$result['processed']}/{$result['total']} ";
echo "Success Rate: {$result['success_rate']}%";
```

#### Using Queue Jobs

```php
<?php

use OginiScoutDriver\Jobs\BulkScoutImportJob;

// Dispatch bulk import job
$businessIds = Business::where('status', 'active')->pluck('id')->toArray();
BulkScoutImportJob::dispatch($businessIds, 'Business', 500);
```

---

## 🔧 Advanced Features

### Zero Configuration Required

The dynamic model discovery system eliminates the need for manual configuration:

```php
// ❌ OLD WAY - Required hardcoded mappings
private $models = [
    'Product' => \App\Models\Product::class,
    'User' => \App\Models\User::class,
    // Had to manually add every model...
];

// ✅ NEW WAY - Automatic discovery
// No configuration needed! 
// The system automatically finds all models with Searchable trait
```

### Universal Application Support

The system adapts to any Laravel application structure:

```php
// Standard Laravel 8+ structure
App\Models\User
App\Models\Product

// Legacy Laravel structure  
App\User
App\Product

// Custom namespace structure
Custom\Models\MyModel
Company\Entities\BusinessModel

// Multi-module structure
Modules\Blog\Models\Post
Modules\Shop\Models\Product
];
```

### Custom Batch Configuration

```php
<?php

// In your service provider or configuration
$customConfig = [
    'batch_size' => 1000,
    'timeout' => 180,
    'retry_attempts' => 5,
    'delay_between_batches' => 50,
];

$batchProcessor = new BatchProcessor($client, $customConfig);
```

---

## 📈 Monitoring & Debugging

### Command Output

The bulk import command provides detailed progress information:

```
🚀 Starting bulk import for Business
✅ OginiSearch server is accessible
📊 Processing 10000 records
████████████████████████████████████████ 10000/10000 [====] 100%

✅ Import completed!
📈 Results:
   - Total processed: 10000
   - Successful: 9998
   - Errors: 2
   - Duration: 45.3 seconds
   - Throughput: 220.75 docs/second
```

### Error Handling

Bulk operations include comprehensive error handling and logging:

```php
// Check results for errors
$result = $batchProcessor->bulkIndex('businesses', $businesses);

if (!empty($result['errors'])) {
    foreach ($result['errors'] as $error) {
        Log::error('Bulk indexing error', [
            'batch_index' => $error['batch_index'],
            'error' => $error['error'],
            'timestamp' => $error['timestamp']
        ]);
    }
}
```

### Queue Monitoring

Monitor bulk processing with Laravel Horizon or queue workers:

```bash
# Monitor queue status
php artisan queue:work redis --queue=ogini-bulk --timeout=600 --tries=3

# With Horizon
php artisan horizon:start
```

---

## 🔍 Verification

### Test Bulk Processing

```bash
# Test with small dataset
php artisan ogini:bulk-import Business --limit=5 --dry-run

# Verify Scout integration
SCOUT_QUEUE=false php artisan scout:import "App\\Models\\Business"
```

### Performance Verification

```php
<?php

// Measure performance improvement
$startTime = microtime(true);

// Your bulk operation
Business::take(1000)->get()->searchable();

$duration = microtime(true) - $startTime;
echo "Bulk operation took: {$duration} seconds";
```

---

## 🚦 Best Practices

### 1. **Batch Size Optimization**
- Start with 500 documents per batch
- Increase to 1000-2000 for larger documents
- Monitor memory usage and API response times

### 2. **Queue Configuration**
- Use Redis for better performance
- Set appropriate timeouts (600+ seconds)
- Monitor failed jobs and retry logic

### 3. **Error Handling**
- Always check batch results for errors
- Implement proper logging and monitoring
- Use dry-run mode for testing

### 4. **Resource Management**
- Schedule bulk imports during low-traffic periods
- Monitor server resources during large imports
- Use chunked processing for very large datasets

---

## 🆘 Troubleshooting

### Common Issues

**Queue jobs failing:**
```bash
# Check queue worker status
php artisan queue:work --timeout=600 --verbose

# Clear failed jobs
php artisan queue:clear --queue=ogini-bulk
```

**Memory issues with large datasets:**
```bash
# Use smaller batch sizes
php artisan ogini:bulk-import Business --batch-size=100 --chunk-size=500
```

**Connection timeouts:**
```env
# Increase timeouts
OGINI_BATCH_TIMEOUT=300
OGINI_QUEUE_TIMEOUT=900
```

### Debug Mode

```bash
# Enable verbose logging
LOG_LEVEL=debug php artisan ogini:bulk-import Business --limit=10
```

---

## 📚 API Reference

### BatchProcessor Methods

```php
// Bulk index documents
$result = $batchProcessor->bulkIndex(string $indexName, Collection $models): array

// Bulk delete documents
$result = $batchProcessor->bulkDelete(string $indexName, Collection $models): array

// Get/update configuration
$config = $batchProcessor->getConfig(): array
$batchProcessor->updateConfig(array $config): void
```

### Command Options

```bash
ogini:bulk-import {model}
  {--limit=0}           # Max records (0 = all)
  {--batch-size=500}    # Documents per API call
  {--chunk-size=1000}   # Records per DB query
  {--queue}             # Use queue processing
  {--force}             # Flush index first
  {--dry-run}           # Test without indexing
```

---

This enhanced bulk processing makes the OginiSearch Scout driver suitable for production applications with large datasets while maintaining all existing functionality. 