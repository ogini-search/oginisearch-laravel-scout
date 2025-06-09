# Troubleshooting Guide

This guide helps you diagnose and resolve common issues with the OginiSearch Laravel Scout Driver.

## Quick Diagnostics

### Health Check Command

First, run our built-in health check to identify common issues:

```bash
php artisan ogini:health-check
```

This command will check:
- ✅ OginiSearch server connectivity
- ✅ Configuration validity
- ✅ Index accessibility
- ✅ Authentication status
- ✅ Performance metrics

## Installation Issues

### Issue: Package Installation Fails

**Symptoms:**
- Composer install/update errors
- Dependency conflicts
- Version compatibility issues

**Solutions:**

1. **Check PHP Version:**
   ```bash
   php -v
   # Must be 8.2 or higher
   ```

2. **Check Laravel Version:**
   ```bash
   php artisan --version
   # Must be 10.0 or higher
   ```

3. **Clear Composer Cache:**
   ```bash
   composer clear-cache
   composer install --no-cache
   ```

4. **Update Composer:**
   ```bash
   composer self-update
   ```

5. **Check for Conflicting Packages:**
   ```bash
   composer why-not ogini/oginisearch-laravel-scout
   ```

### Issue: Configuration Publishing Fails

**Symptoms:**
- `vendor:publish` command fails
- Configuration file not created

**Solutions:**

1. **Clear Laravel Caches:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan view:clear
   ```

2. **Manual Configuration:**
   ```bash
   # Copy configuration manually
   cp vendor/ogini/oginisearch-laravel-scout/config/ogini.php config/
   ```

3. **Check File Permissions:**
   ```bash
   # Ensure config directory is writable
   chmod 755 config/
   ```

## Connection Issues

### Issue: Cannot Connect to OginiSearch Server

**Symptoms:**
- Connection timeout errors
- "Connection refused" messages
- HTTP 500 errors during search

**Diagnostic Steps:**

1. **Test Server Connectivity:**
   ```bash
   # Test if server is reachable
   curl -I http://your-ogini-server:3000/health
   
   # Expected: HTTP 200 OK
   ```

2. **Check Configuration:**
   ```php
   // In config/ogini.php or .env
   OGINI_BASE_URL=http://localhost:3000  // ✅ Correct
   OGINI_BASE_URL=localhost:3000         // ❌ Missing protocol
   OGINI_BASE_URL=http://localhost:3000/ // ❌ Trailing slash
   ```

3. **Verify Network Access:**
   ```bash
   # Test from your server
   telnet your-ogini-server 3000
   ```

**Solutions:**

1. **Fix Base URL:**
   ```env
   OGINI_BASE_URL=http://your-server:3000
   ```

2. **Check Firewall:**
   ```bash
   # Allow port 3000
   sudo ufw allow 3000
   ```

3. **Verify OginiSearch is Running:**
   ```bash
   # Check if OginiSearch process is running
   ps aux | grep ogini
   ```

### Issue: Authentication Failures

**Symptoms:**
- HTTP 401 Unauthorized
- "Invalid API key" errors
- Authentication-related exceptions

**Solutions:**

1. **Verify API Key:**
   ```env
   OGINI_API_KEY=your-actual-api-key-here
   ```

2. **Check Key Format:**
   ```php
   // Keys should not have quotes in .env
   OGINI_API_KEY=abc123def456  // ✅ Correct
   OGINI_API_KEY="abc123def456" // ❌ Quoted
   ```

3. **Test Authentication:**
   ```bash
   curl -H "Authorization: Bearer your-api-key" \
        http://your-server:3000/health
   ```

## Search Issues

### Issue: Search Returns No Results

**Symptoms:**
- Empty search results despite having data
- Search works in OginiSearch directly but not through Laravel

**Diagnostic Steps:**

1. **Check if Data is Indexed:**
   ```bash
   php artisan scout:status
   ```

2. **Verify Index Name:**
   ```php
   // In your model
   public function searchableAs(): string
   {
       return 'your_index_name'; // Must match OginiSearch index
   }
   ```

3. **Test Direct Search:**
   ```bash
   curl -X POST http://your-server:3000/api/indices/your_index/search \
        -H "Content-Type: application/json" \
        -d '{"query": {"match": {"title": "test"}}}'
   ```

**Solutions:**

1. **Re-index Your Data:**
   ```bash
   php artisan scout:flush "App\Models\YourModel"
   php artisan scout:import "App\Models\YourModel"
   ```

2. **Check Searchable Array:**
   ```php
   public function toSearchableArray(): array
   {
       return [
           'title' => $this->title,
           'content' => $this->content,
           // Ensure fields exist and have data
       ];
   }
   ```

3. **Verify Query Syntax:**
   ```php
   // Simple search
   $results = Article::search('test')->get();
   
   // Complex search
   $results = Article::search('test')
       ->options([
           'query' => ['match' => ['title' => 'test']]
       ])
       ->get();
   ```

### Issue: Search is Slow

**Symptoms:**
- Long response times
- Timeout errors
- Poor performance

**Diagnostic Steps:**

1. **Check Query Complexity:**
   ```bash
   # Enable query logging
   php artisan ogini:debug --enable-query-log
   ```

2. **Monitor Performance:**
   ```php
   use OginiScoutDriver\Facades\Ogini;
   
   $metrics = Ogini::getPerformanceMetrics();
   dd($metrics);
   ```

**Solutions:**

1. **Enable Caching:**
   ```php
   // In config/ogini.php
   'performance' => [
       'cache' => [
           'enabled' => true,
           'driver' => 'redis', // Use Redis for better performance
           'query_ttl' => 300,
       ],
   ],
   ```

2. **Optimize Connection Pool:**
   ```php
   'performance' => [
       'connection_pool' => [
           'enabled' => true,
           'pool_size' => 10, // Increase pool size
       ],
   ],
   ```

3. **Use Appropriate Filters:**
   ```php
   // Filter early to reduce result set
   $results = Article::search('query')
       ->options([
           'filter' => ['published' => true], // Reduce dataset
           'size' => 20, // Limit results
       ])
       ->get();
   ```

## Indexing Issues

### Issue: Documents Not Indexing

**Symptoms:**
- `scout:import` command fails
- New/updated models not appearing in search
- Indexing errors in logs

**Diagnostic Steps:**

1. **Check Scout Configuration:**
   ```env
   SCOUT_DRIVER=ogini  # Must be set to 'ogini'
   ```

2. **Verify Model Setup:**
   ```php
   use Laravel\Scout\Searchable;
   
   class Article extends Model
   {
       use Searchable; // ✅ Trait must be present
   }
   ```

3. **Test Manual Indexing:**
   ```php
   $article = Article::find(1);
   $article->searchable(); // Manual index
   ```

**Solutions:**

1. **Fix Scout Driver:**
   ```bash
   php artisan config:clear
   # Ensure SCOUT_DRIVER=ogini in .env
   ```

2. **Check Queue Configuration:**
   ```php
   // If using queues
   'queue' => [
       'enabled' => true,
       'connection' => 'redis',
   ],
   ```

3. **Batch Import with Chunking:**
   ```bash
   php artisan scout:import "App\Models\Article" --chunk=100
   ```

### Issue: Bulk Indexing Memory Errors

**Symptoms:**
- PHP memory limit exceeded
- Process killed during import
- Incomplete indexing

**Solutions:**

1. **Increase Memory Limit:**
   ```bash
   php -d memory_limit=512M artisan scout:import "App\Models\Article"
   ```

2. **Reduce Batch Size:**
   ```php
   // In config/ogini.php
   'performance' => [
       'batch_processing' => [
           'batch_size' => 50, // Reduce from default
       ],
   ],
   ```

3. **Use Chunked Import:**
   ```bash
   php artisan scout:import "App\Models\Article" --chunk=50
   ```

## Performance Issues

### Issue: High Memory Usage

**Symptoms:**
- Memory leaks during search operations
- Gradual memory increase
- Out of memory errors

**Solutions:**

1. **Enable Connection Pooling:**
   ```php
   'performance' => [
       'connection_pool' => [
           'enabled' => true,
           'idle_timeout' => 30, // Close idle connections
       ],
   ],
   ```

2. **Optimize Searchable Arrays:**
   ```php
   public function toSearchableArray(): array
   {
       return [
           'id' => $this->id,
           'title' => $this->title,
           // Only include necessary fields
           // Avoid loading relationships unnecessarily
       ];
   }
   ```

3. **Use Pagination:**
   ```php
   // Instead of ->get()
   $results = Article::search('query')->paginate(20);
   ```

### Issue: Too Many HTTP Connections

**Symptoms:**
- "Too many open files" errors
- Connection pool exhaustion
- HTTP connection errors

**Solutions:**

1. **Configure Connection Limits:**
   ```php
   'performance' => [
       'connection_pool' => [
           'pool_size' => 5, // Reduce if needed
           'connection_timeout' => 5,
           'idle_timeout' => 30,
       ],
   ],
   ```

2. **Use Async Operations:**
   ```php
   use OginiScoutDriver\Facades\AsyncOgini;
   
   // Batch multiple operations
   $promises = [];
   foreach ($queries as $query) {
       $promises[] = AsyncOgini::searchAsync('articles', $query);
   }
   
   $results = AsyncOgini::waitForAll($promises);
   ```

## Configuration Issues

### Issue: Configuration Not Loading

**Symptoms:**
- Default values being used instead of custom config
- Environment variables not recognized
- Configuration changes not taking effect

**Solutions:**

1. **Clear Configuration Cache:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

2. **Check Environment File:**
   ```bash
   # Ensure .env file exists and is readable
   ls -la .env
   cat .env | grep OGINI
   ```

3. **Verify Configuration Syntax:**
   ```php
   // Test configuration loading
   dd(config('ogini'));
   ```

### Issue: Invalid Configuration Values

**Symptoms:**
- Type errors
- Validation failures
- Unexpected behavior

**Solutions:**

1. **Validate Configuration:**
   ```bash
   php artisan ogini:config:validate
   ```

2. **Check Data Types:**
   ```php
   // In config/ogini.php
   'timeout' => 30,        // ✅ Integer
   'timeout' => '30',      // ❌ String
   'enabled' => true,      // ✅ Boolean
   'enabled' => 'true',    // ❌ String
   ```

## Debugging Tools

### Enable Debug Mode

```php
// In config/ogini.php
'debug' => [
    'enabled' => true,
    'log_queries' => true,
    'log_responses' => true,
    'log_performance' => true,
],
```

### Query Logging

```bash
# Enable query logging
php artisan ogini:debug --enable-query-log

# View logs
tail -f storage/logs/laravel.log | grep ogini
```

### Performance Profiling

```php
use OginiScoutDriver\Facades\Ogini;

// Get detailed performance metrics
$metrics = Ogini::getPerformanceMetrics();

// Check connection health
$health = Ogini::healthCheck();

// Get cache statistics
$cacheStats = Ogini::getCacheStatistics();
```

## Getting Help

If you're still experiencing issues after trying these solutions:

1. **Check the FAQ:** [docs/FAQ.md](FAQ.md)
2. **Search existing issues:** [GitHub Issues](https://github.com/ogini-search/oginisearch-laravel-scout/issues)
3. **Create a bug report:** Use our [bug report template](https://github.com/ogini-search/oginisearch-laravel-scout/issues/new?template=bug_report.yml)
4. **Join our Discord:** [Community Support](https://discord.gg/ogini)

### When Reporting Issues

Please include:

- **Package version:** `composer show ogini/oginisearch-laravel-scout`
- **Laravel version:** `php artisan --version`
- **PHP version:** `php -v`
- **Error messages:** Full stack traces
- **Configuration:** Relevant config (remove sensitive data)
- **Steps to reproduce:** Detailed reproduction steps

### Emergency Support

For production issues requiring immediate attention:

- **Email:** support@oginisearch.com
- **Priority:** Mark as "Production Issue"
- **Include:** Impact assessment and urgency level

---

**Remember:** Most issues can be resolved by checking configuration, clearing caches, and ensuring proper connectivity. Start with the basics before diving into complex debugging. 