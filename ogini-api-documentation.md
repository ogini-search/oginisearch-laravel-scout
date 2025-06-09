# Ogini Search Engine API Documentation

## Overview
The Ogini Search Engine provides a comprehensive RESTful API for index management, document operations, and advanced search capabilities. This document covers all crucial endpoints with correct query structures and usage examples.

## Base URL
```
http://localhost:3000/api
```

## Authentication
All endpoints require authentication via API key (when implemented):
```http
Authorization: Bearer <api_key>
# OR
x-api-key: <api_key>
```

---

## 1. Index Management

### 1.1 Create Index
**Endpoint:** `POST /api/indices`

**Request Body:**
```json
{
  "name": "products",
  "settings": {
    "numberOfShards": 1,
    "refreshInterval": "1s"
  },
  "mappings": {
    "properties": {
      "title": { 
        "type": "text", 
        "analyzer": "standard", 
        "boost": 2.0 
      },
      "description": { 
        "type": "text", 
        "analyzer": "standard" 
      },
      "price": { 
        "type": "number" 
      },
      "categories": { 
        "type": "keyword" 
      },
      "inStock": { 
        "type": "boolean" 
      },
      "createdAt": { 
        "type": "date" 
      }
    }
  }
}
```

**Response:**
```json
{
  "name": "products",
  "status": "open",
  "createdAt": "2023-06-15T10:00:00Z",
  "documentCount": 0,
  "settings": {
    "numberOfShards": 1,
    "refreshInterval": "1s"
  },
  "mappings": {
    "properties": {
      "title": { "type": "text", "analyzer": "standard", "boost": 2.0 }
    }
  }
}
```

### 1.2 List All Indices
**Endpoint:** `GET /api/indices`

**Query Parameters:**
- `status` (optional): Filter by status (open, closed)

**Response:**
```json
{
  "indices": [
    {
      "name": "products",
      "status": "open",
      "documentCount": 150,
      "createdAt": "2023-06-15T10:00:00Z"
    }
  ],
  "total": 1
}
```

### 1.3 Get Index Details
**Endpoint:** `GET /api/indices/{index_name}`

**Response:**
```json
{
  "name": "products",
  "status": "open",
  "documentCount": 150,
  "createdAt": "2023-06-15T10:00:00Z",
  "settings": {
    "numberOfShards": 1,
    "refreshInterval": "1s"
  },
  "mappings": {
    "properties": {
      "title": { "type": "text", "analyzer": "standard" }
    }
  }
}
```

### 1.4 Update Index Settings
**Endpoint:** `PUT /api/indices/{index_name}/settings`

**Request Body:**
```json
{
  "settings": {
    "refreshInterval": "2s"
  },
  "mappings": {
    "properties": {
      "rating": { "type": "number" }
    }
  }
}
```

### 1.5 Delete Index
**Endpoint:** `DELETE /api/indices/{index_name}`

**Response:** `204 No Content`

---

## 2. Document Management

### 2.1 Index a Document
**Endpoint:** `POST /api/indices/{index_name}/documents`

**Request Body (with ID):**
```json
{
  "id": "product-123",
  "document": {
    "title": "Smartphone X",
    "description": "Latest smartphone with advanced features",
    "price": 999.99,
    "categories": ["electronics", "mobile"],
    "inStock": true,
    "createdAt": "2023-06-15T10:00:00Z"
  }
}
```

**Request Body (auto-generated ID):**
```json
{
  "document": {
    "title": "Laptop Pro",
    "description": "Professional grade laptop",
    "price": 1499.99,
    "categories": ["electronics", "computers"]
  }
}
```

**Response:**
```json
{
  "id": "product-123",
  "index": "products",
  "version": 1,
  "result": "created"
}
```

### 2.2 Get Document
**Endpoint:** `GET /api/indices/{index_name}/documents/{document_id}`

**Response:**
```json
{
  "id": "product-123",
  "index": "products",
  "version": 1,
  "source": {
    "title": "Smartphone X",
    "description": "Latest smartphone with advanced features",
    "price": 999.99,
    "categories": ["electronics", "mobile"]
  }
}
```

### 2.3 Update Document
**Endpoint:** `PUT /api/indices/{index_name}/documents/{document_id}`

**Request Body:**
```json
{
  "document": {
    "title": "Smartphone X Pro",
    "price": 1099.99,
    "inStock": false
  }
}
```

### 2.4 Delete Document
**Endpoint:** `DELETE /api/indices/{index_name}/documents/{document_id}`

**Response:** `204 No Content`

### 2.5 Bulk Index Documents
**Endpoint:** `POST /api/indices/{index_name}/documents/_bulk`

**Request Body:**
```json
{
  "documents": [
    {
      "id": "product-1",
      "document": {
        "title": "Product 1",
        "price": 100
      }
    },
    {
      "id": "product-2",
      "document": {
        "title": "Product 2",
        "price": 200
      }
    }
  ]
}
```

### 2.6 Delete by Query
**Endpoint:** `DELETE /api/indices/{index_name}/documents/_query`

**Request Body:**
```json
{
  "query": {
    "term": {
      "field": "categories",
      "value": "discontinued"
    }
  }
}
```

**Range Query Example:**
```json
{
  "query": {
    "range": {
      "field": "price",
      "lt": 100
    }
  }
}
```

### 2.7 List Documents
**Endpoint:** `GET /api/indices/{index_name}/documents`

**Query Parameters:**
- `limit` (default: 10): Number of documents to return
- `offset` (default: 0): Starting offset
- `filter` (optional): Filter criteria

---

## 3. Search Operations

### 3.1 Search Documents
**Endpoint:** `POST /api/indices/{index_name}/_search`

**Query Parameters:**
- `size` (optional): Number of results to return
- `from` (optional): Starting offset for pagination

#### 3.1.1 Basic Match Query
```json
{
  "query": {
    "match": {
      "field": "title",
      "value": "smartphone"
    }
  },
  "size": 10,
  "from": 0
}
```

#### 3.1.2 Multi-Field Search
```json
{
  "query": {
    "match": {
      "value": "wireless headphones"
    }
  },
  "fields": ["title", "description"],
  "size": 10
}
```

#### 3.1.3 Multi-Match Query
```json
{
  "query": {
    "multi_match": {
      "query": "laptop gaming",
      "fields": ["title^2", "description", "category"]
    }
  }
}
```

#### 3.1.4 Match with Filter
```json
{
  "query": {
    "match": {
      "field": "description",
      "value": "high performance"
    }
  },
  "filter": {
    "term": {
      "field": "categories",
      "value": "electronics"
    }
  },
  "size": 20
}
```

#### 3.1.5 Match All Documents
```json
{
  "query": {
    "match": {
      "value": "*"
    }
  }
}
```

#### 3.1.6 Advanced Search with All Options
```json
{
  "query": {
    "match": {
      "field": "title",
      "value": "smartphone"
    }
  },
  "size": 10,
  "from": 0,
  "fields": ["title", "description"],
  "filter": {
    "term": {
      "field": "inStock",
      "value": true
    }
  },
  "sort": "price:desc",
  "highlight": true,
  "facets": ["categories", "brand"]
}
```

**Search Response:**
```json
{
  "data": {
    "total": 5,
    "maxScore": 0.9567,
    "hits": [
      {
        "id": "product-123",
        "index": "products",
        "score": 0.9567,
        "source": {
          "title": "Wireless Bluetooth Headphones",
          "description": "High quality audio with noise cancellation",
          "price": 159.99,
          "categories": ["electronics", "audio"]
        },
        "highlight": {
          "title": ["<em>Wireless</em> Bluetooth Headphones"]
        }
      }
    ]
  },
  "facets": {
    "categories": {
      "buckets": [
        { "key": "electronics", "count": 3 },
        { "key": "audio", "count": 2 }
      ]
    }
  },
  "took": 15
}
```

### 3.2 Suggestions
**Endpoint:** `POST /api/indices/{index_name}/_search/_suggest`

#### 3.2.1 Basic Suggestion
```json
{
  "text": "phon",
  "field": "title",
  "size": 5
}
```

#### 3.2.2 Suggestion Without Specific Field
```json
{
  "text": "lapt",
  "size": 3
}
```

**Suggestion Response:**
```json
{
  "suggestions": [
    { "text": "phone", "score": 1.0, "freq": 10 },
    { "text": "smartphone", "score": 0.8, "freq": 5 },
    { "text": "headphone", "score": 0.6, "freq": 3 }
  ],
  "took": 5
}
```

---

## 4. Log Analysis & Query Pattern Assessment

### 4.1 Observed Query Patterns in Logs

Based on the application logs, here are the query patterns being used:

#### ✅ **CORRECT Queries Observed:**

1. **Basic Match Query:**
```json
{"match":{"value":"service"}}
```
✅ This is correct and follows the expected DTO structure.

2. **Multi-Match Query:**
```json
{"multi_match":{"query":"laptop","fields":["title^2","description","category"]}}
```
✅ This is correct and properly structured.

3. **Match All Query:**
```json
{"match":{"value":"*"}}
```
✅ This works but could be optimized using a dedicated match_all query.

4. **Multi-word Queries:**
```json
{"match":{"value":"Art Gallery"}}
```
✅ Correctly handled by the query processor, which splits into boolean OR clauses.

### 4.2 Query Processing Analysis

From the logs, the engine is correctly:

1. **Processing Multi-Word Queries:**
   - Input: `"Art Gallery"` 
   - Processed: `{"type":"boolean","operator":"or","clauses":[{"type":"term","field":"_all","value":"art"},{"type":"term","field":"_all","value":"gallery"}]}`

2. **Handling Multi-Match Queries:**
   - Complex field boosting with `title^2` is being processed
   - Multiple field searches are working correctly

3. **Executing Search Plans:**
   - Query cost estimation is working (`"cost":1000,"estimatedResults":0`)
   - Execution plans are being generated properly

### 4.3 Performance Observations

From the logs:
- **Search Speed:** 1-5ms per query (excellent performance)
- **Memory Usage:** Stable at ~37-39MB heap
- **Index Operations:** Working correctly with both RocksDB and MongoDB
- **Document Count Verification:** Running automatically every hour

### 4.4 Potential Issues Observed

1. **Empty Results:** Many queries return 0 results, which might indicate:
   - Index needs more documents
   - Query terms don't match indexed content
   - Analyzer configuration might need adjustment

2. **Multi-Match Empty Results:** 
   ```
   "parsedQuery":{"type":"boolean","operator":"or","clauses":[]}
   ```
   This suggests the multi-match query isn't finding matching terms.

---

## 5. Best Practices & Recommendations

### 5.1 Query Structure Best Practices

1. **Use Specific Field Queries When Possible:**
```json
// Good - targets specific field
{"match": {"field": "title", "value": "smartphone"}}

// Less optimal - searches all fields
{"match": {"value": "smartphone"}}
```

2. **Leverage Field Boosting:**
```json
{
  "query": {
    "multi_match": {
      "query": "search term",
      "fields": ["title^3", "description^1", "tags^2"]
    }
  }
}
```

3. **Use Filters for Exact Matches:**
```json
{
  "query": {"match": {"value": "search term"}},
  "filter": {
    "term": {"field": "category", "value": "electronics"}
  }
}
```

### 5.2 Performance Optimization

1. **Pagination for Large Result Sets:**
```json
{
  "query": {"match": {"value": "popular term"}},
  "size": 20,
  "from": 0
}
```

2. **Use Facets for Navigation:**
```json
{
  "query": {"match": {"value": "search"}},
  "facets": ["category", "brand", "price_range"]
}
```

### 5.3 Error Handling

The API returns appropriate HTTP status codes:
- `400` - Bad Request (invalid query structure)
- `404` - Not Found (index/document doesn't exist)
- `409` - Conflict (index already exists)
- `500` - Internal Server Error

---

## 6. Testing with cURL Examples

### Index Creation
```bash
curl -X POST "http://localhost:3000/api/indices" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <api_key>" \
  -d '{
    "name": "test_products",
    "mappings": {
      "properties": {
        "title": {"type": "text", "analyzer": "standard"},
        "price": {"type": "number"}
      }
    }
  }'
```

### Document Indexing
```bash
curl -X POST "http://localhost:3000/api/indices/test_products/documents" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <api_key>" \
  -d '{
    "id": "prod-1",
    "document": {
      "title": "Test Product",
      "price": 99.99
    }
  }'
```

### Search
```bash
curl -X POST "http://localhost:3000/api/indices/test_products/_search" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <api_key>" \
  -d '{
    "query": {
      "match": {
        "field": "title",
        "value": "test"
      }
    }
  }'
```

---

## 7. Conclusion

**Assessment of Current Application Queries:**

✅ **The application is querying correctly!** The logs show proper use of:
- Match queries with correct structure
- Multi-match queries with field boosting
- Proper JSON formatting
- Appropriate endpoint usage

**Recommendations for Improvement:**

1. **Add more test documents** to indices to get meaningful search results
2. **Consider using term queries** for exact matches instead of match queries
3. **Implement proper error handling** for empty result sets
4. **Add query validation** on the client side to ensure required fields are present

The Ogini search engine is performing well with fast response times and proper query processing. The current query patterns from the calling application are well-structured and follow the expected API format. 