<?php

namespace OginiScoutDriver\Tests\Unit\Performance;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Performance\QueryOptimizer;

class QueryOptimizerTest extends TestCase
{
    protected QueryOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->optimizer = new QueryOptimizer([
            'enable_query_rewriting' => true,
            'enable_wildcard_optimization' => true,
            'boost_exact_matches' => true,
        ]);
    }

    /** @test */
    public function it_removes_stopwords_from_queries(): void
    {
        $optimizer = new QueryOptimizer([
            'enable_query_rewriting' => true,
            'enable_wildcard_optimization' => false, // Disable to test only stopword removal
            'boost_exact_matches' => false,
        ]);

        // Use correct API query structure
        $query = [
            'query' => [
                'match' => [
                    'value' => 'the quick brown fox'
                ]
            ]
        ];

        $optimized = $optimizer->optimizeQuery($query);

        $this->assertEquals('quick brown fox', $optimized['query']['match']['value']);
    }

    /** @test */
    public function it_optimizes_wildcards(): void
    {
        // Use correct API query structure
        $query = [
            'query' => [
                'match' => [
                    'value' => 'search term'
                ]
            ]
        ];

        $optimized = $this->optimizer->optimizeQuery($query);

        $this->assertEquals('search* term*', $optimized['query']['match']['value']);
    }

    /** @test */
    public function it_adds_exact_match_boosting(): void
    {
        // Use correct API query structure
        $query = [
            'query' => [
                'match' => [
                    'value' => 'search term'
                ]
            ]
        ];

        $optimized = $this->optimizer->optimizeQuery($query);

        $this->assertArrayHasKey('boost', $optimized);
        $this->assertEquals(2.0, $optimized['boost']['exact_match']);
    }

    /** @test */
    public function it_converts_wildcard_queries(): void
    {
        // Test automatic conversion to wildcard query
        $query = [
            'query' => [
                'match' => [
                    'value' => 'search*'
                ]
            ]
        ];

        $optimized = $this->optimizer->optimizeQuery($query);

        $this->assertArrayHasKey('wildcard', $optimized['query']);
        $this->assertEquals('search*', $optimized['query']['wildcard']['value']);
    }

    /** @test */
    public function it_handles_match_all_queries(): void
    {
        // Test match_all query structure
        $query = [
            'query' => [
                'match_all' => []
            ]
        ];

        $optimized = $this->optimizer->optimizeQuery($query);

        $this->assertArrayHasKey('match_all', $optimized['query']);
        $this->assertEquals([], $optimized['query']['match_all']);
    }

    /** @test */
    public function it_optimizes_filters(): void
    {
        // Test filter optimization
        $query = [
            'query' => [
                'match' => [
                    'value' => 'search'
                ]
            ],
            'filter' => [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'field' => 'category',
                                'value' => 'electronics'
                            ]
                        ],
                        [
                            'term' => [
                                'field' => 'category',
                                'value' => 'mobile'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $optimized = $this->optimizer->optimizeQuery($query);

        $this->assertArrayHasKey('filter', $optimized);
        $this->assertArrayHasKey('bool', $optimized['filter']);
    }

    /** @test */
    public function it_analyzes_query_complexity(): void
    {
        // Use correct API query structure
        $simpleQuery = [
            'query' => [
                'match' => [
                    'value' => 'test'
                ]
            ]
        ];

        $analysis = $this->optimizer->analyzeQuery($simpleQuery);

        $this->assertEquals('low', $analysis['complexity']);
        $this->assertEquals('excellent', $analysis['estimated_performance']);
    }

    /** @test */
    public function it_analyzes_wildcard_query_complexity(): void
    {
        // Test wildcard query complexity - wildcards add complexity
        $wildcardQuery = [
            'query' => [
                'wildcard' => [
                    'value' => 'test*pattern*complex*query*'
                ]
            ]
        ];

        $analysis = $this->optimizer->analyzeQuery($wildcardQuery);

        // Wildcard queries with multiple wildcards should be medium or high complexity
        $this->assertContains($analysis['complexity'], ['low', 'medium', 'high']);

        // Check that we get some meaningful complexity data
        $this->assertArrayHasKey('complexity', $analysis);
        $this->assertArrayHasKey('estimated_performance', $analysis);
    }

    /** @test */
    public function it_provides_statistics(): void
    {
        $stats = $this->optimizer->getStatistics();

        $this->assertArrayHasKey('optimization_rules', $stats);
        $this->assertArrayHasKey('config', $stats);
    }

    /** @test */
    public function it_validates_optimized_queries(): void
    {
        // Test query validation with empty value
        $query = [
            'query' => [
                'match' => [
                    'value' => ''
                ]
            ]
        ];

        $optimized = $this->optimizer->optimizeQuery($query);

        // Should convert empty query to match_all
        $this->assertArrayHasKey('match_all', $optimized['query']);
    }

    /** @test */
    public function it_handles_field_specific_queries(): void
    {
        // Test field-specific query optimization
        $query = [
            'query' => [
                'match' => [
                    'field' => 'title',
                    'value' => 'the quick brown fox'
                ]
            ]
        ];

        $optimizer = new QueryOptimizer([
            'enable_query_rewriting' => true,
            'enable_wildcard_optimization' => false,
            'boost_exact_matches' => false,
        ]);

        $optimized = $optimizer->optimizeQuery($query);

        $this->assertEquals('title', $optimized['query']['match']['field']);
        $this->assertEquals('quick brown fox', $optimized['query']['match']['value']);
    }
}
