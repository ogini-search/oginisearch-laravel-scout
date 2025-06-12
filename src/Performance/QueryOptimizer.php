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

        if (!isset($query['query']) || !is_array($query['query'])) {
            return $query;
        }

        // Handle match query structure: {"query": {"match": {"value": "search term"}}}
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

        // Handle match query with field: {"query": {"match": {"field": "title", "value": "search term"}}}
        if (isset($query['query']['match']['field']) && isset($query['query']['match']['value']) && is_string($query['query']['match']['value'])) {
            $searchTerm = $query['query']['match']['value'];
            $words = explode(' ', strtolower($searchTerm));
            $filteredWords = array_filter($words, function ($word) use ($stopwords) {
                return !in_array(trim($word), $stopwords) && strlen(trim($word)) >= $this->config['min_term_length'];
            });

            if (count($filteredWords) > 0) {
                $query['query']['match']['value'] = implode(' ', $filteredWords);
            }
        }

        return $query;
    }

    /**
     * Optimize wildcard usage in queries.
     */
    protected function optimizeWildcards(array $query): array
    {
        if (!isset($query['query']) || !is_array($query['query'])) {
            return $query;
        }

        // Handle match query structure
        if (isset($query['query']['match']['value']) && is_string($query['query']['match']['value'])) {
            $queryText = $query['query']['match']['value'];

            // Check if this should be converted to a wildcard query
            if (str_contains($queryText, '*') || str_contains($queryText, '?')) {
                // Convert to wildcard query structure
                $field = $query['query']['match']['field'] ?? null;

                // Remove excessive wildcards
                $queryText = preg_replace('/\*{2,}/', '*', $queryText);

                $query['query'] = [
                    'wildcard' => [
                        'field' => $field,
                        'value' => $queryText
                    ]
                ];

                // Remove the field if it was set at the top level
                if (!$field) {
                    unset($query['query']['wildcard']['field']);
                }
            } else {
                // Add wildcard to end of words for better matching (if not already present)
                if (!str_contains($queryText, '"')) {
                    $words = explode(' ', $queryText);
                    $words = array_map(function ($word) {
                        if (strlen(trim($word)) >= $this->config['min_term_length']) {
                            return trim($word) . '*';
                        }
                        return $word;
                    }, $words);
                    $queryText = implode(' ', $words);
                    $query['query']['match']['value'] = $queryText;
                }
            }
        }

        return $query;
    }

    /**
     * Detect phrases and optimize them for better matching.
     */
    protected function detectAndOptimizePhrases(array $query): array
    {
        if (!isset($query['query']) || !is_array($query['query'])) {
            return $query;
        }

        // Handle match query structure
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

        return $query;
    }

    /**
     * Add exact match boosting.
     */
    protected function addExactMatchBoost(array $query): array
    {
        if (!isset($query['query']) || !is_array($query['query'])) {
            return $query;
        }

        // Add boost configuration for different query types
        $query['boost'] = [
            'exact_match' => $this->config['exact_match_boost'],
            'phrase_match' => $this->config['phrase_boost'],
            'fuzzy_match' => $this->config['fuzzy_match_boost'],
        ];

        return $query;
    }

    /**
     * Optimize filter conditions for better performance.
     */
    protected function optimizeFilters(array $query): array
    {
        if (!isset($query['filter']) || !is_array($query['filter'])) {
            return $query;
        }

        // Handle bool filter structure: {"filter": {"bool": {"must": [...]}}}
        if (isset($query['filter']['bool']['must']) && is_array($query['filter']['bool']['must'])) {
            $filters = $query['filter']['bool']['must'];

            // Sort filters by selectivity (more selective filters first)
            $optimizedFilters = $this->sortFiltersBySelectivity($filters);

            // Combine similar filters
            $optimizedFilters = $this->combineSimilarFilters($optimizedFilters);

            $query['filter']['bool']['must'] = $optimizedFilters;
        }
        // Handle single filter: {"filter": {"term": {"field": "category", "value": "electronics"}}}
        elseif (isset($query['filter']['term'])) {
            // Single term filter, leave as is - no optimization needed
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
            if (isset($filter['term']['field'], $filter['term']['value'])) {
                $field = $filter['term']['field'];
                if (!isset($termFilters[$field])) {
                    $termFilters[$field] = [];
                }
                $termFilters[$field][] = $filter['term']['value'];
            } else {
                $combined[] = $filter;
            }
        }

        // Convert multiple term filters on same field to terms filter
        foreach ($termFilters as $field => $values) {
            if (count($values) > 1) {
                $combined[] = [
                    'terms' => [
                        'field' => $field,
                        'values' => array_unique($values)
                    ]
                ];
            } else {
                $combined[] = [
                    'term' => [
                        'field' => $field,
                        'value' => $values[0]
                    ]
                ];
            }
        }

        return $combined;
    }

    /**
     * Optimize field weights for better relevance.
     */
    protected function optimizeFieldWeights(array $query): array
    {
        if (isset($query['fields']) && is_array($query['fields'])) {
            // Define default field weights based on importance
            $fieldWeights = [
                'title' => 3.0,
                'name' => 2.5,
                'description' => 1.5,
                'content' => 1.0,
                'tags' => 1.2,
            ];

            $weightedFields = [];
            foreach ($query['fields'] as $field) {
                $weight = $fieldWeights[$field] ?? 1.0;
                $weightedFields[] = $field . '^' . $weight;
            }
            $query['fields'] = $weightedFields;
        }

        return $query;
    }

    /**
     * Validate the optimized query to ensure it's still valid.
     */
    protected function validateOptimizedQuery(array $query): array
    {
        // Handle query structure validation
        if (isset($query['query']) && is_array($query['query'])) {
            // Handle match query validation
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
            // Handle wildcard query validation
            elseif (isset($query['query']['wildcard']['value'])) {
                $wildcardValue = $query['query']['wildcard']['value'];

                if (is_string($wildcardValue) && strlen($wildcardValue) > $this->config['max_query_length']) {
                    $query['query']['wildcard']['value'] = substr($wildcardValue, 0, $this->config['max_query_length']);
                }

                if (is_string($wildcardValue) && empty(trim($wildcardValue))) {
                    $query['query'] = ['match_all' => []];
                }
            }
            // Handle match_all query (no validation needed)
            elseif (isset($query['query']['match_all'])) {
                // match_all is always valid
            }
            // If no recognized query structure, default to match_all
            else {
                $query['query'] = ['match_all' => []];
            }
        } else {
            // No valid query structure provided, default to match_all
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
        // Only log in debug mode and if there were actual changes
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

        // Analyze query structure complexity
        if (isset($query['query']) && is_array($query['query'])) {
            // Handle match query
            if (isset($query['query']['match']['value']) && is_string($query['query']['match']['value'])) {
                $queryText = $query['query']['match']['value'];
                $wordCount = str_word_count($queryText);
                $score += $wordCount * 1;

                if (str_contains($queryText, '*')) $score += 2;
                if (str_contains($queryText, '"')) $score += 1;
            }
            // Handle wildcard query
            elseif (isset($query['query']['wildcard']['value']) && is_string($query['query']['wildcard']['value'])) {
                $wildcardText = $query['query']['wildcard']['value'];
                $score += 3; // Wildcards are more complex

                if (substr_count($wildcardText, '*') > 2) $score += 2;
                if (str_contains($wildcardText, '?')) $score += 1;
            }
            // match_all is simple
            elseif (isset($query['query']['match_all'])) {
                $score += 1;
            }
        }

        // Filter complexity
        if (isset($query['filter']) && is_array($query['filter'])) {
            if (isset($query['filter']['bool']['must'])) {
                $score += count($query['filter']['bool']['must']) * 2;
            } else {
                $score += 2; // Single filter
            }
        }

        // Sorting complexity
        if (isset($query['sort'])) {
            $score += 1;
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

        // Analyze query structure for recommendations
        if (isset($query['query']) && is_array($query['query'])) {
            // Check match query
            if (isset($query['query']['match']['value']) && is_string($query['query']['match']['value'])) {
                $queryText = $query['query']['match']['value'];

                if (strlen($queryText) > $this->config['performance_check_threshold']) {
                    $recommendations[] = 'Consider shortening the query for better performance';
                }

                if (substr_count($queryText, '*') > 3) {
                    $recommendations[] = 'Too many wildcards may impact performance';
                }
            }
            // Check wildcard query
            elseif (isset($query['query']['wildcard']['value']) && is_string($query['query']['wildcard']['value'])) {
                $wildcardText = $query['query']['wildcard']['value'];

                if (substr_count($wildcardText, '*') > 2) {
                    $recommendations[] = 'Multiple wildcards in pattern may slow down searches';
                }

                if (strpos($wildcardText, '*') === 0) {
                    $recommendations[] = 'Leading wildcards are slower - consider using different query approach';
                }
            }
        }

        // Check filter complexity
        if (isset($query['filter']['bool']['must']) && count($query['filter']['bool']['must']) > 10) {
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
