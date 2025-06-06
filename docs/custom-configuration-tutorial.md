# Custom Configuration Tutorial

This tutorial covers advanced configuration options for the OginiSearch Laravel Scout Driver, helping you fine-tune performance, security, and functionality for your specific use case.

## Table of Contents

- [Configuration Overview](#configuration-overview)
- [Environment-Specific Configuration](#environment-specific-configuration)
- [Performance Optimization](#performance-optimization)
- [Connection Configuration](#connection-configuration)
- [Cache Configuration](#cache-configuration)
- [Security Configuration](#security-configuration)
- [Advanced Examples](#advanced-examples)

## Configuration Overview

The OginiSearch driver offers extensive configuration options through three main sources:

1. **Environment variables** (`.env` file)
2. **Configuration files** (`config/ogini.php`, `config/scout.php`)
3. **Runtime configuration** (dynamic settings)

### Configuration Hierarchy

```
Runtime Configuration (highest priority)
    ↓
config/ogini.php
    ↓
Environment Variables (.env)
    ↓
Default Values (lowest priority)
```

## Environment-Specific Configuration

### Development Environment

Create a development-optimized configuration:

```env
# .env.development
SCOUT_DRIVER=ogini

# OginiSearch Development Settings
OGINI_BASE_URL=http://localhost:3000
OGINI_API_KEY=dev-api-key

# Performance (relaxed for development)
OGINI_TIMEOUT=60
OGINI_RETRY_ATTEMPTS=1
OGINI_RETRY_DELAY=500

# Debugging
OGINI_DEBUG_MODE=true
OGINI_LOG_QUERIES=true
OGINI_LOG_LEVEL=debug

# Cache (disabled for development)
OGINI_CACHE_ENABLED=false
OGINI_QUERY_CACHE_TTL=0
```

### Production Environment

```env
# .env.production
SCOUT_DRIVER=ogini

# OginiSearch Production Settings
OGINI_BASE_URL=https://search.yourapp.com
OGINI_API_KEY=prod-super-secure-key

# Performance (optimized)
OGINI_TIMEOUT=15
OGINI_RETRY_ATTEMPTS=3
OGINI_RETRY_DELAY=100

# Cache (aggressive caching)
OGINI_CACHE_ENABLED=true
OGINI_CACHE_DRIVER=redis
OGINI_QUERY_CACHE_TTL=1800
OGINI_RESULT_CACHE_TTL=3600

# Connection Pool (maximum performance)
OGINI_POOL_ENABLED=true
OGINI_POOL_SIZE=10
OGINI_CONNECTION_TIMEOUT=5

# Batch Processing
OGINI_BATCH_ENABLED=true
OGINI_BATCH_SIZE=500
OGINI_MAX_RETRY_ATTEMPTS=5

# Security
OGINI_SSL_VERIFY=true
```

## Performance Optimization

### Query Optimization Configuration

Edit `config/ogini.php`:

```php
'performance' => [
    'query_optimization' => [
        'enabled' => env('OGINI_QUERY_OPTIMIZATION', true),
        'min_term_length' => env('OGINI_MIN_TERM_LENGTH', 2),
        'max_complexity_score' => env('OGINI_MAX_COMPLEXITY_SCORE', 20),
        'performance_check_threshold' => env('OGINI_PERFORMANCE_CHECK_THRESHOLD', 100),
        
        // Scoring adjustments
        'wildcard_penalty' => env('OGINI_WILDCARD_PENALTY', 3),
        'phrase_boost' => env('OGINI_PHRASE_BOOST', 2.0),
        'exact_match_boost' => env('OGINI_EXACT_MATCH_BOOST', 3.0),
        'fuzzy_match_boost' => env('OGINI_FUZZY_MATCH_BOOST', 0.8),
        
        // Field-specific boosts
        'field_boosts' => [
            'title' => 2.0,
            'content' => 1.0,
            'tags' => 1.5,
            'category' => 1.2,
        ],
    ],
],
```

## Connection Configuration

### Basic Connection Settings

```php
'client' => [
    'base_url' => env('OGINI_BASE_URL', 'http://localhost:3000'),
    'api_key' => env('OGINI_API_KEY'),
    'timeout' => env('OGINI_TIMEOUT', 30),
    'connect_timeout' => env('OGINI_CONNECT_TIMEOUT', 10),
    'retry_attempts' => env('OGINI_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('OGINI_RETRY_DELAY', 100),
    'verify_ssl' => env('OGINI_SSL_VERIFY', true),
],
```

## Cache Configuration

### Cache Drivers

```php
'performance' => [
    'cache' => [
        'enabled' => env('OGINI_CACHE_ENABLED', true),
        'driver' => env('OGINI_CACHE_DRIVER', 'redis'),
        'query_ttl' => env('OGINI_QUERY_CACHE_TTL', 1800),
        'result_ttl' => env('OGINI_RESULT_CACHE_TTL', 3600),
        'suggestion_ttl' => env('OGINI_SUGGESTION_CACHE_TTL', 7200),
        'prefix' => env('OGINI_CACHE_PREFIX', 'ogini_search'),
    ],
],
```

## Security Configuration

### Authentication and Authorization

```php
'security' => [
    'authentication' => [
        'type' => env('OGINI_AUTH_TYPE', 'bearer'),
        'api_key' => env('OGINI_API_KEY'),
    ],
    'ssl' => [
        'verify_peer' => env('OGINI_SSL_VERIFY_PEER', true),
        'verify_host' => env('OGINI_SSL_VERIFY_HOST', true),
        'ca_bundle' => env('OGINI_SSL_CA_BUNDLE'),
    ],
    'rate_limiting' => [
        'enabled' => env('OGINI_RATE_LIMITING', false),
        'requests_per_minute' => env('OGINI_RATE_LIMIT_RPM', 1000),
    ],
],
```

## Advanced Examples

### Runtime Configuration Changes

```php
use OginiScoutDriver\Facades\Ogini;

// Adjust batch size based on system load
$systemLoad = sys_getloadavg()[0];
$batchSize = $systemLoad > 2.0 ? 50 : 200;

Ogini::configure([
    'performance' => [
        'batch_processing' => [
            'batch_size' => $batchSize,
        ],
    ],
]);
```

### Environment-Based Configuration

```php
// config/ogini.php
return [
    'base_url' => env('OGINI_BASE_URL', 'http://localhost:3000'),
    'api_key' => env('OGINI_API_KEY'),
    
    'environments' => [
        'local' => [
            'cache' => ['enabled' => false],
            'logging' => ['level' => 'debug'],
        ],
        'production' => [
            'performance' => ['cache' => ['query_ttl' => 1800]],
            'monitoring' => ['enabled' => true],
        ],
    ],
];
```

## Configuration Best Practices

### 1. Security First
- Never commit API keys to version control
- Use different keys for each environment
- Enable SSL verification in production

### 2. Performance Optimization
- Enable caching in production
- Use connection pooling for high-traffic applications
- Configure appropriate batch sizes

### 3. Environment Management
- Use environment-specific configuration files
- Implement configuration validation
- Test configuration changes thoroughly

## Next Steps

After configuring your OginiSearch driver:

1. **[API Reference](./api-reference.md)** - Explore all available methods
2. **[Performance Optimization](./performance-optimization.md)** - Advanced performance tuning
3. **[Production Deployment](./production-deployment.md)** - Deploy with confidence

Your custom configuration is now ready for production use! 