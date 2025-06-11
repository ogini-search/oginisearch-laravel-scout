<?php

namespace OginiScoutDriver\Performance;

use Laravel\Scout\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QueryOptimizer
{
    protected array $config;
    protected array $optimizationRules;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'enable_query_rewriting' => true,
            'enable_field_optimization' => true,
            'enable_filter_optimization' => true,
            'max_query_length' => 1000,
            'enable_wildcard_optimization' => true,
            'enable_phrase_detection' => true,
            'boost_exact_matches' => true,
            'min_term_length' => 3,
            'max_complexity_score' => 15,
            'performance_check_threshold' => 100,
            'wildcard_penalty' => 5,
            'phrase_boost' => 1.5,
            'exact_match_boost' => 2.0,
            'fuzzy_match_boost' => 1.0,
        ], $config);

        $this->initializeOptimizationRules();
    }

    /**
     * Initialize optimization rules.
     */
    protected function initializeOptimizationRules(): void
    {
        $this->optimizationRules = [
            'remove_stopwords' => $this->config['enable_query_rewriting'],
            'optimize_wildcards' => $this->config['enable_wildcard_optimization'],
            'detect_phrases' => $this->config['enable_phrase_detection'],
            'boost_exact_matches' => $this->config['boost_exact_matches'],
            'optimize_filters' => $this->config['enable_filter_optimization'],
            'optimize_fields' => $this->config['enable_field_optimization'],
        ];
    }

    /**
     * Optimize a search query for better performance.
     */
    public function optimizeQuery(array $searchQuery): array
    {
        $originalQuery = $searchQuery;
        $optimizedQuery = $searchQuery;

        // Apply query optimizations
        if ($this->optimizationRules['remove_stopwords']) {
            $optimizedQuery = $this->removeStopwords($optimizedQuery);
        }

        if ($this->optimizationRules['optimize_wildcards']) {
            $optimizedQuery = $this->optimizeWildcards($optimizedQuery);
        }

        if ($this->optimizationRules['detect_phrases']) {
            $optimizedQuery = $this->detectAndOptimizePhrases($optimizedQuery);
        }

        if ($this->optimizationRules['boost_exact_matches']) {
            $optimizedQuery = $this->addExactMatchBoost($optimizedQuery);
        }

        if ($this->optimizationRules['optimize_filters']) {
            $optimizedQuery = $this->optimizeFilters($optimizedQuery);
        }

        if ($this->optimizationRules['optimize_fields']) {
            $optimizedQuery = $this->optimizeFieldWeights($optimizedQuery);
        }

        // Validate optimized query
        $optimizedQuery = $this->validateOptimizedQuery($optimizedQuery);

        // Log optimization if significant changes were made
        $this->logOptimization($originalQuery, $optimizedQuery);

        return $optimizedQuery;
    }

    /**
     * Remove common stopwords from search query.
     */
    protected function removeStopwords(array $query): array
    {
        $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        // Handle the new API structure where query is an object
        if (isset($query['query'])) {
            if (is_array($query['query'])) {
                // New API structure: {"query": {"match": {"value": "search term"}}}
                if (isset($query['query']['match']['value']) && is_string($query['query']['match']['value'])) {
                    $searchTerm = $query['query']['match']['value'];
                    $words = explode(' ', strtolower($searchTerm));
                    $filteredWords = array_filter($words, function ($word) use ($stopwords) {
                        return !in_array(trim($word), $stopwords) && strlen(trim($word)) >= $this->config['min_term_length'];
                    });

                    if (count($filteredWords) > 0) {
                        $query['query']['match']['value'] = implode(' ', $filteredWords);
                    }
                }
            } elseif (is_string($query['query'])) {
                // Legacy structure: {"query": "search term"}
                $words = explode(' ', strtolower($query['query']));
                $filteredWords = array_filter($words, function ($word) use ($stopwords) {
                    return !in_array(trim($word), $stopwords) && strlen(trim($word)) >= $this->config['min_term_length'];
                });

                if (count($filteredWords) > 0) {
                    $query['query'] = implode(' ', $filteredWords);
                }
            }
        }

        return $query;
    }

    /**
     * Optimize wildcard usage in queries.
     */
    protected function optimizeWildcards(array $query): array
    {
        // Handle the new API structure where query is an object
        if (isset($query['query'])) {
            if (is_array($query['query'])) {
                // New API structure: {"query": {"match": {"value": "search term"}}}
                if (isset($query['query']['match']['value']) && is_string($query['query']['match']['value'])) {
                    $queryText = $query['query']['match']['value'];

                    // Remove excessive wildcards
                    $queryText = preg_replace('/\*{2,}/', '*', $queryText);

                    // Add wildcard to end of words for better matching (if not already present)
                    if (!str_contains($queryText, '*') && !str_contains($queryText, '"')) {
                        $words = explode(' ', $queryText);
                        $words = array_map(function ($word) {
                            if (strlen(trim($word)) >= $this->config['min_term_length']) {
                                return trim($word) . '*';
                            }
                            return $word;
                        }, $words);
                        $queryText = implode(' ', $words);
                    }

                    $query['query']['match']['value'] = $queryText;
                }
            } elseif (is_string($query['query'])) {
                // Legacy structure: {"query": "search term"}
                $queryText = $query['query'];

                // Remove excessive wildcards
                $queryText = preg_replace('/\*{2,}/', '*', $queryText);

                // Add wildcard to end of words for better matching (if not already present)
                if (!str_contains($queryText, '*') && !str_contains($queryText, '"')) {
                    $words = explode(' ', $queryText);
                    $words = array_map(function ($word) {
                        if (strlen(trim($word)) >= $this->config['min_term_length']) {
                            return trim($word) . '*';
                        }
                        return $word;
                    }, $words);
                    $queryText = implode(' ', $words);
                }

                $query['query'] = $queryText;
            }
        }

        return $query;
    }

    /**
     * Detect phrases and optimize them for better matching.
     */
    protected function detectAndOptimizePhrases(array $query): array
    {
        // Handle the new API structure where query is an object
        if (isset($query['query'])) {
            if (is_array($query['query'])) {
                // New API structure: {"query": {"match": {"value": "search term"}}}
                if (isset($query['query']['match']['value']) && is_string($query['query']['match']['value'])) {
                    $queryText = $query['query']['match']['value'];

                    // Detect quoted phrases and preserve them
                    if (preg_match('/"([^"]+)"/', $queryText)) {
                        // Already has phrases, optimize within them
                        $queryText = preg_replace_callback('/"([^"]+)"/', function ($matches) {
                            return '"' . trim($matches[1]) . '"';
                        }, $queryText);
                    } else {
                        // Detect potential phrases (consecutive words)
                        $words = explode(' ', $queryText);
                        if (count($words) > 1) {
                            // Create a phrase query for better relevance
                            $query['phrase_query'] = '"' . trim($queryText) . '"';
                        }
                    }

                    $query['query']['match']['value'] = $queryText;
                }
            } elseif (is_string($query['query'])) {
                // Legacy structure: {"query": "search term"}
                $queryText = $query['query'];

                // Detect quoted phrases and preserve them
                if (preg_match('/"([^"]+)"/', $queryText)) {
                    // Already has phrases, optimize within them
                    $queryText = preg_replace_callback('/"([^"]+)"/', function ($matches) {
                        return '"' . trim($matches[1]) . '"';
                    }, $queryText);
                } else {
                    // Detect potential phrases (consecutive words)
                    $words = explode(' ', $queryText);
                    if (count($words) > 1) {
                        // Create a phrase query for better relevance
                        $query['phrase_query'] = '"' . trim($queryText) . '"';
                    }
                }

                $query['query'] = $queryText;
            }
        }

        return $query;
    }

    /**
     * Add exact match boosting.
     */
    protected function addExactMatchBoost(array $query): array
    {
        // Handle both new API structure and legacy structure
        if (isset($query['query'])) {
            if (is_array($query['query']) && isset($query['query']['match']['value'])) {
                // New API structure: {"query": {"match": {"value": "search term"}}}
                $query['boost'] = [
                    'exact_match' => $this->config['exact_match_boost'],
                    'phrase_match' => $this->config['phrase_boost'],
                    'fuzzy_match' => $this->config['fuzzy_match_boost'],
                ];
            } elseif (is_string($query['query'])) {
                // Legacy structure: {"query": "search term"}
                $query['boost'] = [
                    'exact_match' => $this->config['exact_match_boost'],
                    'phrase_match' => $this->config['phrase_boost'],
                    'fuzzy_match' => $this->config['fuzzy_match_boost'],
                ];
            }
        }

        return $query;
    }

    /**
     * Optimize filter conditions for better performance.
     */
    protected function optimizeFilters(array $query): array
    {
        if (isset($query['filter']) && is_array($query['filter'])) {
            // Check if this is the correct structure: {"filter": {"bool": {"must": [...]}}}
            if (isset($query['filter']['bool']['must']) && is_array($query['filter']['bool']['must'])) {
                // Optimize the filters within the bool.must array
                $filters = $query['filter']['bool']['must'];

                // Sort filters by selectivity (more selective filters first)
                $optimizedFilters = $this->sortFiltersBySelectivity($filters);

                // Combine similar filters
                $optimizedFilters = $this->combineSimilarFilters($optimizedFilters);

                $query['filter']['bool']['must'] = $optimizedFilters;
            } elseif (isset($query['filter']['term'])) {
                // Single term filter, leave as is
                // No optimization needed for single filters
            } else {
                // Handle legacy or other filter structures
                // Treat the entire filter as an array of filters (legacy behavior)
                $filters = $query['filter'];

                // Only apply optimization if it's actually an array of filter objects
                $isFilterArray = true;
                foreach ($filters as $filter) {
                    if (!is_array($filter)) {
                        $isFilterArray = false;
                        break;
                    }
                }

                if ($isFilterArray) {
                    // Sort filters by selectivity (more selective filters first)
                    $optimizedFilters = $this->sortFiltersBySelectivity($filters);

                    // Combine similar filters
                    $optimizedFilters = $this->combineSimilarFilters($optimizedFilters);

                    $query['filter'] = $optimizedFilters;
                }
            }
        }

        return $query;
    }

    /**
     * Sort filters by selectivity for better performance.
     */
    protected function sortFiltersBySelectivity(array $filters): array
    {
        // Define selectivity order (more selective filters first)
        $selectivityOrder = [
            'range' => 1,
            'term' => 2,
            'terms' => 3,
            'match' => 4,
            'wildcard' => $this->config['wildcard_penalty'],
        ];

        usort($filters, function ($a, $b) use ($selectivityOrder) {
            $aType = $this->getFilterType($a);
            $bType = $this->getFilterType($b);

            $aPriority = $selectivityOrder[$aType] ?? 99;
            $bPriority = $selectivityOrder[$bType] ?? 99;

            return $aPriority <=> $bPriority;
        });

        return $filters;
    }

    /**
     * Get the type of a filter.
     */
    protected function getFilterType(array $filter): string
    {
        if (isset($filter['range'])) return 'range';
        if (isset($filter['term'])) return 'term';
        if (isset($filter['terms'])) return 'terms';
        if (isset($filter['match'])) return 'match';
        if (isset($filter['wildcard'])) return 'wildcard';

        return 'unknown';
    }

    /**
     * Combine similar filters for efficiency.
     */
    protected function combineSimilarFilters(array $filters): array
    {
        $combined = [];
        $termFilters = [];

        foreach ($filters as $filter) {
            if (isset($filter['term'])) {
                $field = array_keys($filter['term'])[0];
                if (!isset($termFilters[$field])) {
                    $termFilters[$field] = [];
                }
                $termFilters[$field][] = $filter['term'][$field];
            } else {
                $combined[] = $filter;
            }
        }

        // Convert multiple term filters on same field to terms filter
        foreach ($termFilters as $field => $values) {
            if (count($values) > 1) {
                $combined[] = ['terms' => [$field => array_unique($values)]];
            } else {
                $combined[] = ['term' => [$field => $values[0]]];
            }
        }

        return $combined;
    }

    /**
     * Optimize field weights for better relevance.
     */
    protected function optimizeFieldWeights(array $query): array
    {
        if (isset($query['fields'])) {
            // Define default field weights based on importance
            $fieldWeights = [
                'title' => 3.0,
                'name' => 2.5,
                'description' => 1.5,
                'content' => 1.0,
                'tags' => 1.2,
            ];

            if (is_array($query['fields'])) {
                $weightedFields = [];
                foreach ($query['fields'] as $field) {
                    $weight = $fieldWeights[$field] ?? 1.0;
                    $weightedFields[] = $field . '^' . $weight;
                }
                $query['fields'] = $weightedFields;
            }
        }

        return $query;
    }

    /**
     * Validate the optimized query to ensure it's still valid.
     */
    protected function validateOptimizedQuery(array $query): array
    {
        // Handle the new API structure where query is an object
        if (isset($query['query'])) {
            if (is_array($query['query'])) {
                // New API structure: {"query": {"match": {"value": "search term"}}}
                if (isset($query['query']['match']['value'])) {
                    $searchValue = $query['query']['match']['value'];

                    // Check query length only if it's a string
                    if (is_string($searchValue) && strlen($searchValue) > $this->config['max_query_length']) {
                        $query['query']['match']['value'] = substr($searchValue, 0, $this->config['max_query_length']);
                    }

                    // Ensure query value is not empty
                    if (is_string($searchValue) && empty(trim($searchValue))) {
                        $query['query'] = ['match_all' => []];
                    }
                }

                // If no match value, default to match_all
                if (!isset($query['query']['match']['value']) && !isset($query['query']['match_all'])) {
                    $query['query'] = ['match_all' => []];
                }
            } elseif (is_string($query['query'])) {
                // Legacy structure: {"query": "search term"}
                // Check query length
                if (strlen($query['query']) > $this->config['max_query_length']) {
                    $query['query'] = substr($query['query'], 0, $this->config['max_query_length']);
                }

                // Ensure query is not empty
                if (empty(trim($query['query']))) {
                    $query['query'] = '*';
                }
            }
        } else {
            // No query provided, default to match_all
            $query['query'] = ['match_all' => []];
        }

        // Validate filter structure
        if (isset($query['filter']) && !is_array($query['filter'])) {
            unset($query['filter']);
        }

        return $query;
    }

    /**
     * Log query optimization details.
     */
    protected function logOptimization(array $original, array $optimized): void
    {
        $debug = false;
        if (function_exists('config')) {
            try {
                $debug = config('app.debug');
            } catch (\Exception $e) {
                $debug = false;
            }
        }

        if ($debug && $original !== $optimized) {
            if (class_exists('Illuminate\Support\Facades\Log')) {
                try {
                    \Illuminate\Support\Facades\Log::debug('Query optimized', [
                        'original' => $original,
                        'optimized' => $optimized,
                        'optimizations_applied' => array_filter($this->optimizationRules),
                    ]);
                } catch (\Exception $e) {
                    // Ignore logging errors in test environment
                }
            }
        }
    }

    /**
     * Analyze query performance characteristics.
     */
    public function analyzeQuery(array $query): array
    {
        $analysis = [
            'complexity' => 'low',
            'estimated_performance' => 'good',
            'recommendations' => [],
            'metrics' => [],
        ];

        // Analyze query complexity
        $complexity = $this->calculateQueryComplexity($query);
        $analysis['complexity'] = $complexity;
        $analysis['metrics']['complexity_score'] = $complexity;

        // Performance recommendations
        $analysis['recommendations'] = $this->generateRecommendations($query);

        // Estimate performance
        $analysis['estimated_performance'] = $this->estimatePerformance($query);

        return $analysis;
    }

    /**
     * Calculate query complexity score.
     */
    protected function calculateQueryComplexity(array $query): string
    {
        $score = 0;

        // Handle both new API structure and legacy structure
        if (isset($query['query'])) {
            $queryText = null;

            if (is_array($query['query']) && isset($query['query']['match']['value'])) {
                // New API structure: {"query": {"match": {"value": "search term"}}}
                $queryText = $query['query']['match']['value'];
            } elseif (is_string($query['query'])) {
                // Legacy structure: {"query": "search term"}
                $queryText = $query['query'];
            }

            if ($queryText && is_string($queryText)) {
                $wordCount = str_word_count($queryText);
                $score += $wordCount * 1;

                if (str_contains($queryText, '*')) $score += 2;
                if (str_contains($queryText, '"')) $score += 1;
            }
        }

        // Filter complexity
        if (isset($query['filter'])) {
            $score += count($query['filter']) * 2;
        }

        // Sorting complexity
        if (isset($query['sort'])) {
            $score += count($query['sort']) * 1;
        }

        if ($score <= 5) return 'low';
        if ($score <= $this->config['max_complexity_score']) return 'medium';
        return 'high';
    }

    /**
     * Generate performance recommendations.
     */
    protected function generateRecommendations(array $query): array
    {
        $recommendations = [];

        // Handle both new API structure and legacy structure
        if (isset($query['query'])) {
            $queryText = null;

            if (is_array($query['query']) && isset($query['query']['match']['value'])) {
                // New API structure: {"query": {"match": {"value": "search term"}}}
                $queryText = $query['query']['match']['value'];
            } elseif (is_string($query['query'])) {
                // Legacy structure: {"query": "search term"}
                $queryText = $query['query'];
            }

            if ($queryText && is_string($queryText)) {
                if (strlen($queryText) > $this->config['performance_check_threshold']) {
                    $recommendations[] = 'Consider shortening the query for better performance';
                }

                if (substr_count($queryText, '*') > 3) {
                    $recommendations[] = 'Too many wildcards may impact performance';
                }
            }
        }

        if (isset($query['filter']) && count($query['filter']) > 10) {
            $recommendations[] = 'Consider reducing the number of filters';
        }

        return $recommendations;
    }

    /**
     * Estimate query performance.
     */
    protected function estimatePerformance(array $query): string
    {
        $complexity = $this->calculateQueryComplexity($query);

        switch ($complexity) {
            case 'low':
                return 'excellent';
            case 'medium':
                return 'good';
            case 'high':
                return 'moderate';
            default:
                return 'unknown';
        }
    }

    /**
     * Get optimization statistics.
     */
    public function getStatistics(): array
    {
        return [
            'optimization_rules' => $this->optimizationRules,
            'config' => $this->config,
        ];
    }

    /**
     * Update optimizer configuration.
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->initializeOptimizationRules();
    }
}
