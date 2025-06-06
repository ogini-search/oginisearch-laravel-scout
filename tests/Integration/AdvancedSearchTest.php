<?php

namespace OginiScoutDriver\Tests\Integration;

use OginiScoutDriver\Tests\Integration\Models\TestProduct;
use OginiScoutDriver\Tests\Integration\Factories\TestDataFactory;
use OginiScoutDriver\Search\Facets\FacetDefinition;
use OginiScoutDriver\Search\Facets\FacetCollection;
use OginiScoutDriver\Search\Filters\FilterBuilder;
use OginiScoutDriver\Search\Sorting\SortBuilder;
use OginiScoutDriver\Search\Highlighting\HighlightBuilder;
use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Client\OginiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;

/**
 * @group integration-tests
 */
class AdvancedSearchTest extends TestCase
{
    private OginiClient $client;
    private MockHandler $mockHandler;
    private string $testIndex = 'advanced_search_test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $handlerStack = HandlerStack::create($this->mockHandler);
        $httpClient = new Client(['handler' => $handlerStack]);

        $this->client = new OginiClient('http://localhost:3000', 'test-key');
        $this->client->setHttpClient($httpClient);
    }

    /** @test */
    public function it_can_perform_faceted_search(): void
    {
        $expectedResponse = [
            'hits' => [
                ['_id' => 'doc1', '_source' => ['title' => 'Product A', 'category' => 'Electronics', 'price' => 299.99]],
                ['_id' => 'doc2', '_source' => ['title' => 'Product B', 'category' => 'Electronics', 'price' => 199.99]],
            ],
            'aggregations' => [
                'categories' => [
                    'buckets' => [
                        ['key' => 'Electronics', 'doc_count' => 15],
                        ['key' => 'Books', 'doc_count' => 8],
                        ['key' => 'Clothing', 'doc_count' => 12],
                    ]
                ],
                'price_ranges' => [
                    'buckets' => [
                        ['key' => '0-100', 'from' => 0, 'to' => 100, 'doc_count' => 5],
                        ['key' => '100-500', 'from' => 100, 'to' => 500, 'doc_count' => 20],
                        ['key' => '500+', 'from' => 500, 'doc_count' => 10],
                    ]
                ]
            ],
            'total' => 35
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $facetedQuery = [
            'query' => [
                'bool' => [
                    'must' => [
                        ['match' => ['title' => 'product']]
                    ],
                    'filter' => [
                        ['term' => ['category' => 'Electronics']]
                    ]
                ]
            ],
            'aggs' => [
                'categories' => [
                    'terms' => ['field' => 'category', 'size' => 10]
                ],
                'price_ranges' => [
                    'range' => [
                        'field' => 'price',
                        'ranges' => [
                            ['key' => '0-100', 'from' => 0, 'to' => 100],
                            ['key' => '100-500', 'from' => 100, 'to' => 500],
                            ['key' => '500+', 'from' => 500],
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->client->search($this->testIndex, $facetedQuery, 20, 0);

        $this->assertEquals(35, $result['total']);
        $this->assertCount(2, $result['hits']);
        $this->assertArrayHasKey('aggregations', $result);
        $this->assertArrayHasKey('categories', $result['aggregations']);
        $this->assertArrayHasKey('price_ranges', $result['aggregations']);
        $this->assertCount(3, $result['aggregations']['categories']['buckets']);
        $this->assertEquals('Electronics', $result['aggregations']['categories']['buckets'][0]['key']);
        $this->assertEquals(15, $result['aggregations']['categories']['buckets'][0]['doc_count']);
    }

    /** @test */
    public function it_can_perform_complex_filtering(): void
    {
        $expectedResponse = [
            'hits' => [
                ['_id' => 'doc1', '_source' => ['title' => 'Premium Laptop', 'price' => 1299.99, 'rating' => 4.5, 'available' => true]],
                ['_id' => 'doc2', '_source' => ['title' => 'Gaming Laptop', 'price' => 1599.99, 'rating' => 4.8, 'available' => true]],
            ],
            'total' => 12
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $complexQuery = [
            'query' => [
                'bool' => [
                    'must' => [
                        ['match' => ['title' => 'laptop']]
                    ],
                    'filter' => [
                        ['range' => ['price' => ['gte' => 1000, 'lte' => 2000]]],
                        ['range' => ['rating' => ['gte' => 4.0]]],
                        ['term' => ['available' => true]]
                    ],
                    'must_not' => [
                        ['term' => ['discontinued' => true]]
                    ],
                    'should' => [
                        ['match' => ['brand' => 'Apple']],
                        ['match' => ['brand' => 'Dell']],
                        ['match' => ['brand' => 'HP']]
                    ],
                    'minimum_should_match' => 1
                ]
            ]
        ];

        $result = $this->client->search($this->testIndex, $complexQuery, 10, 0);

        $this->assertEquals(12, $result['total']);
        $this->assertCount(2, $result['hits']);

        foreach ($result['hits'] as $hit) {
            $source = $hit['_source'];
            $this->assertGreaterThanOrEqual(1000, $source['price']);
            $this->assertLessThanOrEqual(2000, $source['price']);
            $this->assertGreaterThanOrEqual(4.0, $source['rating']);
            $this->assertTrue($source['available']);
        }
    }

    /** @test */
    public function it_can_perform_search_with_highlighting(): void
    {
        $expectedResponse = [
            'hits' => [
                [
                    '_id' => 'doc1',
                    '_source' => ['title' => 'Advanced Search Techniques', 'content' => 'This article covers advanced search methodologies...'],
                    'highlight' => [
                        'title' => ['<em>Advanced</em> <em>Search</em> Techniques'],
                        'content' => ['This article covers <em>advanced</em> <em>search</em> methodologies...']
                    ]
                ],
                [
                    '_id' => 'doc2',
                    '_source' => ['title' => 'Search Engine Optimization', 'content' => 'SEO and search optimization strategies...'],
                    'highlight' => [
                        'title' => ['<em>Search</em> Engine Optimization'],
                        'content' => ['SEO and <em>search</em> optimization strategies...']
                    ]
                ]
            ],
            'total' => 25
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $highlightQuery = [
            'query' => [
                'multi_match' => [
                    'query' => 'advanced search',
                    'fields' => ['title^2', 'content'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO'
                ]
            ],
            'highlight' => [
                'pre_tags' => ['<em>'],
                'post_tags' => ['</em>'],
                'fields' => [
                    'title' => [
                        'fragment_size' => 150,
                        'number_of_fragments' => 1
                    ],
                    'content' => [
                        'fragment_size' => 200,
                        'number_of_fragments' => 2
                    ]
                ]
            ]
        ];

        $result = $this->client->search($this->testIndex, $highlightQuery, 10, 0);

        $this->assertEquals(25, $result['total']);
        $this->assertCount(2, $result['hits']);

        foreach ($result['hits'] as $hit) {
            $this->assertArrayHasKey('highlight', $hit);
            $this->assertArrayHasKey('title', $hit['highlight']);
            $this->assertArrayHasKey('content', $hit['highlight']);

            // Verify highlighting tags are present
            $titleHighlight = implode(' ', $hit['highlight']['title']);
            $contentHighlight = implode(' ', $hit['highlight']['content']);

            $this->assertStringContainsString('<em>', $titleHighlight);
            $this->assertStringContainsString('</em>', $titleHighlight);
            $this->assertStringContainsString('<em>', $contentHighlight);
            $this->assertStringContainsString('</em>', $contentHighlight);
        }
    }

    /** @test */
    public function it_can_perform_search_with_sorting(): void
    {
        $expectedResponse = [
            'hits' => [
                ['_id' => 'doc1', '_source' => ['title' => 'Product A', 'price' => 299.99, 'rating' => 4.8, 'created_at' => '2023-12-01']],
                ['_id' => 'doc2', '_source' => ['title' => 'Product B', 'price' => 199.99, 'rating' => 4.5, 'created_at' => '2023-11-15']],
                ['_id' => 'doc3', '_source' => ['title' => 'Product C', 'price' => 399.99, 'rating' => 4.2, 'created_at' => '2023-10-20']],
            ],
            'total' => 3
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $sortQuery = [
            'query' => [
                'match' => ['category' => 'Electronics']
            ],
            'sort' => [
                ['rating' => ['order' => 'desc']],
                ['price' => ['order' => 'asc']],
                ['created_at' => ['order' => 'desc']],
                '_score'
            ]
        ];

        $result = $this->client->search($this->testIndex, $sortQuery, 10, 0);

        $this->assertEquals(3, $result['total']);
        $this->assertCount(3, $result['hits']);

        // Verify sorting order - should be sorted by rating desc, then price asc
        $ratings = array_map(fn($hit) => $hit['_source']['rating'], $result['hits']);
        $this->assertEquals([4.8, 4.5, 4.2], $ratings);
    }

    /** @test */
    public function it_can_perform_geospatial_search(): void
    {
        $expectedResponse = [
            'hits' => [
                [
                    '_id' => 'loc1',
                    '_source' => [
                        'name' => 'Coffee Shop A',
                        'location' => ['lat' => 40.7128, 'lon' => -74.0060],
                        'distance' => '0.5km'
                    ]
                ],
                [
                    '_id' => 'loc2',
                    '_source' => [
                        'name' => 'Restaurant B',
                        'location' => ['lat' => 40.7580, 'lon' => -73.9855],
                        'distance' => '2.1km'
                    ]
                ]
            ],
            'total' => 8
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $geoQuery = [
            'query' => [
                'bool' => [
                    'must' => [
                        ['match' => ['category' => 'restaurant']]
                    ],
                    'filter' => [
                        'geo_distance' => [
                            'distance' => '5km',
                            'location' => [
                                'lat' => 40.7128,
                                'lon' => -74.0060
                            ]
                        ]
                    ]
                ]
            ],
            'sort' => [
                [
                    '_geo_distance' => [
                        'location' => [
                            'lat' => 40.7128,
                            'lon' => -74.0060
                        ],
                        'order' => 'asc',
                        'unit' => 'km'
                    ]
                ]
            ]
        ];

        $result = $this->client->search($this->testIndex, $geoQuery, 10, 0);

        $this->assertEquals(8, $result['total']);
        $this->assertCount(2, $result['hits']);

        foreach ($result['hits'] as $hit) {
            $this->assertArrayHasKey('location', $hit['_source']);
            $this->assertArrayHasKey('lat', $hit['_source']['location']);
            $this->assertArrayHasKey('lon', $hit['_source']['location']);
        }
    }

    /** @test */
    public function it_can_perform_nested_object_search(): void
    {
        $expectedResponse = [
            'hits' => [
                [
                    '_id' => 'user1',
                    '_source' => [
                        'name' => 'John Doe',
                        'orders' => [
                            ['product' => 'Laptop', 'price' => 1299.99, 'date' => '2023-12-01'],
                            ['product' => 'Mouse', 'price' => 29.99, 'date' => '2023-11-15']
                        ]
                    ]
                ]
            ],
            'total' => 1
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $nestedQuery = [
            'query' => [
                'nested' => [
                    'path' => 'orders',
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['match' => ['orders.product' => 'Laptop']],
                                ['range' => ['orders.price' => ['gte' => 1000]]]
                            ]
                        ]
                    ],
                    'inner_hits' => [
                        'highlight' => [
                            'fields' => [
                                'orders.product' => []
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $result = $this->client->search($this->testIndex, $nestedQuery, 10, 0);

        $this->assertEquals(1, $result['total']);
        $this->assertCount(1, $result['hits']);
        $this->assertArrayHasKey('orders', $result['hits'][0]['_source']);
        $this->assertIsArray($result['hits'][0]['_source']['orders']);
    }

    /** @test */
    public function it_can_perform_multi_field_search_with_boosting(): void
    {
        $expectedResponse = [
            'hits' => [
                [
                    '_id' => 'doc1',
                    '_score' => 2.5,
                    '_source' => ['title' => 'JavaScript Programming', 'author' => 'John Smith', 'content' => 'Learn JavaScript...']
                ],
                [
                    '_id' => 'doc2',
                    '_score' => 1.8,
                    '_source' => ['title' => 'Web Development', 'author' => 'Jane Doe', 'content' => 'JavaScript and HTML...']
                ]
            ],
            'total' => 25
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $boostedQuery = [
            'query' => [
                'multi_match' => [
                    'query' => 'javascript programming',
                    'fields' => [
                        'title^3',      // Boost title by 3x
                        'author^2',     // Boost author by 2x
                        'content^1',    // Normal boost for content
                        'tags^1.5'     // Boost tags by 1.5x
                    ],
                    'type' => 'best_fields',
                    'tie_breaker' => 0.3,
                    'minimum_should_match' => '75%'
                ]
            ]
        ];

        $result = $this->client->search($this->testIndex, $boostedQuery, 10, 0);

        $this->assertEquals(25, $result['total']);
        $this->assertCount(2, $result['hits']);

        // Verify scores are in descending order
        $this->assertGreaterThan($result['hits'][1]['_score'], $result['hits'][0]['_score']);
        $this->assertEquals(2.5, $result['hits'][0]['_score']);
        $this->assertEquals(1.8, $result['hits'][1]['_score']);
    }

    /** @test */
    public function it_can_perform_function_score_search(): void
    {
        $expectedResponse = [
            'hits' => [
                [
                    '_id' => 'doc1',
                    '_score' => 3.2,
                    '_source' => ['title' => 'Popular Article', 'views' => 10000, 'likes' => 500, 'published_date' => '2023-12-01']
                ],
                [
                    '_id' => 'doc2',
                    '_score' => 2.8,
                    '_source' => ['title' => 'Recent Article', 'views' => 5000, 'likes' => 300, 'published_date' => '2023-11-20']
                ]
            ],
            'total' => 15
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $functionScoreQuery = [
            'query' => [
                'function_score' => [
                    'query' => [
                        'match' => ['title' => 'article']
                    ],
                    'functions' => [
                        [
                            'field_value_factor' => [
                                'field' => 'views',
                                'factor' => 0.0001,
                                'modifier' => 'log1p'
                            ]
                        ],
                        [
                            'field_value_factor' => [
                                'field' => 'likes',
                                'factor' => 0.01,
                                'modifier' => 'sqrt'
                            ]
                        ],
                        [
                            'gauss' => [
                                'published_date' => [
                                    'origin' => '2023-12-01',
                                    'scale' => '30d',
                                    'decay' => 0.5
                                ]
                            ]
                        ]
                    ],
                    'score_mode' => 'multiply',
                    'boost_mode' => 'sum'
                ]
            ]
        ];

        $result = $this->client->search($this->testIndex, $functionScoreQuery, 10, 0);

        $this->assertEquals(15, $result['total']);
        $this->assertCount(2, $result['hits']);
        $this->assertGreaterThan(0, $result['hits'][0]['_score']);
        $this->assertGreaterThan($result['hits'][1]['_score'], $result['hits'][0]['_score']);
    }

    /** @test */
    public function it_can_perform_search_with_custom_scoring(): void
    {
        $expectedResponse = [
            'hits' => [
                [
                    '_id' => 'doc1',
                    '_score' => 4.5,
                    '_source' => ['title' => 'Premium Product', 'price' => 299.99, 'popularity_score' => 85]
                ]
            ],
            'total' => 10
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($expectedResponse)));

        $customScoringQuery = [
            'query' => [
                'script_score' => [
                    'query' => [
                        'match' => ['title' => 'product']
                    ],
                    'script' => [
                        'source' => '_score * Math.log(2 + doc[\'popularity_score\'].value) / (1 + doc[\'price\'].value / 100)',
                        'lang' => 'painless'
                    ]
                ]
            ]
        ];

        $result = $this->client->search($this->testIndex, $customScoringQuery, 10, 0);

        $this->assertEquals(10, $result['total']);
        $this->assertCount(1, $result['hits']);
        $this->assertEquals(4.5, $result['hits'][0]['_score']);
    }
}
