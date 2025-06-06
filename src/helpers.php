<?php

use OginiScoutDriver\Helpers\SearchHelpers;
use OginiScoutDriver\Helpers\ConfigHelpers;
use OginiScoutDriver\Helpers\UtilityHelpers;
use OginiScoutDriver\Facades\Ogini;
use OginiScoutDriver\Facades\AsyncOgini;

if (!function_exists('ogini_search')) {
    /**
     * Perform a search using OginiSearch.
     *
     * @param string $index
     * @param string $query
     * @param array $options
     * @return array
     */
    function ogini_search(string $index, string $query, array $options = []): array
    {
        $searchQuery = ['query' => ['match' => ['_all' => $query]]];
        return Ogini::search($index, $searchQuery, $options['size'] ?? 10, $options['from'] ?? 0);
    }
}

if (!function_exists('ogini_fuzzy_search')) {
    /**
     * Perform a fuzzy search using OginiSearch.
     *
     * @param string $index
     * @param string $query
     * @param array $options
     * @return array
     */
    function ogini_fuzzy_search(string $index, string $query, array $options = []): array
    {
        return SearchHelpers::fuzzySearch($index, $query, $options);
    }
}

if (!function_exists('ogini_suggest')) {
    /**
     * Get query suggestions from OginiSearch.
     *
     * @param string $index
     * @param string $text
     * @param int $size
     * @return array
     */
    function ogini_suggest(string $index, string $text, int $size = 10): array
    {
        return Ogini::getQuerySuggestions($index, $text, ['size' => $size]);
    }
}

if (!function_exists('ogini_autocomplete')) {
    /**
     * Get autocomplete suggestions from OginiSearch.
     *
     * @param string $index
     * @param string $prefix
     * @param int $size
     * @return array
     */
    function ogini_autocomplete(string $index, string $prefix, int $size = 10): array
    {
        return Ogini::getAutocompleteSuggestions($index, $prefix, ['size' => $size]);
    }
}

if (!function_exists('ogini_format_results')) {
    /**
     * Format search results for easier consumption.
     *
     * @param array $results
     * @return array
     */
    function ogini_format_results(array $results): array
    {
        return UtilityHelpers::formatSearchResults($results);
    }
}

if (!function_exists('ogini_sanitize_query')) {
    /**
     * Sanitize a search query.
     *
     * @param string $query
     * @return string
     */
    function ogini_sanitize_query(string $query): string
    {
        return UtilityHelpers::sanitizeQuery($query);
    }
}

if (!function_exists('ogini_validate_document')) {
    /**
     * Validate a document before indexing.
     *
     * @param array $document
     * @param array $requiredFields
     * @return array
     */
    function ogini_validate_document(array $document, array $requiredFields = []): array
    {
        return UtilityHelpers::validateDocument($document, $requiredFields);
    }
}

if (!function_exists('ogini_config')) {
    /**
     * Get OginiSearch configuration.
     *
     * @param string|null $key
     * @param mixed $default
     * @return mixed
     */
    function ogini_config(string $key = null, $default = null)
    {
        return ConfigHelpers::getConfig($key, $default);
    }
}

if (!function_exists('ogini_health_check')) {
    /**
     * Check the health of OginiSearch service.
     *
     * @return array
     */
    function ogini_health_check(): array
    {
        return UtilityHelpers::checkServiceHealth();
    }
}

if (!function_exists('ogini_search_across_indices')) {
    /**
     * Search across multiple indices.
     *
     * @param array $indices
     * @param string $query
     * @param array $options
     * @return array
     */
    function ogini_search_across_indices(array $indices, string $query, array $options = []): array
    {
        return SearchHelpers::searchAcrossIndices($indices, $query, $options);
    }
}

if (!function_exists('ogini_search_async')) {
    /**
     * Perform an asynchronous search.
     *
     * @param string $index
     * @param string $query
     * @param array $options
     * @param callable|null $callback
     * @return \GuzzleHttp\Promise\PromiseInterface|string
     */
    function ogini_search_async(string $index, string $query, array $options = [], ?callable $callback = null)
    {
        $searchQuery = ['query' => ['match' => ['_all' => $query]]];
        return AsyncOgini::searchAsync(
            $index,
            $searchQuery,
            $options['size'] ?? 10,
            $options['from'] ?? 0,
            $callback
        );
    }
}

if (!function_exists('ogini_index_document')) {
    /**
     * Index a single document.
     *
     * @param string $index
     * @param array $document
     * @param string|null $id
     * @return array
     */
    function ogini_index_document(string $index, array $document, ?string $id = null): array
    {
        return Ogini::indexDocument($index, $document, $id);
    }
}

if (!function_exists('ogini_index_document_async')) {
    /**
     * Index a document asynchronously.
     *
     * @param string $index
     * @param array $document
     * @param string|null $id
     * @param callable|null $callback
     * @return \GuzzleHttp\Promise\PromiseInterface|string
     */
    function ogini_index_document_async(string $index, array $document, ?string $id = null, ?callable $callback = null)
    {
        return AsyncOgini::indexDocumentAsync($index, $document, $id, $callback);
    }
}

if (!function_exists('ogini_bulk_index')) {
    /**
     * Bulk index multiple documents.
     *
     * @param string $index
     * @param array $documents
     * @return array
     */
    function ogini_bulk_index(string $index, array $documents): array
    {
        return Ogini::bulkIndexDocuments($index, $documents);
    }
}

if (!function_exists('ogini_bulk_index_async')) {
    /**
     * Bulk index documents asynchronously.
     *
     * @param string $index
     * @param array $documents
     * @param callable|null $callback
     * @return \GuzzleHttp\Promise\PromiseInterface|string
     */
    function ogini_bulk_index_async(string $index, array $documents, ?callable $callback = null)
    {
        return AsyncOgini::bulkIndexDocumentsAsync($index, $documents, $callback);
    }
}

if (!function_exists('ogini_delete_document')) {
    /**
     * Delete a document from the index.
     *
     * @param string $index
     * @param string $id
     * @return array
     */
    function ogini_delete_document(string $index, string $id): array
    {
        return Ogini::deleteDocument($index, $id);
    }
}

if (!function_exists('ogini_delete_document_async')) {
    /**
     * Delete a document asynchronously.
     *
     * @param string $index
     * @param string $id
     * @param callable|null $callback
     * @return \GuzzleHttp\Promise\PromiseInterface|string
     */
    function ogini_delete_document_async(string $index, string $id, ?callable $callback = null)
    {
        return AsyncOgini::deleteDocumentAsync($index, $id, $callback);
    }
}

if (!function_exists('ogini_manage_synonyms')) {
    /**
     * Manage synonyms for an index.
     *
     * @param string $index
     * @param array $synonyms
     * @param string $action
     * @return array
     */
    function ogini_manage_synonyms(string $index, array $synonyms, string $action = 'add'): array
    {
        switch ($action) {
            case 'add':
                return Ogini::addSynonyms($index, $synonyms);
            case 'update':
                return Ogini::updateSynonyms($index, $synonyms);
            case 'delete':
                return Ogini::deleteSynonyms($index, $synonyms);
            case 'get':
                return Ogini::getSynonyms($index);
            default:
                throw new InvalidArgumentException("Invalid action: {$action}");
        }
    }
}

if (!function_exists('ogini_manage_stopwords')) {
    /**
     * Manage stopwords for an index.
     *
     * @param string $index
     * @param array $stopwords
     * @param string $action
     * @param string $language
     * @return array
     */
    function ogini_manage_stopwords(string $index, array $stopwords = [], string $action = 'get', string $language = 'en'): array
    {
        switch ($action) {
            case 'configure':
                return Ogini::configureStopwords($index, $stopwords, $language);
            case 'update':
                return Ogini::updateStopwords($index, $stopwords, $language);
            case 'reset':
                return Ogini::resetStopwords($index, $language);
            case 'get':
                return Ogini::getStopwords($index);
            default:
                throw new InvalidArgumentException("Invalid action: {$action}");
        }
    }
}

if (!function_exists('ogini_debug_performance')) {
    /**
     * Debug search performance and log metrics.
     *
     * @param string $query
     * @param array $results
     * @param float $executionTime
     * @return void
     */
    function ogini_debug_performance(string $query, array $results, float $executionTime): void
    {
        UtilityHelpers::debugSearchPerformance($query, $results, $executionTime);
    }
}

if (!function_exists('ogini_generate_job_id')) {
    /**
     * Generate a unique job ID for tracking operations.
     *
     * @param string $prefix
     * @return string
     */
    function ogini_generate_job_id(string $prefix = 'ogini'): string
    {
        return UtilityHelpers::generateJobId($prefix);
    }
}

if (!function_exists('ogini_estimate_index_size')) {
    /**
     * Estimate the index size for a collection of documents.
     *
     * @param array $documents
     * @return array
     */
    function ogini_estimate_index_size(array $documents): array
    {
        return UtilityHelpers::estimateIndexSize($documents);
    }
}

if (!function_exists('ogini_build_pagination')) {
    /**
     * Build pagination metadata from search results.
     *
     * @param array $results
     * @param int $currentPage
     * @param int $perPage
     * @return array
     */
    function ogini_build_pagination(array $results, int $currentPage = 1, int $perPage = 15): array
    {
        return UtilityHelpers::buildPagination($results, $currentPage, $perPage);
    }
}

if (!function_exists('ogini_more_like_this')) {
    /**
     * Perform a more-like-this search.
     *
     * @param string $index
     * @param string $documentId
     * @param array $options
     * @return array
     */
    function ogini_more_like_this(string $index, string $documentId, array $options = []): array
    {
        return SearchHelpers::moreLikeThis($index, $documentId, $options);
    }
}

if (!function_exists('ogini_extract_text')) {
    /**
     * Extract text content from various document formats.
     *
     * @param mixed $content
     * @return string
     */
    function ogini_extract_text($content): string
    {
        return UtilityHelpers::extractTextContent($content);
    }
}
