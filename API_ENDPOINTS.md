# Ogini Search Engine API Endpoints

This document maps the actual API endpoints from the NestJS Ogini Search Engine to ensure the Laravel Scout driver uses the correct routes.

## Base URL Structure

All API endpoints are prefixed with `/api/` and follow RESTful conventions.

## Index Management Endpoints

### Create Index
- **Endpoint**: `POST /api/indices`
- **Description**: Creates a new search index
- **Request Body**:
  ```json
  {
    "name": "index_name",
    "settings": {
      "numberOfShards": 1,
      "refreshInterval": "1s"
    },
    "mappings": {
      "properties": {
        "title": { "type": "text", "analyzer": "standard" },
        "description": { "type": "text" },
        "price": { "type": "number" },
        "categories": { "type": "keyword" }
      }
    }
  }
  ```
- **Response**: Index configuration with metadata

### List Indices
- **Endpoint**: `GET /api/indices`
- **Query Parameters**: 
  - `status` (optional): Filter by index status
- **Description**: Returns a list of all indices
- **Response**: Array of index objects with total count

### Get Index Details
- **Endpoint**: `GET /api/indices/{name}`
- **Description**: Retrieves detailed information about a specific index
- **Response**: Complete index configuration and metadata

### Update Index Settings
- **Endpoint**: `PUT /api/indices/{name}/settings`
- **Description**: Updates settings for an existing index
- **Request Body**:
  ```json
  {
    "settings": {
      "refreshInterval": "2s"
    }
  }
  ```

### Delete Index
- **Endpoint**: `DELETE /api/indices/{name}`
- **Description**: Permanently deletes an index and all its documents
- **Response**: HTTP 204 No Content

### Rebuild Document Count
- **Endpoint**: `POST /api/indices/{name}/_rebuild_count`
- **Description**: Rebuilds the document count for an index

## Document Management Endpoints

### Index Document
- **Endpoint**: `POST /api/indices/{index}/documents`
- **Description**: Adds or updates a document in the index
- **Request Body**:
  ```json
  {
    "id": "optional_document_id",
    "document": {
      "title": "Document Title",
      "content": "Document content"
    }
  }
  ```

### Get Document
- **Endpoint**: `GET /api/indices/{index}/documents/{id}`
- **Description**: Retrieves a specific document by ID

### Update Document
- **Endpoint**: `PUT /api/indices/{index}/documents/{id}`
- **Description**: Updates an existing document
- **Request Body**:
  ```json
  {
    "document": {
      "title": "Updated Title",
      "content": "Updated content"
    }
  }
  ```

### Delete Document
- **Endpoint**: `DELETE /api/indices/{index}/documents/{id}`
- **Description**: Deletes a specific document
- **Response**: HTTP 204 No Content

### Bulk Index Documents
- **Endpoint**: `POST /api/indices/{index}/documents/_bulk`
- **Description**: Index multiple documents in a single request
- **Request Body**:
  ```json
  {
    "documents": [
      {
        "id": "doc1",
        "document": { "title": "Document 1" }
      },
      {
        "id": "doc2", 
        "document": { "title": "Document 2" }
      }
    ]
  }
  ```

### Delete by Query
- **Endpoint**: `POST /api/indices/{index}/documents/_delete_by_query`
- **Description**: Deletes documents matching a query
- **Request Body**:
  ```json
  {
    "query": {
      "term": {
        "field": "category",
        "value": "discontinued"
      }
    }
  }
  ```

### List Documents
- **Endpoint**: `GET /api/indices/{index}/documents`
- **Query Parameters**:
  - `limit`: Number of documents to return (default: 10)
  - `offset`: Starting offset (default: 0)
  - `filter`: Optional filter criteria
- **Description**: Lists documents in an index with pagination

## Search Endpoints

### Search Documents
- **Endpoint**: `POST /api/indices/{index}/_search`
- **Query Parameters**:
  - `size`: Number of results to return
  - `from`: Starting offset for pagination
- **Description**: Search for documents in an index
- **Request Body**:
  ```json
  {
    "query": {
      "match": {
        "field": "title",
        "value": "search term"
      }
    },
    "filter": {
      "term": {
        "field": "category",
        "value": "electronics"
      }
    },
    "size": 10,
    "from": 0,
    "fields": ["title", "description"],
    "sort": "title:desc",
    "highlight": false,
    "facets": ["category", "brand"]
  }
  ```

### Get Suggestions
- **Endpoint**: `POST /api/indices/{index}/_search/_suggest`
- **Description**: Returns search suggestions for autocomplete
- **Request Body**:
  ```json
  {
    "text": "partial_text",
    "field": "title",
    "size": 5
  }
  ```

## Response Formats

### Standard Success Response
```json
{
  "data": { /* response data */ },
  "took": 15
}
```

### Error Response
```json
{
  "error": "Error message",
  "code": "ERROR_CODE",
  "details": { /* additional error details */ }
}
```

### Search Response
```json
{
  "data": {
    "total": 5,
    "maxScore": 0.95,
    "hits": [
      {
        "id": "doc_id",
        "score": 0.95,
        "source": { /* document data */ },
        "highlight": { /* highlighted fields */ }
      }
    ]
  },
  "facets": {
    "category": {
      "buckets": [
        { "key": "electronics", "count": 10 }
      ]
    }
  },
  "took": 15
}
```

## Implementation Notes

1. **Route Prefix**: All endpoints start with `/api/`
2. **Index Path**: Use `/indices` (not `/indexes`)
3. **Document Path**: Follow pattern `/api/indices/{index}/documents`
4. **Search Path**: Use `/_search` suffix for search operations
5. **Content Type**: All requests should use `application/json`
6. **Authentication**: Bearer token in Authorization header
7. **Error Handling**: Check HTTP status codes and parse error responses

## Field Types

The following field types are supported in mappings:

- `text`: Full-text searchable fields
- `keyword`: Exact match fields (categories, tags, etc.)
- `integer`: Integer numbers
- `float`: Floating-point numbers  
- `date`: Date/timestamp fields
- `boolean`: True/false values
- `object`: Nested object structures
- `nested`: Nested document arrays

## Query Types

### Match Query
```json
{
  "match": {
    "field": "title",
    "value": "search term"
  }
}
```

### Term Query
```json
{
  "term": {
    "field": "category",
    "value": "electronics"
  }
}
```

### Range Query
```json
{
  "range": {
    "field": "price",
    "gte": 100,
    "lte": 500
  }
}
```

This documentation ensures the Laravel Scout driver correctly interfaces with the actual Ogini Search Engine API endpoints. 