<?php

namespace OginiScoutDriver\Helpers;

use OginiScoutDriver\Facades\Ogini;
use OginiScoutDriver\Facades\AsyncOgini;
use Illuminate\Support\Collection;

class SearchHelpers
{
    /**
     * Perform a simple search across multiple indices.
     *
     * @param array $indices
     * @param string $query
     * @param array $options
     * @return array
     */
    public static function searchAcrossIndices(array $indices, string $query, array $options = []): array
    {
        $results = [];

        foreach ($indices as $index) {
            try {
                $result = Ogini::search(
                    $index,
                    ['query' => ['match' => ['_all' => $query]]],
                    $options['size'] ?? 10,
                    $options['from'] ?? 0
                );
                $results[$index] = $result;
            } catch (\Exception $e) {
                $results[$index] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Perform an asynchronous search across multiple indices.
     *
     * @param array $indices
     * @param string $query
     * @param array $options
     * @return array
     */
    public static function searchAcrossIndicesAsync(array $indices, string $query, array $options = []): array
    {
        $requests = [];

        foreach ($indices as $index) {
            $requests[] = [
                'method' => 'POST',
                'endpoint' => "/api/indices/{$index}/search",
                'data' => [
                    'query' => ['match' => ['_all' => $query]],
                    'size' => $options['size'] ?? 10,
                    'from' => $options['from'] ?? 0,
                ]
            ];
        }

        return AsyncOgini::executeParallel($requests, $options['progressCallback'] ?? null);
    }

    /**
     * Get suggestions from multiple indices.
     *
     * @param array $indices
     * @param string $text
     * @param array $options
     * @return array
     */
    public static function getSuggestionsFromIndices(array $indices, string $text, array $options = []): array
    {
        $allSuggestions = [];

        foreach ($indices as $index) {
            try {
                $suggestions = Ogini::getQuerySuggestions($index, $text, $options);
                $allSuggestions[$index] = $suggestions['suggestions'] ?? [];
            } catch (\Exception $e) {
                $allSuggestions[$index] = [];
            }
        }

        return self::mergeSuggestions($allSuggestions, $options['maxSuggestions'] ?? 10);
    }

    /**
     * Perform a fuzzy search with automatic query optimization.
     *
     * @param string $index
     * @param string $query
     * @param array $options
     * @return array
     */
    public static function fuzzySearch(string $index, string $query, array $options = []): array
    {
        $searchQuery = [
            'query' => [
                'multi_match' => [
                    'query' => $query,
                    'type' => 'best_fields',
                    'fuzziness' => $options['fuzziness'] ?? 'AUTO',
                    'operator' => $options['operator'] ?? 'or',
                    'minimum_should_match' => $options['minimum_should_match'] ?? '75%',
                ]
            ]
        ];

        if (isset($options['fields'])) {
            $searchQuery['query']['multi_match']['fields'] = $options['fields'];
        }

        if (isset($options['boost'])) {
            foreach ($options['boost'] as $field => $boost) {
                $searchQuery['query']['multi_match']['fields'][] = "{$field}^{$boost}";
            }
        }

        return Ogini::search($index, $searchQuery, $options['size'] ?? 10, $options['from'] ?? 0);
    }

    /**
     * Perform a phrase search with proximity matching.
     *
     * @param string $index
     * @param string $phrase
     * @param array $options
     * @return array
     */
    public static function phraseSearch(string $index, string $phrase, array $options = []): array
    {
        $searchQuery = [
            'query' => [
                'multi_match' => [
                    'query' => $phrase,
                    'type' => 'phrase',
                    'slop' => $options['slop'] ?? 2,
                ]
            ]
        ];

        if (isset($options['fields'])) {
            $searchQuery['query']['multi_match']['fields'] = $options['fields'];
        }

        return Ogini::search($index, $searchQuery, $options['size'] ?? 10, $options['from'] ?? 0);
    }

    /**
     * Search with date range filtering.
     *
     * @param string $index
     * @param string $query
     * @param string $dateField
     * @param array $dateRange
     * @param array $options
     * @return array
     */
    public static function searchWithDateRange(string $index, string $query, string $dateField, array $dateRange, array $options = []): array
    {
        $searchQuery = [
            'query' => [
                'bool' => [
                    'must' => [
                        'match' => ['_all' => $query]
                    ],
                    'filter' => [
                        'range' => [
                            $dateField => $dateRange
                        ]
                    ]
                ]
            ]
        ];

        return Ogini::search($index, $searchQuery, $options['size'] ?? 10, $options['from'] ?? 0);
    }

    /**
     * Get trending searches based on search frequency.
     *
     * @param string $index
     * @param array $options
     * @return array
     */
    public static function getTrendingSearches(string $index, array $options = []): array
    {
        // This would typically be based on search analytics
        // For now, we'll return suggestions as a proxy
        return Ogini::getQuerySuggestions($index, '', [
            'size' => $options['size'] ?? 10,
            'field' => $options['field'] ?? null,
        ]);
    }

    /**
     * Merge suggestions from multiple sources and sort by relevance.
     *
     * @param array $suggestions
     * @param int $maxResults
     * @return array
     */
    protected static function mergeSuggestions(array $suggestions, int $maxResults = 10): array
    {
        $merged = [];

        foreach ($suggestions as $index => $indexSuggestions) {
            foreach ($indexSuggestions as $suggestion) {
                $text = $suggestion['text'] ?? $suggestion;
                $score = $suggestion['score'] ?? 1.0;

                if (isset($merged[$text])) {
                    $merged[$text]['score'] = max($merged[$text]['score'], $score);
                    $merged[$text]['sources'][] = $index;
                } else {
                    $merged[$text] = [
                        'text' => $text,
                        'score' => $score,
                        'sources' => [$index],
                    ];
                }
            }
        }

        // Sort by score descending
        usort($merged, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($merged, 0, $maxResults);
    }

    /**
     * Build a complex search query with multiple conditions.
     *
     * @param array $conditions
     * @return array
     */
    public static function buildComplexQuery(array $conditions): array
    {
        $query = ['bool' => []];

        foreach ($conditions as $type => $clauses) {
            if (in_array($type, ['must', 'should', 'must_not', 'filter'])) {
                $query['bool'][$type] = $clauses;
            }
        }

        return ['query' => $query];
    }

    /**
     * Perform a more-like-this search.
     *
     * @param string $index
     * @param string $documentId
     * @param array $options
     * @return array
     */
    public static function moreLikeThis(string $index, string $documentId, array $options = []): array
    {
        $searchQuery = [
            'query' => [
                'more_like_this' => [
                    'like' => [
                        '_index' => $index,
                        '_id' => $documentId,
                    ],
                    'min_term_freq' => $options['min_term_freq'] ?? 2,
                    'max_query_terms' => $options['max_query_terms'] ?? 25,
                    'min_doc_freq' => $options['min_doc_freq'] ?? 5,
                ]
            ]
        ];

        if (isset($options['fields'])) {
            $searchQuery['query']['more_like_this']['fields'] = $options['fields'];
        }

        return Ogini::search($index, $searchQuery, $options['size'] ?? 10, $options['from'] ?? 0);
    }
}
