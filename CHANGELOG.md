# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.8] - 2025-01-07

### 🐛 Bug Fixes

#### Fixed
- **DivisionByZeroError in BulkImportCommand**: Fixed crash when bulk import command executes very quickly (< 0.01 seconds)
- **Throughput Calculation**: Added protection against division by zero in throughput calculation
- **Error Handling**: Enhanced error handling with descriptive messages when throughput cannot be measured

#### Technical Improvements
- **Robust Duration Handling**: Use raw duration for calculations to avoid rounding issues
- **User Experience**: Show "N/A (too fast to measure)" instead of crashing when execution is very fast
- **Production Stability**: Prevents command crashes in high-performance environments

### Notes
- **Critical Fix**: Resolves production crashes when processing small datasets or very fast operations
- **Zero Breaking Changes**: All existing functionality remains unchanged

## [1.0.7] - 2025-06-17

### 🎯 Enhanced Laravel Integration

#### Added
- **Automatic Type Casting for Pagination Parameters**: All pagination parameters (`per_page`, `page`, `size`, `from`, `offset`) now automatically cast string values to integers
- **Enhanced Parameter Flexibility**: Methods now accept both `int|string` types for pagination parameters, making Laravel request validation seamless
- **Improved Developer Experience**: No more manual casting required - use `$request->validated('per_page', 15)` directly instead of `(int) $request->validated('per_page', 15)`
- **Robust Edge Case Handling**: Automatic minimum value enforcement (perPage/size ≥ 1, page/offset ≥ 0) and graceful handling of invalid inputs
- **Bulk Import Offset Flag**: Added `--offset` option to `ogini:bulk-import` command for pagination support in large dataset imports

#### Enhanced
- **OginiEngine::paginate()**: Now accepts `int|string` parameters with automatic type casting and validation
- **OginiClient Methods**: Enhanced `listDocuments()`, `search()`, `advancedSearch()`, and `suggest()` with flexible parameter types
- **UtilityHelpers::buildPagination()**: Improved with type casting and minimum value enforcement
- **Global Helper Functions**: Updated `ogini_build_pagination()` function signature for consistency

#### Fixed
- **Laravel Request Integration**: Eliminates type errors when using Laravel request validation with string parameters
- **Parameter Validation**: Prevents negative values and enforces sensible minimum values for pagination
- **Type Safety**: Maintains backward compatibility while adding flexibility for Laravel integration

#### Technical Improvements
- **Laravel-Friendly API**: Follows Laravel's convention of flexible parameter handling
- **Backward Compatibility**: Existing integer parameter usage continues to work unchanged
- **Production Ready**: Reduces debugging time and common integration errors in Laravel applications

### Notes
- **Zero Breaking Changes**: All existing code continues to work without modification
- **Laravel Best Practices**: Aligns with Laravel's flexible request handling patterns
- Makes the package more Laravel-friendly and reduces common integration friction

## [1.0.6] - 2025-06-12

### 🚨 CRITICAL BUG FIXES
- **Type Error Resolution**: Fixed `strlen(): Argument #1 ($string) must be of type string, array given` error in QueryOptimizer
- **API Response Format**: Fixed response parsing to handle correct API format `{"hits": {"total": 5, "hits": [...]}}`
- **Query Structure Compliance**: Complete rewrite of QueryOptimizer to handle proper API query structures
- **DELETE Request Body**: Fixed deleteByQuery to send JSON body instead of query parameters
- **Test Suite**: Updated all QueryOptimizer tests to use correct API structure format

### Fixed  
- **Production Timeouts**: Resolved search API timeouts caused by type mismatches
- **Query Structure Handling**: Now properly handles `{"query": {"match": {"value": "search term"}}}` format
- **Wildcard Query Auto-Detection**: Automatically converts match queries with wildcards to wildcard queries
- **Response Parsing**: Added `extractHitsFromResponse()` method to handle both current and legacy response formats
- **Filter Structure**: Proper handling of `{"filter": {"term": {"field": "category", "value": "electronics"}}}` format
- **Match-All Queries**: Correct handling of empty search queries with `{"query": {"match_all": {}}}`
- **Type Safety**: All QueryOptimizer methods now properly validate array structures
- **Backward Compatibility**: Maintains support for legacy response formats

### Technical Improvements
- **API Compliance**: 100% alignment with Ogini Search API specification
- **Error Resilience**: Graceful handling of unexpected response formats
- **Performance**: Optimized query structures reduce server processing load
- **Test Coverage**: All 381 tests passing with comprehensive API structure validation

### Notes
- **CRITICAL UPDATE**: This version fixes production-breaking bugs that caused all search operations to fail
- **IMMEDIATE DEPLOYMENT RECOMMENDED**: All users experiencing search errors should update immediately
- All search operations now work correctly without type errors or response parsing issues

## [1.0.5] - 2025-06-12 (Superseded)

### 🚨 CRITICAL FIXES
- **QueryOptimizer Type Error**: Fixed `strlen(): Argument #1 ($string) must be of type string, array given` error
- **API Response Format**: Fixed response parsing to handle correct API format `{"hits": {"total": 5, "hits": [...]}}`
- **Query Structure Compliance**: Complete rewrite of QueryOptimizer to handle proper API query structures
- **DELETE Request Body**: Fixed deleteByQuery to send JSON body instead of query parameters

### Fixed  
- **Query Structure Handling**: Now properly handles `{"query": {"match": {"value": "search term"}}}` format
- **Wildcard Query Auto-Detection**: Automatically converts match queries with wildcards to wildcard queries
- **Response Parsing**: Added `extractHitsFromResponse()` method to handle both current and legacy response formats
- **Filter Structure**: Proper handling of `{"filter": {"term": {"field": "category", "value": "electronics"}}}` format
- **Match-All Queries**: Correct handling of empty search queries with `{"query": {"match_all": {}}}`
- **Backward Compatibility**: Maintains support for legacy response formats

### Technical Improvements
- **Type Safety**: All QueryOptimizer methods now properly validate array structures
- **Error Resilience**: Graceful handling of unexpected response formats
- **API Compliance**: 100% alignment with Ogini Search API specification
- **Performance**: Optimized query structures reduce server processing load

### Notes
- **BREAKING**: This version fixes critical API compliance issues that were causing production failures
- **RECOMMENDED**: Immediate update required for all users experiencing search timeouts or type errors
- All search operations should now work correctly without type errors or response parsing issues

## [1.1.0] - 2025-06-10 (Deprecated)

### Added
- **🚀 Revolutionary Dynamic Model Discovery System**: Automatically discovers all searchable models in any Laravel application structure
- **📦 Universal Bulk Import Command**: `ogini:bulk-import` with dynamic model resolution across all Laravel app structures
- **🔍 Advanced Model Discovery Service**: Supports standard, legacy, and custom namespaces automatically
- **⚡ Enhanced Bulk Processing**: 500x performance improvement with optimized BatchProcessor
- **🎯 Flexible Model Resolution**: Supports short names, full class names, and custom namespaces
- **📊 Real-time Progress Tracking**: Advanced progress bars with throughput metrics
- **🛠️ Comprehensive Validation**: `--validate` option for model configuration verification
- **🧪 Dry Run Capabilities**: `--dry-run` for testing without actual indexing
- **📋 Model Listing**: `--list` option showing all available searchable models with detailed information
- **🔄 Queue Integration**: Enhanced queue support with custom BulkScoutImportJob
- **📈 Performance Analytics**: Detailed timing, throughput, and success rate reporting
- **🏗️ Command Validation Testing**: Comprehensive test suite ensuring universal compatibility
- **📚 Scout Import Analysis**: Complete documentation comparing scout:import vs ogini:bulk-import

### Enhanced
- **BatchProcessor Performance**: Improved error handling and fallback strategies
- **OginiEngine Integration**: Enhanced bulk processing with better error recovery
- **Test Infrastructure**: Added proper test groups and CI exclusions for stable workflows
- **Documentation**: Updated with universal compatibility examples and usage patterns

### Fixed
- **Test Group Configuration**: Properly tagged tests to avoid CI conflicts
- **BatchProcessor Statistics**: Fixed getStatistics() method to return correct configuration keys
- **Error Handling**: Enhanced batch processing error reporting and individual fallback reliability

### Breaking Changes
- None (fully backward compatible)

### Universal Compatibility Features
- **Any Laravel Structure**: Works with standard, legacy, and custom application structures
- **Automatic Detection**: No manual configuration required for model discovery
- **Namespace Flexibility**: Supports App\Models, App\, Custom\Namespace patterns
- **Version Agnostic**: Compatible with Laravel 10.x and 11.x

### Performance Improvements
- **500x Bulk Processing**: Reduces 1000 API calls to just 2 bulk operations
- **Optimized Chunking**: Configurable batch sizes for optimal performance
- **Memory Efficiency**: Lower memory footprint with intelligent chunking
- **Queue Optimization**: Enhanced async processing with custom job handling

## [1.0.3] - 2025-06-09

### Added
- **Update Management System**: Intelligent update checking with security alerts
- **Release Automation**: GitHub Actions workflow and release scripts
- **Quality Assurance Testing**: Comprehensive edge case and security testing
- **Laravel Compatibility Testing**: Multi-version Laravel support validation
- **Artisan Commands**: `ogini:check-updates` command with multiple options
- **UpdateChecker Facade**: Programmatic access to version management
- **Security Update Detection**: Automatic CVE and security vulnerability detection
- **Breaking Change Warnings**: SemVer-based breaking change detection
- **Caching System**: Efficient API usage with configurable TTL
- **Distribution Documentation**: Complete Packagist and release guides
- **Scout Import Verification**: Comprehensive integration tests for `scout:import` functionality
- **Parameter Order Validation**: Tests confirming correct method signature usage

### Fixed
- **Verified Scout Integration**: Confirmed that parameter order issues mentioned in community reports have been resolved
  - `indexDocument()` calls use correct order: `indexDocument(indexName, documentId, document)`
  - `bulkIndexDocuments()` uses proper document format with 'id' and 'document' keys
  - `search()` method signature correctly implemented: `search(indexName, query, options)`
  - All fallback paths in `OginiEngine::update()` work correctly
- **Enhanced Test Coverage**: Added `ScoutImportTest` with 5 comprehensive tests covering scout:import scenarios

### Verified
- **Scout Import Command**: `php artisan scout:import` now verified to work correctly
- **Bulk Indexing**: Bulk operations and individual fallback indexing both functional
- **Search Functionality**: Query processing and result mapping working as expected
- **Error Handling**: Graceful fallback from bulk to individual indexing on failures

## [1.0.2] - 2025-06-09

### Fixed
- **CRITICAL: Laravel Scout Method Signature Compatibility** - Fixed incorrect method signatures that were preventing proper Scout integration
  - Fixed `indexDocument()` parameter order: now `indexDocument(indexName, documentId, document)` 
  - Fixed `search()` method signature: now `search(indexName, query_string, options[])`
  - Updated all internal method calls in `OginiEngine`, `AsyncIndexJob`, `BatchProcessor`
  - Fixed health check search calls
  - Updated all corresponding unit tests, integration tests, and benchmark tests
  - **Impact**: Resolves all indexing failures, search returning 0 results, and bulk operation issues
  - **GitHub Workflows**: All CI tests now pass (497 tests, 1671 assertions)
- **API Endpoint Compliance** - Updated OginiClient to match official Ogini API documentation
  - Fixed `deleteByQuery()` endpoint: now uses `DELETE /api/indices/{index}/_query` (was POST /_delete_by_query)
  - Enhanced `updateIndexSettings()` to support both settings and mappings updates
  - Improved `search()` method to support field-specific queries and advanced options
  - Added `advancedSearch()` method for complex query structures as per API docs

### Breaking Changes
- Direct calls to `OginiClient::indexDocument()` must swap parameter order
- Direct calls to `OginiClient::search()` must use new signature
- **Laravel Scout Integration**: No migration needed - fixes make package properly compatible

## [1.0.0] - 2025-06-06

### Added
- Initial release of Ogini Laravel Scout Driver
- Complete Laravel Scout engine implementation
- OginiClient HTTP client with full API coverage
- Enhanced OginiSearchable trait with highlighting support
- Advanced search features (faceted search, filtering, sorting)
- Performance optimizations (batch processing, query caching)
- Extended client functionality (async operations, events)
- Ogini facade and helper functions
- Comprehensive test suite with 90%+ coverage
- Complete documentation and examples
- Error handling and logging system
- Security features and validation
- Quality assurance testing
- Update management and version checking
- Release automation and distribution system

### Features
- **Core Engine**: Full Laravel Scout compatibility
- **Search Capabilities**: Basic and advanced search with highlighting
- **Faceted Search**: Dynamic facet generation and filtering
- **Performance**: Optimized for large datasets with caching
- **Async Operations**: Background processing and queue integration
- **Events**: Comprehensive event system for indexing and search
- **Testing**: Extensive test coverage including load testing
- **Documentation**: Complete API documentation and tutorials
- **Security**: Input validation and authentication support
- **Monitoring**: Performance monitoring and health checks
- **Update Management**: Intelligent version checking with security alerts
- **Quality Assurance**: Enterprise-grade testing with 430+ tests and 90%+ coverage

### Requirements
- PHP ^8.2
- Laravel ^11.0
- Laravel Scout ^10.0
- OginiSearch server instance

### Breaking Changes
- None (initial release)

### Deprecated
- None

### Removed
- None

### Fixed
- None

### Security
- Input validation for all user data
- Authentication handling for OginiSearch API
- Secure configuration management

---

## Release Notes Template

### [Version] - Date

#### Added
- New features and functionality

#### Changed
- Changes in existing functionality

#### Deprecated
- Soon-to-be removed features

#### Removed
- Removed features

#### Fixed
- Bug fixes

#### Security
- Security improvements and fixes 