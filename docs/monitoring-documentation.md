# OginiSearch Scout Driver - Monitoring & Error Handling Documentation

## Table of Contents

1. [Overview](#overview)
2. [Error Handling System](#error-handling-system)
3. [Logging System](#logging-system)
4. [Performance Monitoring](#performance-monitoring)
5. [Health Checking](#health-checking)
6. [Status Reporting](#status-reporting)
7. [Configuration](#configuration)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)

## Overview

The OginiSearch Scout Driver provides comprehensive monitoring, logging, and error handling capabilities to ensure reliable operation and easy troubleshooting in production environments.

### Key Features

- **Comprehensive Exception Hierarchy**: Structured error handling with specific exception types
- **Centralized Error Codes**: Standardized error codes with descriptions and categories
- **Structured Logging**: JSON-formatted logs with context and metadata
- **Performance Monitoring**: Real-time metrics collection and analysis
- **Health Checking**: Automated service health monitoring
- **Status Reporting**: Comprehensive status reports with recommendations

## Error Handling System

### Exception Hierarchy

The driver uses a hierarchical exception system for better error categorization and handling:

```php
OginiException (Base)
├── ConnectionException (Connection-related errors)
├── ValidationException (Request validation errors)
├── IndexNotFoundException (Index-related errors)
└── RateLimitException (Rate limiting errors)
```

### Exception Usage Examples

#### Basic Exception Handling

```php
use OginiScoutDriver\Exceptions\OginiException;
use OginiScoutDriver\Exceptions\ConnectionException;

try {
    $client->search('my_index', ['query' => 'search term']);
} catch (ConnectionException $e) {
    // Handle connection-specific errors
    if ($e->isRetryable()) {
        $delay = $e->getRetryDelay();
        sleep($delay);
        // Retry logic
    }
} catch (OginiException $e) {
    // Handle all other OginiSearch errors
    $context = $e->getContext();
    Log::error('OginiSearch error occurred', $context);
}
```

#### Validation Exception Handling

```php
use OginiScoutDriver\Exceptions\ValidationException;

try {
    $client->index('my_index', $document);
} catch (ValidationException $e) {
    $errors = $e->getValidationErrors();
    $invalidData = $e->getInvalidData();
    
    // Return detailed validation errors to user
    return response()->json([
        'message' => 'Validation failed',
        'errors' => $errors,
        'invalid_data' => $invalidData
    ], 422);
}
```

#### Rate Limit Exception Handling

```php
use OginiScoutDriver\Exceptions\RateLimitException;

try {
    $client->search('my_index', $query);
} catch (RateLimitException $e) {
    $retryAfter = $e->getRetryAfter();
    $remaining = $e->getRateLimitRemaining();
    
    return response()->json([
        'message' => 'Rate limit exceeded',
        'retry_after' => $retryAfter,
        'remaining_requests' => $remaining
    ], 429);
}
```

### Error Codes

The driver uses a comprehensive error code system for consistent error identification:

```php
use OginiScoutDriver\Exceptions\ErrorCodes;

// Get error description
$description = ErrorCodes::getDescription(ErrorCodes::CONNECTION_FAILED);

// Check if error is retryable
$isRetryable = ErrorCodes::isRetryable(ErrorCodes::CONNECTION_TIMEOUT);

// Get suggested retry delay
$retryDelay = ErrorCodes::getRetryDelay(ErrorCodes::CONNECTION_TIMEOUT);

// Get error category
$category = ErrorCodes::getCategory(ErrorCodes::VALIDATION_FAILED);
```

#### Error Code Categories

- **Connection** (1000-1099): Network and connectivity issues
- **Authentication** (1100-1199): Authentication and authorization errors
- **Rate Limiting** (1200-1299): Rate limit and quota violations
- **Validation** (1300-1399): Request validation failures
- **Index** (1400-1499): Index-related operations
- **Document** (1500-1599): Document operations
- **Search** (1600-1699): Search operations
- **Performance** (1700-1799): Performance-related issues
- **Server** (1800-1899): Server-side errors
- **Configuration** (1900-1999): Configuration issues

## Logging System

### OginiLogger

The centralized logging system provides structured logging with context enrichment:

```php
use OginiScoutDriver\Logging\OginiLogger;

$logger = new OginiLogger();

// Basic logging
$logger->info('Operation completed successfully');
$logger->error('Operation failed', ['error' => $errorMessage]);

// Operation-specific logging
$logger->logSearch('my_index', $query, $resultCount, $duration);
$logger->logIndexing('my_index', $docCount, $duration, true);
$logger->logDeletion('my_index', $documentIds, true);

// Performance logging
$logger->logPerformance('search_latency', 0.250, 'seconds');
$logger->logSlowOperation('search', 2.5, 1.0);

// Exception logging
$logger->logException($exception, ['operation' => 'search']);
```

### Log Formatting

The LogFormatter provides structured JSON output with sensitive data sanitization:

```php
use OginiScoutDriver\Logging\LogFormatter;

// Development formatter (includes stack traces)
$devFormatter = LogFormatter::forDevelopment();

// Production formatter (excludes stack traces)
$prodFormatter = LogFormatter::forProduction();

// Custom sensitive fields
$customFormatter = LogFormatter::withSensitiveFields(['custom_secret']);
```

### Sample Log Output

```json
{
  "@timestamp": "2024-01-15T10:30:45.123Z",
  "level": "info",
  "message": "Search operation completed",
  "channel": "ogini",
  "context": {
    "operation": "search",
    "index_name": "blog_posts",
    "query": {"term": "laravel"},
    "results_count": 42,
    "duration_ms": 245.67,
    "performance_category": "good"
  },
  "service": {
    "name": "ogini-scout-driver",
    "version": "1.0.0",
    "environment": "production"
  },
  "request": {
    "method": "POST",
    "url": "https://api.example.com/search",
    "ip": "192.168.1.100",
    "request_id": "req_abc123"
  },
  "performance": {
    "memory_usage": 12582912,
    "memory_peak": 15728640,
    "execution_time": 0.245
  }
}
```

## Performance Monitoring

### PerformanceMonitor

The performance monitoring system tracks operations and identifies bottlenecks:

```php
use OginiScoutDriver\Monitoring\PerformanceMonitor;

$monitor = new PerformanceMonitor();

// Start timing an operation
$timerId = $monitor->startTimer('search_operation', [
    'index' => 'blog_posts',
    'query_type' => 'full_text'
]);

// ... perform operation ...

// Stop timing and get metrics
$metrics = $monitor->stopTimer($timerId, [
    'results_found' => $resultCount
]);

// Record custom metrics
$monitor->recordMetric('cache_hit_rate', 85.5, ['cache_type' => 'query']);

// Increment counters
$monitor->incrementCounter('total_searches');
$monitor->incrementCounter('failed_operations', 1, ['error_type' => 'timeout']);
```

### Metrics Summary

```php
// Get comprehensive metrics summary
$summary = $monitor->getMetricsSummary();

/*
Returns:
{
  "total_operations": 1250,
  "active_timers": 3,
  "counters": {
    "total_searches": 1000,
    "failed_operations": 12
  },
  "memory": {
    "current": 12582912,
    "peak": 15728640,
    "current_mb": 12.0,
    "peak_mb": 15.0
  },
  "performance_stats": {
    "duration": {
      "avg": 0.245,
      "min": 0.050,
      "max": 2.100,
      "p95": 0.800,
      "p99": 1.200
    }
  }
}
*/
```

### Slow Operations Report

```php
// Get slow operations report
$slowOps = $monitor->getSlowOperations(10);

/*
Returns array of:
{
  "operation": "search",
  "duration": 2.5,
  "threshold": 1.0,
  "slowness_factor": 2.5,
  "timestamp": 1705310445.123,
  "context": {"index": "large_index"}
}
*/
```

## Health Checking

### HealthChecker

Automated health monitoring with comprehensive checks:

```php
use OginiScoutDriver\Monitoring\HealthChecker;

$healthChecker = new HealthChecker($oginiClient, $logger, $monitor);

// Perform comprehensive health check
$healthResults = $healthChecker->performHealthCheck();

// Get cached health results
$cachedHealth = $healthChecker->getCachedHealthCheck();

// Get health summary
$summary = $healthChecker->getHealthSummary();
```

### Health Check Results

```php
/*
Health check results structure:
{
  "status": "healthy|degraded|unhealthy|critical",
  "timestamp": 1705310445.123,
  "overall_health": 95,
  "checks": {
    "connectivity": {
      "name": "Connectivity",
      "status": "healthy",
      "score": 100,
      "details": {
        "response_time_ms": 45.67,
        "status_code": 200,
        "server_version": "1.2.0"
      }
    },
    "service_availability": {
      "name": "Service Availability",
      "status": "healthy",
      "score": 100,
      "details": {
        "search": {"working": true, "response_time_ms": 123.45},
        "indexing": {"working": true, "response_time_ms": 234.56}
      }
    },
    // ... more checks
  }
}
*/
```

### Health Check Integration

```php
// In your application's health check endpoint
Route::get('/health', function () {
    $healthChecker = app(HealthChecker::class);
    $health = $healthChecker->getCachedHealthCheck() 
        ?? $healthChecker->performHealthCheck();
    
    $statusCode = match($health['status']) {
        'healthy' => 200,
        'degraded' => 200,
        'unhealthy' => 503,
        'critical' => 503,
        default => 500
    };
    
    return response()->json($health, $statusCode);
});
```

## Status Reporting

### StatusReporter

Comprehensive status reporting with recommendations:

```php
use OginiScoutDriver\Monitoring\StatusReporter;

$reporter = new StatusReporter($healthChecker, $monitor, $logger);

// Generate comprehensive status report
$statusReport = $reporter->generateStatusReport();

// Get cached status report
$cachedReport = $reporter->getCachedStatusReport();
```

### Status Report Structure

```php
/*
Status report structure:
{
  "timestamp": 1705310445.123,
  "service": {
    "name": "OginiSearch Scout Driver",
    "version": "1.0.0",
    "environment": "production",
    "uptime": {
      "start_time": 1705224045.123,
      "uptime_seconds": 86400,
      "uptime_human": "1 day"
    }
  },
  "health": {
    "overall_status": "healthy",
    "overall_health_score": 95,
    "last_check": 1705310445.123,
    "checks": { ... },
    "summary": {
      "total_checks": 6,
      "healthy_checks": 5,
      "issues_found": 1,
      "issues": [...],
      "recommendations": [...]
    }
  },
  "performance": {
    "memory_usage": {...},
    "active_operations": 3,
    "total_operations": 1250,
    "slow_operations": [...],
    "thresholds_exceeded": 2
  },
  "errors": {
    "recent_errors": [...],
    "error_counts": {...},
    "error_rate": 0.96,
    "most_common_errors": [...]
  },
  "statistics": {
    "today": {
      "searches": 1000,
      "documents_indexed": 500,
      "errors": 12,
      "cache_hit_rate": 85.5,
      "error_rate": 1.2
    },
    "uptime_stats": {...},
    "throughput": {...}
  },
  "configuration": {...}
}
*/
```

## Configuration

### Logging Configuration

```php
// config/logging.php
'channels' => [
    'ogini' => [
        'driver' => 'single',
        'path' => storage_path('logs/ogini.log'),
        'level' => env('OGINI_LOG_LEVEL', 'info'),
        'formatter' => \OginiScoutDriver\Logging\LogFormatter::class,
    ],
],

// For production
'ogini' => [
    'driver' => 'daily',
    'path' => storage_path('logs/ogini.log'),
    'level' => 'warning',
    'days' => 14,
    'formatter' => \OginiScoutDriver\Logging\LogFormatter::forProduction(),
],
```

### Monitoring Configuration

```php
// config/ogini.php
'monitoring' => [
    'enabled' => env('OGINI_MONITORING_ENABLED', true),
    'performance_thresholds' => [
        'search_duration' => 1.0,
        'indexing_duration' => 5.0,
        'memory_usage' => 128 * 1024 * 1024,
    ],
    'health_check' => [
        'cache_ttl' => 300, // 5 minutes
        'enabled_checks' => [
            'connectivity',
            'service_availability',
            'performance',
            'resources',
            'cache',
        ],
    ],
    'metrics' => [
        'retention_hours' => 24,
        'cache_prefix' => 'ogini:metrics',
    ],
],
```

## Best Practices

### Exception Handling

1. **Use Specific Exception Types**: Catch specific exception types rather than the base `OginiException`
2. **Check Retryability**: Always check if an exception is retryable before implementing retry logic
3. **Log Context**: Include relevant context when logging exceptions
4. **Graceful Degradation**: Implement fallback mechanisms for non-critical operations

```php
try {
    $results = $client->search($index, $query);
} catch (ConnectionException $e) {
    if ($e->isRetryable()) {
        // Implement exponential backoff
        $delay = min($e->getRetryDelay() * pow(2, $retryAttempt), 30);
        sleep($delay);
        // Retry...
    } else {
        // Fall back to cached results or alternative service
        $results = $this->getFallbackResults($query);
    }
} catch (ValidationException $e) {
    // Return validation errors to user
    return $this->handleValidationErrors($e);
}
```

### Logging Best Practices

1. **Use Appropriate Log Levels**: Follow PSR-3 log levels
2. **Include Context**: Always provide relevant context data
3. **Avoid Sensitive Data**: Never log passwords, API keys, or personal data
4. **Use Structured Logging**: Prefer structured data over string concatenation

```php
// Good
$logger->info('User search completed', [
    'user_id' => $user->id,
    'query' => $sanitizedQuery,
    'results_count' => count($results),
    'duration_ms' => $duration * 1000,
]);

// Avoid
$logger->info("User {$user->id} searched for '{$query}' and got " . count($results) . " results");
```

### Performance Monitoring

1. **Monitor Key Operations**: Track critical operations like search and indexing
2. **Set Appropriate Thresholds**: Configure thresholds based on your performance requirements
3. **Use Percentiles**: Monitor P95 and P99 latencies, not just averages
4. **Clean Up Metrics**: Implement metric retention policies to prevent memory bloat

### Health Checking

1. **Regular Health Checks**: Schedule regular health checks (every 5 minutes)
2. **Monitor Dependencies**: Include checks for external dependencies
3. **Set Up Alerts**: Configure alerts based on health check results
4. **Cache Results**: Cache health check results to avoid overloading the system

## Troubleshooting

### Common Issues

#### High Memory Usage

**Symptoms**: Memory usage warnings in health checks, slow performance

**Solutions**:
- Reduce batch sizes for bulk operations
- Implement proper pagination for large result sets
- Clear metrics periodically
- Optimize query complexity

#### Slow Operations

**Symptoms**: Slow operation warnings in performance monitoring

**Solutions**:
- Optimize index structure
- Add proper caching
- Review query complexity
- Scale infrastructure

#### Connection Timeouts

**Symptoms**: Connection timeout exceptions

**Solutions**:
- Increase timeout values
- Check network connectivity
- Verify server capacity
- Implement connection pooling

#### High Error Rates

**Symptoms**: High error rates in status reports

**Solutions**:
- Review error logs for patterns
- Check service availability
- Verify configuration
- Implement circuit breaker pattern

### Debugging

Enable debug logging for detailed troubleshooting:

```php
// Temporarily increase log level
$logger = new OginiLogger();
$logger->debug('Detailed operation information', [
    'request_data' => $request,
    'response_data' => $response,
    'timing_info' => $timingData,
]);
```

### Performance Analysis

Use the performance monitor to identify bottlenecks:

```php
$monitor = new PerformanceMonitor();

// Get slow operations
$slowOps = $monitor->getSlowOperations(20);

// Analyze performance trends
$stats = $monitor->getMetricsSummary()['performance_stats'];

// Check memory usage patterns
$memoryMetrics = $monitor->getOperationMetrics('memory_usage', 100);
```

## Conclusion

The OginiSearch Scout Driver's monitoring and error handling system provides comprehensive visibility into your search operations. By following the best practices outlined in this documentation, you can ensure reliable operation and quick troubleshooting when issues arise.

For additional support or questions, please refer to the main documentation or submit an issue on the project repository. 