<?php

namespace OginiScoutDriver\Tests\Unit\Performance;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Performance\QueryCache;
use OginiScoutDriver\Client\OginiClient;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery;

class QueryCacheTest extends TestCase
{
    protected QueryCache $queryCache;
    protected $mockCache;
    protected $mockClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCache = Mockery::mock(CacheRepository::class);
        $this->mockClient = Mockery::mock(OginiClient::class);

        $this->queryCache = new QueryCache($this->mockCache, $this->mockClient, [
            'enabled' => true,
            'query_ttl' => 300,
            'suggestion_ttl' => 1800,
            'facet_ttl' => 600,
            'cache_prefix' => 'test_ogini',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_caches_search_results(): void
    {
        $indexName = 'test_index';
        $searchQuery = ['query' => ['match' => ['value' => 'test']]];
        $options = ['size' => 10];
        $expectedResults = ['data' => ['hits' => []]];

        $this->mockCache->shouldReceive('remember')
            ->once()
            ->with(
                Mockery::type('string'),
                300,
                Mockery::type('callable')
            )
            ->andReturnUsing(function ($key, $ttl, $callback) use ($expectedResults) {
                $results = $callback();
                $this->assertArrayHasKey('_cache', $results);
                return $results;
            });

        $searchCallback = function () use ($expectedResults) {
            return $expectedResults;
        };

        $result = $this->queryCache->remember($indexName, $searchQuery, $options, $searchCallback);

        $this->assertArrayHasKey('_cache', $result);
        $this->assertArrayHasKey('cached_at', $result['_cache']);
        $this->assertArrayHasKey('cache_key', $result['_cache']);
        $this->assertFalse($result['_cache']['from_cache']);
    }

    /** @test */
    public function it_bypasses_cache_when_disabled(): void
    {
        $this->queryCache->setEnabled(false);

        $indexName = 'test_index';
        $searchQuery = ['query' => ['match' => ['value' => 'test']]];
        $options = ['size' => 10];
        $expectedResults = ['data' => ['hits' => []]];

        $this->mockCache->shouldNotReceive('remember');

        $searchCallback = function () use ($expectedResults) {
            return $expectedResults;
        };

        $result = $this->queryCache->remember($indexName, $searchQuery, $options, $searchCallback);

        $this->assertEquals($expectedResults, $result);
    }

    /** @test */
    public function it_caches_suggestions(): void
    {
        $indexName = 'test_index';
        $text = 'test';
        $field = 'title';
        $size = 5;
        $expectedSuggestions = ['suggestions' => ['test1', 'test2']];

        $this->mockCache->shouldReceive('remember')
            ->once()
            ->with(
                Mockery::type('string'),
                1800, // suggestion_ttl
                Mockery::type('callable')
            )
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $suggestionCallback = function () use ($expectedSuggestions) {
            return $expectedSuggestions;
        };

        $result = $this->queryCache->rememberSuggestions($indexName, $text, $field, $size, $suggestionCallback);

        $this->assertEquals($expectedSuggestions, $result);
    }

    /** @test */
    public function it_caches_faceted_search_results(): void
    {
        $indexName = 'test_index';
        $searchQuery = ['query' => ['match' => ['value' => 'test']]];
        $facets = ['category' => ['type' => 'terms', 'field' => 'category']];
        $expectedResults = ['data' => ['hits' => []], 'facets' => []];

        $this->mockCache->shouldReceive('remember')
            ->once()
            ->with(
                Mockery::type('string'),
                600, // facet_ttl
                Mockery::type('callable')
            )
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $facetCallback = function () use ($expectedResults) {
            return $expectedResults;
        };

        $result = $this->queryCache->rememberFacets($indexName, $searchQuery, $facets, $facetCallback);

        $this->assertEquals($expectedResults, $result);
    }

    /** @test */
    public function it_generates_consistent_cache_keys(): void
    {
        $reflection = new \ReflectionClass($this->queryCache);
        $method = $reflection->getMethod('generateCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($this->queryCache, 'query', 'test_index', ['query' => 'test'], ['size' => 10]);
        $key2 = $method->invoke($this->queryCache, 'query', 'test_index', ['query' => 'test'], ['size' => 10]);
        $key3 = $method->invoke($this->queryCache, 'query', 'test_index', ['query' => 'different'], ['size' => 10]);

        $this->assertEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
        $this->assertStringStartsWith('test_ogini:query:', $key1);
    }

    /** @test */
    public function it_optimizes_key_data_for_better_cache_hits(): void
    {
        $reflection = new \ReflectionClass($this->queryCache);
        $method = $reflection->getMethod('optimizeKeyData');
        $method->setAccessible(true);

        $keyData = [
            'type' => 'query',
            'index' => 'test_index',
            'params' => [
                ['query' => 'test', 'timestamp' => '2023-01-01'],
                ['size' => 10, 'debug' => true],
            ],
        ];

        $optimized = $method->invoke($this->queryCache, $keyData);

        // Should remove ignored parameters
        $this->assertArrayNotHasKey('timestamp', $optimized['params'][0]);
        $this->assertArrayNotHasKey('debug', $optimized['params'][1]);

        // Should preserve important parameters
        $this->assertArrayHasKey('query', $optimized['params'][0]);
        $this->assertArrayHasKey('size', $optimized['params'][1]);
    }

    /** @test */
    public function it_determines_appropriate_ttl_for_queries(): void
    {
        $reflection = new \ReflectionClass($this->queryCache);
        $method = $reflection->getMethod('getTtlForQuery');
        $method->setAccessible(true);

        // Filtered query should have shorter TTL
        $filteredQuery = ['query' => 'test', 'filter' => ['term' => ['status' => 'published']]];
        $filteredTtl = $method->invoke($this->queryCache, $filteredQuery);
        $this->assertEquals(150, $filteredTtl); // 50% of query_ttl

        // Simple text query should have normal TTL
        $simpleQuery = ['query' => 'test'];
        $simpleTtl = $method->invoke($this->queryCache, $simpleQuery);
        $this->assertEquals(300, $simpleTtl); // query_ttl

        // Complex query should have default TTL
        $complexQuery = ['complex' => true];
        $complexTtl = $method->invoke($this->queryCache, $complexQuery);
        $this->assertEquals(300, $complexTtl); // default_ttl
    }

    /** @test */
    public function it_can_flush_cache(): void
    {
        // Create a simple object with flush method for testing
        $mockStore = new class {
            public function flush()
            {
                return true;
            }
        };

        $this->mockCache->shouldReceive('getStore')
            ->once()
            ->andReturn($mockStore);

        $result = $this->queryCache->flush();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_handles_flush_failures_gracefully(): void
    {
        $this->mockCache->shouldReceive('getStore')
            ->once()
            ->andThrow(new \Exception('Flush failed'));

        $result = $this->queryCache->flush();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_provides_statistics(): void
    {
        $stats = $this->queryCache->getStatistics();

        $this->assertArrayHasKey('enabled', $stats);
        $this->assertArrayHasKey('query_ttl', $stats);
        $this->assertArrayHasKey('suggestion_ttl', $stats);
        $this->assertArrayHasKey('facet_ttl', $stats);
        $this->assertArrayHasKey('cache_prefix', $stats);
        $this->assertArrayHasKey('optimization_enabled', $stats);
        $this->assertArrayHasKey('compression_enabled', $stats);

        $this->assertTrue($stats['enabled']);
        $this->assertEquals(300, $stats['query_ttl']);
        $this->assertEquals(1800, $stats['suggestion_ttl']);
        $this->assertEquals(600, $stats['facet_ttl']);
        $this->assertEquals('test_ogini', $stats['cache_prefix']);
    }

    /** @test */
    public function it_can_enable_and_disable_cache(): void
    {
        $this->assertTrue($this->queryCache->isEnabled());

        $this->queryCache->setEnabled(false);
        $this->assertFalse($this->queryCache->isEnabled());

        $this->queryCache->setEnabled(true);
        $this->assertTrue($this->queryCache->isEnabled());
    }

    /** @test */
    public function it_can_update_configuration(): void
    {
        $this->queryCache->updateConfig(['query_ttl' => 600]);

        $stats = $this->queryCache->getStatistics();
        $this->assertEquals(600, $stats['query_ttl']);
    }

    /** @test */
    public function it_sorts_arrays_recursively_for_consistent_keys(): void
    {
        $reflection = new \ReflectionClass($this->queryCache);
        $method = $reflection->getMethod('sortRecursive');
        $method->setAccessible(true);

        $data = [
            'z' => 'last',
            'a' => 'first',
            'nested' => [
                'y' => 'nested_last',
                'b' => 'nested_first',
            ],
        ];

        $sorted = $method->invoke($this->queryCache, $data);

        $keys = array_keys($sorted);
        $this->assertEquals(['a', 'nested', 'z'], $keys);

        $nestedKeys = array_keys($sorted['nested']);
        $this->assertEquals(['b', 'y'], $nestedKeys);
    }
}
