<?php

namespace OginiScoutDriver\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Helpers\SearchHelpers;
use OginiScoutDriver\Facades\Ogini;
use OginiScoutDriver\Facades\AsyncOgini;
use Mockery;

class SearchHelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_can_search_across_multiple_indices(): void
    {
        Ogini::shouldReceive('search')
            ->twice()
            ->andReturn(['hits' => ['doc1']], ['hits' => ['doc2']]);

        $result = SearchHelpers::searchAcrossIndices(['index1', 'index2'], 'test query');

        $this->assertArrayHasKey('index1', $result);
        $this->assertArrayHasKey('index2', $result);
        $this->assertEquals(['hits' => ['doc1']], $result['index1']);
        $this->assertEquals(['hits' => ['doc2']], $result['index2']);
    }

    /** @test */
    public function it_handles_search_errors_gracefully(): void
    {
        Ogini::shouldReceive('search')
            ->once()
            ->andThrow(new \Exception('Search failed'));

        $result = SearchHelpers::searchAcrossIndices(['index1'], 'test query');

        $this->assertArrayHasKey('error', $result['index1']);
        $this->assertEquals('Search failed', $result['index1']['error']);
    }

    /** @test */
    public function it_can_perform_async_search_across_indices(): void
    {
        AsyncOgini::shouldReceive('executeParallel')
            ->once()
            ->with(Mockery::type('array'), null)
            ->andReturn(['result1', 'result2']);

        $result = SearchHelpers::searchAcrossIndicesAsync(['index1', 'index2'], 'test query');

        $this->assertEquals(['result1', 'result2'], $result);
    }

    /** @test */
    public function it_can_get_suggestions_from_multiple_indices(): void
    {
        Ogini::shouldReceive('getQuerySuggestions')
            ->twice()
            ->andReturn(
                ['suggestions' => [['text' => 'suggestion1', 'score' => 0.9]]],
                ['suggestions' => [['text' => 'suggestion2', 'score' => 0.8]]]
            );

        $result = SearchHelpers::getSuggestionsFromIndices(['index1', 'index2'], 'test');

        $this->assertCount(2, $result);
        $this->assertEquals('suggestion1', $result[0]['text']);
        $this->assertEquals('suggestion2', $result[1]['text']);
    }

    /** @test */
    public function it_can_perform_fuzzy_search(): void
    {
        Ogini::shouldReceive('search')
            ->once()
            ->with(
                'test_index',
                Mockery::on(function ($query) {
                    return isset($query['query']['multi_match']['fuzziness']);
                }),
                10,
                0
            )
            ->andReturn(['hits' => []]);

        $result = SearchHelpers::fuzzySearch('test_index', 'test query');

        $this->assertEquals(['hits' => []], $result);
    }

    /** @test */
    public function it_can_perform_phrase_search(): void
    {
        Ogini::shouldReceive('search')
            ->once()
            ->with(
                'test_index',
                Mockery::on(function ($query) {
                    return $query['query']['multi_match']['type'] === 'phrase';
                }),
                10,
                0
            )
            ->andReturn(['hits' => []]);

        $result = SearchHelpers::phraseSearch('test_index', 'test phrase');

        $this->assertEquals(['hits' => []], $result);
    }

    /** @test */
    public function it_can_search_with_date_range(): void
    {
        Ogini::shouldReceive('search')
            ->once()
            ->with(
                'test_index',
                Mockery::on(function ($query) {
                    return isset($query['query']['bool']['filter']['range']);
                }),
                10,
                0
            )
            ->andReturn(['hits' => []]);

        $result = SearchHelpers::searchWithDateRange(
            'test_index',
            'test query',
            'created_at',
            ['gte' => '2023-01-01', 'lte' => '2023-12-31']
        );

        $this->assertEquals(['hits' => []], $result);
    }

    /** @test */
    public function it_can_get_trending_searches(): void
    {
        Ogini::shouldReceive('getQuerySuggestions')
            ->once()
            ->with('test_index', '', ['size' => 10, 'field' => null])
            ->andReturn(['suggestions' => []]);

        $result = SearchHelpers::getTrendingSearches('test_index');

        $this->assertEquals(['suggestions' => []], $result);
    }

    /** @test */
    public function it_can_build_complex_query(): void
    {
        $conditions = [
            'must' => [['term' => ['status' => 'active']]],
            'should' => [['match' => ['title' => 'test']]],
            'must_not' => [['term' => ['deleted' => true]]],
            'filter' => [['range' => ['price' => ['gte' => 10]]]]
        ];

        $result = SearchHelpers::buildComplexQuery($conditions);

        $this->assertArrayHasKey('query', $result);
        $this->assertArrayHasKey('bool', $result['query']);
        $this->assertEquals($conditions['must'], $result['query']['bool']['must']);
        $this->assertEquals($conditions['should'], $result['query']['bool']['should']);
        $this->assertEquals($conditions['must_not'], $result['query']['bool']['must_not']);
        $this->assertEquals($conditions['filter'], $result['query']['bool']['filter']);
    }

    /** @test */
    public function it_can_perform_more_like_this_search(): void
    {
        Ogini::shouldReceive('search')
            ->once()
            ->with(
                'test_index',
                Mockery::on(function ($query) {
                    return isset($query['query']['more_like_this']);
                }),
                10,
                0
            )
            ->andReturn(['hits' => []]);

        $result = SearchHelpers::moreLikeThis('test_index', 'doc123');

        $this->assertEquals(['hits' => []], $result);
    }

    /** @test */
    public function it_merges_suggestions_correctly(): void
    {
        $suggestions = [
            'index1' => [
                ['text' => 'duplicate', 'score' => 0.8],
                ['text' => 'unique1', 'score' => 0.7]
            ],
            'index2' => [
                ['text' => 'duplicate', 'score' => 0.9],
                ['text' => 'unique2', 'score' => 0.6]
            ]
        ];

        $method = new \ReflectionMethod(SearchHelpers::class, 'mergeSuggestions');
        $method->setAccessible(true);
        $result = $method->invoke(null, $suggestions, 5);

        $this->assertCount(3, $result);
        $this->assertEquals('duplicate', $result[0]['text']);
        $this->assertEquals(0.9, $result[0]['score']); // Should use higher score
        $this->assertCount(2, $result[0]['sources']);
    }
}
