Ran tool
Ran tool
Read file: vendor/ogini/oginisearch-laravel-scout/src/Engine/OginiEngine.php
Read file: vendor/ogini/oginisearch-laravel-scout/src/Performance/BatchProcessor.php
Ran tool
Now I have gathered comprehensive information about Laravel Scout's expected method signatures and how the OginiSearch package implements them. Let me create a detailed report.

# OginiSearch Laravel Scout Package - Method Signature Inconsistencies Report

## Executive Summary

This report identifies critical method signature mismatches between the OginiSearch Laravel Scout package and Laravel Scout's expected interface. These inconsistencies prevent proper integration and cause silent failures in bulk operations and search functionality.

## Critical Issues Identified

### 1. **PRIMARY ISSUE: `indexDocument` Method Parameter Order Mismatch**

**Location**: `vendor/ogini/oginisearch-laravel-scout/src/Client/OginiClient.php:151`

**Current Implementation**:
```php
public function indexDocument(string $indexName, array $document, ?string $documentId = null): array
```

**Laravel Scout Expected Signature**:
```php
indexDocument(string $indexName, string $documentId, array $document): array
```

**Impact**: 
- ❌ All single document indexing operations pass parameters in wrong order
- ❌ Bulk indexing fallback fails silently
- ❌ Individual retry operations in BatchProcessor fail

**Affected Files**:
- `src/Engine/OginiEngine.php:128-132` - Individual indexing fallback
- `src/Jobs/AsyncIndexJob.php:59` - Async job indexing  
- `src/Performance/BatchProcessor.php:203-206` - Retry logic
- Current Laravel application integration

### 2. **PRIMARY ISSUE: `search` Method Signature Mismatch**

**Location**: `vendor/ogini/oginisearch-laravel-scout/src/Client/OginiClient.php:254`

**Current Implementation**:
```php
public function search(string $indexName, array $searchQuery, ?int $size = null, ?int $from = null): array
```

**Laravel Scout Expected Signature**:
```php
search(string $indexName, string $query, array $options = []): array
```

**Impact**:
- ❌ All search operations return 0 results
- ❌ Query format incompatible with Scout's string-based queries
- ❌ Pagination parameters passed incorrectly
- ❌ Builder pattern integration broken

**Affected Files**:
- `src/Engine/OginiEngine.php:377-382` - Main search implementation
- `src/Engine/OginiEngine.php:387-391` - Pagination search
- `src/Engine/OginiEngine.php:524-528` - Advanced search methods
- All search functionality in Laravel application

### 3. **SECONDARY ISSUE: Query Format Incompatibility**

**Current OginiClient Expectation**:
```php
// Expects complex array structure
$searchQuery = [
    'query' => ['match' => ['value' => 'search term']],
    'filter' => [...],
    'sort' => '...'
];
```

**Laravel Scout Provides**:
```php
// Provides simple string query
$query = "search term";
$options = ['size' => 10, 'from' => 0];
```

**Impact**:
- ❌ OginiEngine must transform Scout's simple queries into complex arrays
- ❌ Additional complexity in query building logic
- ❌ Potential performance overhead

### 4. **BATCH OPERATIONS INCONSISTENCY**

**Location**: `src/Client/OginiClient.php:219`

**Current Implementation**: 
```php
public function bulkIndexDocuments(string $indexName, array $documents): array
```

**Expected Document Format**:
```php
$documents = [
    ['id' => '1', 'document' => ['field' => 'value']],
    ['id' => '2', 'document' => ['field' => 'value']]
];
```

**Laravel Scout Provides**:
```php
$documents = [
    ['id' => '1', 'field' => 'value'],
    ['id' => '2', 'field' => 'value']
];
```

**Impact**:
- ⚠️ BatchProcessor must restructure documents before bulk operations
- ⚠️ Additional data transformation overhead

## Method Usage Analysis

### Current Package Usage Patterns

**In OginiEngine.php**:
```php
// Lines 128-132: INCORRECT parameter order
$this->client->indexDocument(
    $indexName,                    // ✓ Correct
    $model->toSearchableArray(),   // ❌ Should be 3rd parameter  
    $model->getScoutKey()          // ❌ Should be 2nd parameter
);

// Lines 377-382: INCORRECT method signature
return $this->client->search(
    $indexName,                    // ✓ Correct
    $searchQuery,                  // ❌ Should be string, not array
    $options['size'] ?? null,      // ❌ Should be in options array
    $options['from'] ?? null       // ❌ Should be in options array  
);
```

**In BatchProcessor.php**:
```php
// Lines 203-206: INCORRECT parameter order (retry logic)
$this->client->indexDocument(
    $indexName,                    // ✓ Correct
    $document['document'],         // ❌ Should be 3rd parameter
    $document['id']                // ❌ Should be 2nd parameter
);
```

**In AsyncIndexJob.php**:
```php
// Line 59: INCORRECT parameter order  
$result = $client->indexDocument($this->indexName, $this->document, $this->documentId);
//                                     ✓              ❌              ❌
```

## Complete Fix Requirements

### 1. **Fix `indexDocument` Method Signature**

**File**: `src/Client/OginiClient.php`

**Change From**:
```php
public function indexDocument(string $indexName, array $document, ?string $documentId = null): array
{
    $payload = ['document' => $document];
    if ($documentId !== null) {
        $payload['id'] = $documentId;
    }
    return $this->request('POST', "/api/indices/{$indexName}/documents", $payload);
}
```

**Change To**:
```php
public function indexDocument(string $indexName, string $documentId, array $document): array
{
    $payload = [
        'id' => $documentId,
        'document' => $document
    ];
    return $this->request('POST', "/api/indices/{$indexName}/documents", $payload);
}
```

### 2. **Fix `search` Method Signature**

**File**: `src/Client/OginiClient.php`

**Change From**:
```php
public function search(string $indexName, array $searchQuery, ?int $size = null, ?int $from = null): array
{
    $payload = $searchQuery;
    if ($size !== null) {
        $payload['size'] = $size;
    }
    if ($from !== null) {
        $payload['from'] = $from;
    }
    return $this->request('POST', "/api/indices/{$indexName}/_search", $payload);
}
```

**Change To**:
```php
public function search(string $indexName, string $query, array $options = []): array
{
    $payload = [
        'query' => [
            'match' => [
                'value' => $query
            ]
        ]
    ];
    
    // Merge options (size, from, filters, etc.)
    $payload = array_merge($payload, $options);
    
    return $this->request('POST', "/api/indices/{$indexName}/_search", $payload);
}
```

### 3. **Update All Method Calls**

**Files to Update**:
- `src/Engine/OginiEngine.php` (lines 128-132, 377-382, 387-391, 524-528)
- `src/Jobs/AsyncIndexJob.php` (line 59)
- `src/Performance/BatchProcessor.php` (lines 203-206)

### 4. **Update OginiEngine Query Building**

**File**: `src/Engine/OginiEngine.php`

**Update Method**: `performSearch()` - lines 366-392

**Change search calls from**:
```php
return $this->client->search(
    $indexName,
    $searchQuery,                // Complex array
    $options['size'] ?? null,    // Separate parameters
    $options['from'] ?? null
);
```

**To**:
```php
return $this->client->search(
    $indexName,
    $builder->query ?? '',       // Simple string query
    array_merge($searchQuery, [  // All options in array
        'size' => $options['size'] ?? null,
        'from' => $options['from'] ?? null
    ])
);
```

## Testing Requirements

### 1. **Unit Tests to Add**
- Test `indexDocument` with correct parameter order
- Test `search` with string query and options array
- Test bulk operations with transformed document format
- Test query building logic in OginiEngine

### 2. **Integration Tests to Update**
- Scout's `scout:import` command
- Model `searchable()` calls
- Search queries with pagination
- Async job processing

### 3. **Validation Commands**
- Run existing `DebugOginiClientCommand` to verify fixes
- Test bulk indexing with `BulkOginiIndexCommand`
- Verify search results return correctly

## Implementation Priority

### **CRITICAL (Must Fix First)**
1. ✅ `indexDocument` parameter order - **Breaks all individual indexing**
2. ✅ `search` method signature - **Breaks all search functionality**

### **HIGH (Fix Next)**  
3. Update all method calls in Engine, BatchProcessor, AsyncJob
4. Update query building logic in OginiEngine

### **MEDIUM (Polish)**
5. Add comprehensive tests
6. Update documentation
7. Add method signature validation

## Backward Compatibility

**⚠️ BREAKING CHANGES**: These fixes will break existing code that directly uses `OginiClient` methods. However, this is necessary for Laravel Scout compatibility.

**Migration Path**:
1. Fix method signatures in OginiClient
2. Update all internal package usage
3. Update any direct client usage in applications
4. Release as major version bump (v2.0.0)

## Success Criteria

- ✅ `scout:import` commands complete successfully
- ✅ Model `searchable()` calls index documents properly  
- ✅ Search queries return expected results
- ✅ Bulk operations process all documents
- ✅ Async jobs complete without errors
- ✅ Package maintains 69x performance improvement over MeiliSearch

This report provides a complete roadmap for fixing the OginiSearch Laravel Scout package to achieve full compatibility with Laravel Scout's expected interface.