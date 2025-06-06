<?php

namespace OginiScoutDriver\Exceptions;

/**
 * Centralized error codes for OginiSearch operations.
 */
class ErrorCodes
{
    // Connection Errors (1000-1099)
    public const CONNECTION_FAILED = 'OGINI_1001';
    public const CONNECTION_TIMEOUT = 'OGINI_1002';
    public const CONNECTION_REFUSED = 'OGINI_1003';
    public const DNS_RESOLUTION_FAILED = 'OGINI_1004';
    public const SSL_VERIFICATION_FAILED = 'OGINI_1005';
    public const NETWORK_UNREACHABLE = 'OGINI_1006';

    // Authentication & Authorization Errors (1100-1199)
    public const AUTHENTICATION_FAILED = 'OGINI_1101';
    public const INVALID_API_KEY = 'OGINI_1102';
    public const API_KEY_EXPIRED = 'OGINI_1103';
    public const INSUFFICIENT_PERMISSIONS = 'OGINI_1104';
    public const TOKEN_REFRESH_REQUIRED = 'OGINI_1105';

    // Rate Limiting Errors (1200-1299)
    public const RATE_LIMIT_EXCEEDED = 'OGINI_1201';
    public const QUOTA_EXCEEDED = 'OGINI_1202';
    public const CONCURRENT_LIMIT_EXCEEDED = 'OGINI_1203';
    public const DAILY_LIMIT_EXCEEDED = 'OGINI_1204';

    // Validation Errors (1300-1399)
    public const VALIDATION_FAILED = 'OGINI_1301';
    public const INVALID_INDEX_NAME = 'OGINI_1302';
    public const INVALID_DOCUMENT_STRUCTURE = 'OGINI_1303';
    public const INVALID_QUERY_SYNTAX = 'OGINI_1304';
    public const REQUIRED_FIELD_MISSING = 'OGINI_1305';
    public const FIELD_TYPE_MISMATCH = 'OGINI_1306';
    public const INVALID_FIELD_VALUE = 'OGINI_1307';

    // Index Errors (1400-1499)
    public const INDEX_NOT_FOUND = 'OGINI_1401';
    public const INDEX_ALREADY_EXISTS = 'OGINI_1402';
    public const INDEX_CREATION_FAILED = 'OGINI_1403';
    public const INDEX_DELETION_FAILED = 'OGINI_1404';
    public const INDEX_UPDATE_FAILED = 'OGINI_1405';
    public const INDEX_LOCKED = 'OGINI_1406';
    public const INDEX_CORRUPTED = 'OGINI_1407';

    // Document Errors (1500-1599)
    public const DOCUMENT_NOT_FOUND = 'OGINI_1501';
    public const DOCUMENT_TOO_LARGE = 'OGINI_1502';
    public const DOCUMENT_INDEXING_FAILED = 'OGINI_1503';
    public const DOCUMENT_UPDATE_FAILED = 'OGINI_1504';
    public const DOCUMENT_DELETION_FAILED = 'OGINI_1505';
    public const BULK_OPERATION_FAILED = 'OGINI_1506';
    public const DOCUMENT_VERSION_CONFLICT = 'OGINI_1507';

    // Search Errors (1600-1699)
    public const SEARCH_FAILED = 'OGINI_1601';
    public const SEARCH_TIMEOUT = 'OGINI_1602';
    public const QUERY_TOO_COMPLEX = 'OGINI_1603';
    public const INVALID_SEARCH_PARAMETERS = 'OGINI_1604';
    public const SEARCH_RESULTS_TOO_LARGE = 'OGINI_1605';
    public const FACET_QUERY_FAILED = 'OGINI_1606';
    public const AGGREGATION_FAILED = 'OGINI_1607';

    // Performance Errors (1700-1799)
    public const MEMORY_LIMIT_EXCEEDED = 'OGINI_1701';
    public const EXECUTION_TIMEOUT = 'OGINI_1702';
    public const RESOURCE_EXHAUSTED = 'OGINI_1703';
    public const CACHE_OPERATION_FAILED = 'OGINI_1704';
    public const CONNECTION_POOL_EXHAUSTED = 'OGINI_1705';
    public const BATCH_SIZE_EXCEEDED = 'OGINI_1706';

    // Server Errors (1800-1899)
    public const INTERNAL_SERVER_ERROR = 'OGINI_1801';
    public const SERVICE_UNAVAILABLE = 'OGINI_1802';
    public const MAINTENANCE_MODE = 'OGINI_1803';
    public const DATABASE_ERROR = 'OGINI_1804';
    public const STORAGE_ERROR = 'OGINI_1805';
    public const CONFIGURATION_ERROR = 'OGINI_1806';

    // Configuration Errors (1900-1999)
    public const INVALID_CONFIGURATION = 'OGINI_1901';
    public const MISSING_CONFIGURATION = 'OGINI_1902';
    public const CONFIGURATION_VALIDATION_FAILED = 'OGINI_1903';
    public const ENVIRONMENT_MISMATCH = 'OGINI_1904';

    /**
     * Get human-readable error descriptions.
     *
     * @return array
     */
    public static function getDescriptions(): array
    {
        return [
            // Connection Errors
            self::CONNECTION_FAILED => 'Failed to establish connection to OginiSearch',
            self::CONNECTION_TIMEOUT => 'Connection to OginiSearch timed out',
            self::CONNECTION_REFUSED => 'Connection to OginiSearch was refused',
            self::DNS_RESOLUTION_FAILED => 'Failed to resolve OginiSearch hostname',
            self::SSL_VERIFICATION_FAILED => 'SSL certificate verification failed',
            self::NETWORK_UNREACHABLE => 'OginiSearch server is not reachable',

            // Authentication & Authorization
            self::AUTHENTICATION_FAILED => 'Authentication with OginiSearch failed',
            self::INVALID_API_KEY => 'The provided API key is invalid',
            self::API_KEY_EXPIRED => 'The API key has expired',
            self::INSUFFICIENT_PERMISSIONS => 'Insufficient permissions for this operation',
            self::TOKEN_REFRESH_REQUIRED => 'Access token needs to be refreshed',

            // Rate Limiting
            self::RATE_LIMIT_EXCEEDED => 'API rate limit has been exceeded',
            self::QUOTA_EXCEEDED => 'API quota has been exceeded',
            self::CONCURRENT_LIMIT_EXCEEDED => 'Too many concurrent requests',
            self::DAILY_LIMIT_EXCEEDED => 'Daily request limit has been exceeded',

            // Validation
            self::VALIDATION_FAILED => 'Request validation failed',
            self::INVALID_INDEX_NAME => 'The index name is invalid',
            self::INVALID_DOCUMENT_STRUCTURE => 'The document structure is invalid',
            self::INVALID_QUERY_SYNTAX => 'The search query syntax is invalid',
            self::REQUIRED_FIELD_MISSING => 'A required field is missing',
            self::FIELD_TYPE_MISMATCH => 'Field type does not match expected type',
            self::INVALID_FIELD_VALUE => 'Field value is invalid',

            // Index Operations
            self::INDEX_NOT_FOUND => 'The specified index was not found',
            self::INDEX_ALREADY_EXISTS => 'An index with this name already exists',
            self::INDEX_CREATION_FAILED => 'Failed to create the index',
            self::INDEX_DELETION_FAILED => 'Failed to delete the index',
            self::INDEX_UPDATE_FAILED => 'Failed to update the index',
            self::INDEX_LOCKED => 'The index is currently locked',
            self::INDEX_CORRUPTED => 'The index is corrupted and needs rebuilding',

            // Document Operations
            self::DOCUMENT_NOT_FOUND => 'The specified document was not found',
            self::DOCUMENT_TOO_LARGE => 'The document exceeds the maximum size limit',
            self::DOCUMENT_INDEXING_FAILED => 'Failed to index the document',
            self::DOCUMENT_UPDATE_FAILED => 'Failed to update the document',
            self::DOCUMENT_DELETION_FAILED => 'Failed to delete the document',
            self::BULK_OPERATION_FAILED => 'Bulk operation failed partially or completely',
            self::DOCUMENT_VERSION_CONFLICT => 'Document version conflict detected',

            // Search Operations
            self::SEARCH_FAILED => 'Search operation failed',
            self::SEARCH_TIMEOUT => 'Search operation timed out',
            self::QUERY_TOO_COMPLEX => 'The search query is too complex',
            self::INVALID_SEARCH_PARAMETERS => 'Invalid search parameters provided',
            self::SEARCH_RESULTS_TOO_LARGE => 'Search results exceed maximum size limit',
            self::FACET_QUERY_FAILED => 'Faceted search query failed',
            self::AGGREGATION_FAILED => 'Search aggregation failed',

            // Performance
            self::MEMORY_LIMIT_EXCEEDED => 'Memory limit exceeded during operation',
            self::EXECUTION_TIMEOUT => 'Operation execution timed out',
            self::RESOURCE_EXHAUSTED => 'System resources have been exhausted',
            self::CACHE_OPERATION_FAILED => 'Cache operation failed',
            self::CONNECTION_POOL_EXHAUSTED => 'Connection pool has been exhausted',
            self::BATCH_SIZE_EXCEEDED => 'Batch size exceeds maximum allowed',

            // Server
            self::INTERNAL_SERVER_ERROR => 'Internal server error occurred',
            self::SERVICE_UNAVAILABLE => 'OginiSearch service is temporarily unavailable',
            self::MAINTENANCE_MODE => 'OginiSearch is in maintenance mode',
            self::DATABASE_ERROR => 'Database error occurred',
            self::STORAGE_ERROR => 'Storage system error occurred',
            self::CONFIGURATION_ERROR => 'Server configuration error',

            // Configuration
            self::INVALID_CONFIGURATION => 'Invalid configuration provided',
            self::MISSING_CONFIGURATION => 'Required configuration is missing',
            self::CONFIGURATION_VALIDATION_FAILED => 'Configuration validation failed',
            self::ENVIRONMENT_MISMATCH => 'Environment configuration mismatch',
        ];
    }

    /**
     * Get error description for a specific error code.
     *
     * @param string $errorCode
     * @return string
     */
    public static function getDescription(string $errorCode): string
    {
        $descriptions = self::getDescriptions();
        return $descriptions[$errorCode] ?? 'Unknown error occurred';
    }

    /**
     * Check if an error code is retryable.
     *
     * @param string $errorCode
     * @return bool
     */
    public static function isRetryable(string $errorCode): bool
    {
        $retryableErrors = [
            self::CONNECTION_TIMEOUT,
            self::CONNECTION_REFUSED,
            self::NETWORK_UNREACHABLE,
            self::RATE_LIMIT_EXCEEDED,
            self::SEARCH_TIMEOUT,
            self::EXECUTION_TIMEOUT,
            self::INTERNAL_SERVER_ERROR,
            self::SERVICE_UNAVAILABLE,
            self::DATABASE_ERROR,
            self::STORAGE_ERROR,
            self::CONNECTION_POOL_EXHAUSTED,
        ];

        return in_array($errorCode, $retryableErrors);
    }

    /**
     * Get suggested retry delay for an error code.
     *
     * @param string $errorCode
     * @return int Delay in seconds
     */
    public static function getRetryDelay(string $errorCode): int
    {
        $delayMap = [
            self::CONNECTION_TIMEOUT => 5,
            self::CONNECTION_REFUSED => 10,
            self::NETWORK_UNREACHABLE => 15,
            self::RATE_LIMIT_EXCEEDED => 60,
            self::SEARCH_TIMEOUT => 2,
            self::EXECUTION_TIMEOUT => 3,
            self::INTERNAL_SERVER_ERROR => 5,
            self::SERVICE_UNAVAILABLE => 30,
            self::DATABASE_ERROR => 10,
            self::STORAGE_ERROR => 5,
            self::CONNECTION_POOL_EXHAUSTED => 2,
        ];

        return $delayMap[$errorCode] ?? 0;
    }

    /**
     * Get error category for an error code.
     *
     * @param string $errorCode
     * @return string
     */
    public static function getCategory(string $errorCode): string
    {
        $code = (int) substr($errorCode, 6, 2); // Extract the category number

        return match (true) {
            $code >= 10 && $code <= 10 => 'connection',
            $code >= 11 && $code <= 11 => 'authentication',
            $code >= 12 && $code <= 12 => 'rate_limiting',
            $code >= 13 && $code <= 13 => 'validation',
            $code >= 14 && $code <= 14 => 'index',
            $code >= 15 && $code <= 15 => 'document',
            $code >= 16 && $code <= 16 => 'search',
            $code >= 17 && $code <= 17 => 'performance',
            $code >= 18 && $code <= 18 => 'server',
            $code >= 19 && $code <= 19 => 'configuration',
            default => 'unknown'
        };
    }
}
