<?php

namespace OginiScoutDriver\Helpers;

use OginiScoutDriver\Facades\Ogini;
use OginiScoutDriver\Facades\AsyncOgini;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class UtilityHelpers
{
    /**
     * Generate a unique job ID for tracking operations.
     *
     * @param string $prefix
     * @return string
     */
    public static function generateJobId(string $prefix = 'ogini'): string
    {
        return $prefix . '_' . Str::random(16) . '_' . time();
    }

    /**
     * Format search results for easier consumption.
     *
     * @param array $rawResults
     * @return array
     */
    public static function formatSearchResults(array $rawResults): array
    {
        $formatted = [
            'total' => $rawResults['total'] ?? 0,
            'took' => $rawResults['took'] ?? 0,
            'max_score' => $rawResults['max_score'] ?? 0,
            'hits' => [],
        ];

        $hits = $rawResults['hits'] ?? [];
        foreach ($hits as $hit) {
            $formatted['hits'][] = [
                'id' => $hit['_id'] ?? null,
                'score' => $hit['_score'] ?? 0,
                'source' => $hit['_source'] ?? [],
                'highlight' => $hit['highlight'] ?? [],
            ];
        }

        return $formatted;
    }

    /**
     * Extract text content from various document formats.
     *
     * @param mixed $content
     * @return string
     */
    public static function extractTextContent($content): string
    {
        if (is_string($content)) {
            return strip_tags($content);
        }

        if (is_array($content)) {
            $text = '';
            foreach ($content as $value) {
                if (is_string($value)) {
                    $text .= ' ' . strip_tags($value);
                } elseif (is_array($value)) {
                    $text .= ' ' . self::extractTextContent($value);
                }
            }
            return trim($text);
        }

        return (string) $content;
    }

    /**
     * Validate document structure before indexing.
     *
     * @param array $document
     * @param array $requiredFields
     * @return array Array of validation errors
     */
    public static function validateDocument(array $document, array $requiredFields = []): array
    {
        $errors = [];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($document[$field]) || empty($document[$field])) {
                $errors[] = "Required field '{$field}' is missing or empty";
            }
        }

        // Check for extremely large fields
        foreach ($document as $field => $value) {
            if (is_string($value) && strlen($value) > 10000000) { // 10MB limit
                $errors[] = "Field '{$field}' is too large (> 10MB)";
            }
        }

        // Check for deeply nested structures
        if (self::getArrayDepth($document) > 20) {
            $errors[] = "Document structure is too deeply nested (> 20 levels)";
        }

        return $errors;
    }

    /**
     * Sanitize search query to prevent injection attacks.
     *
     * @param string $query
     * @return string
     */
    public static function sanitizeQuery(string $query): string
    {
        // Remove potentially dangerous characters
        $query = preg_replace('/[<>"\']/', '', $query);

        // Limit query length
        $query = substr($query, 0, 1000);

        // Remove excessive whitespace
        $query = preg_replace('/\s+/', ' ', trim($query));

        return $query;
    }

    /**
     * Build pagination metadata from search results.
     *
     * @param array $results
     * @param int|string $currentPage
     * @param int|string $perPage
     * @return array
     */
    public static function buildPagination(array $results, int|string $currentPage = 1, int|string $perPage = 15): array
    {
        // Automatically cast string parameters to integers for Laravel compatibility
        $currentPage = (int) $currentPage;
        $perPage = (int) $perPage;

        // Ensure minimum values
        $currentPage = max(1, $currentPage);
        $perPage = max(1, $perPage);

        $total = $results['total'] ?? 0;
        $totalPages = ceil($total / $perPage);

        return [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
            'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
        ];
    }

    /**
     * Debug search performance and log metrics.
     *
     * @param string $query
     * @param array $results
     * @param float $executionTime
     * @return void
     */
    public static function debugSearchPerformance(string $query, array $results, float $executionTime): void
    {
        $metrics = [
            'query' => substr($query, 0, 100), // Truncate for logging
            'execution_time_ms' => round($executionTime * 1000, 2),
            'total_results' => $results['total'] ?? 0,
            'search_time_ms' => $results['took'] ?? 0,
            'max_score' => $results['max_score'] ?? 0,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];

        Log::debug('Search performance metrics', $metrics);

        // Log warnings for slow queries
        if ($executionTime > 1.0) {
            Log::warning('Slow search query detected', $metrics);
        }

        // Log warnings for large result sets
        if (($results['total'] ?? 0) > 10000) {
            Log::warning('Large result set returned', $metrics);
        }
    }

    /**
     * Check the health of OginiSearch service.
     *
     * @return array
     */
    public static function checkServiceHealth(): array
    {
        $health = [
            'status' => 'unknown',
            'response_time' => null,
            'error' => null,
            'timestamp' => now()->toISOString(),
        ];

        try {
            $start = microtime(true);
            $indices = Ogini::listIndices();
            $responseTime = (microtime(true) - $start) * 1000;

            $health['status'] = 'healthy';
            $health['response_time'] = round($responseTime, 2);
            $health['indices_count'] = count($indices['indices'] ?? []);
        } catch (\Exception $e) {
            $health['status'] = 'unhealthy';
            $health['error'] = $e->getMessage();
        }

        return $health;
    }

    /**
     * Estimate index size for a collection of documents.
     *
     * @param array $documents
     * @return array
     */
    public static function estimateIndexSize(array $documents): array
    {
        $totalSize = 0;
        $documentCount = count($documents);
        $fieldStats = [];

        foreach ($documents as $document) {
            $docSize = strlen(json_encode($document));
            $totalSize += $docSize;

            foreach ($document as $field => $value) {
                if (!isset($fieldStats[$field])) {
                    $fieldStats[$field] = [
                        'count' => 0,
                        'total_size' => 0,
                        'avg_size' => 0,
                        'type' => gettype($value),
                    ];
                }

                $fieldStats[$field]['count']++;
                $fieldStats[$field]['total_size'] += strlen(json_encode($value));
                $fieldStats[$field]['avg_size'] = $fieldStats[$field]['total_size'] / $fieldStats[$field]['count'];
            }
        }

        return [
            'document_count' => $documentCount,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'avg_document_size_bytes' => $documentCount > 0 ? round($totalSize / $documentCount, 2) : 0,
            'field_statistics' => $fieldStats,
            'estimated_index_overhead_mb' => round(($totalSize * 0.3) / 1024 / 1024, 2), // ~30% overhead estimate
        ];
    }

    /**
     * Generate search analytics summary.
     *
     * @param array $searchResults
     * @return array
     */
    public static function generateSearchAnalytics(array $searchResults): array
    {
        $analytics = [
            'total_searches' => count($searchResults),
            'avg_response_time' => 0,
            'avg_result_count' => 0,
            'success_rate' => 0,
            'popular_terms' => [],
            'zero_result_queries' => [],
        ];

        if (empty($searchResults)) {
            return $analytics;
        }

        $totalResponseTime = 0;
        $totalResults = 0;
        $successCount = 0;
        $termFrequency = [];

        foreach ($searchResults as $result) {
            if (isset($result['response_time'])) {
                $totalResponseTime += $result['response_time'];
            }

            if (isset($result['total_results'])) {
                $totalResults += $result['total_results'];
                if ($result['total_results'] > 0) {
                    $successCount++;
                } else {
                    $analytics['zero_result_queries'][] = $result['query'] ?? 'unknown';
                }
            }

            // Extract search terms for frequency analysis
            if (isset($result['query'])) {
                $terms = explode(' ', strtolower(trim($result['query'])));
                foreach ($terms as $term) {
                    $term = trim($term);
                    if (strlen($term) > 2) { // Ignore very short terms
                        $termFrequency[$term] = ($termFrequency[$term] ?? 0) + 1;
                    }
                }
            }
        }

        $analytics['avg_response_time'] = round($totalResponseTime / count($searchResults), 2);
        $analytics['avg_result_count'] = round($totalResults / count($searchResults), 2);
        $analytics['success_rate'] = round(($successCount / count($searchResults)) * 100, 2);

        // Sort terms by frequency and get top 10
        arsort($termFrequency);
        $analytics['popular_terms'] = array_slice($termFrequency, 0, 10, true);

        return $analytics;
    }

    /**
     * Clean up old cache entries and temporary data.
     *
     * @param int $maxAgeHours
     * @return int Number of items cleaned
     */
    public static function cleanupOldData(int $maxAgeHours = 24): int
    {
        $cleaned = 0;

        try {
            // This would typically clean up cache entries, logs, etc.
            // Implementation depends on your caching strategy
            Log::info("Cleanup operation completed", ['cleaned_items' => $cleaned, 'max_age_hours' => $maxAgeHours]);
        } catch (\Exception $e) {
            Log::error("Cleanup operation failed", ['error' => $e->getMessage()]);
        }

        return $cleaned;
    }

    /**
     * Get the depth of a nested array.
     *
     * @param array $array
     * @return int
     */
    protected static function getArrayDepth(array $array): int
    {
        $maxDepth = 1;

        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = self::getArrayDepth($value) + 1;
                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                }
            }
        }

        return $maxDepth;
    }
}
