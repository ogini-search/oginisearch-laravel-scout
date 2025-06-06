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

        $query = ['query' => 'the quick brown fox'];

        $optimized = $optimizer->optimizeQuery($query);

        $this->assertEquals('quick brown fox', $optimized['query']);
    }

    /** @test */
    public function it_optimizes_wildcards(): void
    {
        $query = ['query' => 'search term'];

        $optimized = $this->optimizer->optimizeQuery($query);

        $this->assertEquals('search* term*', $optimized['query']);
    }

    /** @test */
    public function it_adds_exact_match_boosting(): void
    {
        $query = ['query' => 'search term'];

        $optimized = $this->optimizer->optimizeQuery($query);

        $this->assertArrayHasKey('boost', $optimized);
        $this->assertEquals(2.0, $optimized['boost']['exact_match']);
    }

    /** @test */
    public function it_analyzes_query_complexity(): void
    {
        $simpleQuery = ['query' => 'test'];
        $analysis = $this->optimizer->analyzeQuery($simpleQuery);

        $this->assertEquals('low', $analysis['complexity']);
        $this->assertEquals('excellent', $analysis['estimated_performance']);
    }

    /** @test */
    public function it_provides_statistics(): void
    {
        $stats = $this->optimizer->getStatistics();

        $this->assertArrayHasKey('optimization_rules', $stats);
        $this->assertArrayHasKey('config', $stats);
    }
}
