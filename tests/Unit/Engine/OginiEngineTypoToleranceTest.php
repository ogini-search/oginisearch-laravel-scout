<?php

namespace Tests\Unit\Engine;

use Orchestra\Testbench\TestCase;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\Pagination\OginiPaginator;
use OginiScoutDriver\Client\OginiClient;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class OginiEngineTypoToleranceTest extends TestCase
{
    protected OginiEngine $engine;
    protected $mockClient;
    protected $mockModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(OginiClient::class);
        $this->mockModel = Mockery::mock(Model::class);

        $this->engine = new OginiEngine($this->mockClient, []);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_handles_typo_tolerance_in_pagination_results()
    {
        // Mock API response with typo tolerance
        $apiResponse = [
            'data' => [
                'hits' => [
                    [
                        'id' => '1',
                        'source' => ['name' => 'Test Business']
                    ]
                ],
                'total' => '1',
                'maxScore' => 1.0
            ],
            'pagination' => [
                'currentPage' => 1,
                'totalPages' => 1,
                'pageSize' => 10,
                'hasNext' => false,
                'hasPrevious' => false,
                'totalResults' => '1'
            ],
            'took' => 50,
            'typoTolerance' => [
                'originalQuery' => 'londry',
                'correctedQuery' => 'laundry',
                'confidence' => 0.85,
                'suggestions' => [
                    [
                        'text' => 'laundry',
                        'score' => 85.0,
                        'freq' => 1,
                        'distance' => 2
                    ]
                ],
                'corrections' => [
                    [
                        'original' => 'londry',
                        'corrected' => 'laundry',
                        'confidence' => 0.85
                    ]
                ]
            ]
        ];

        // Mock the search method
        $this->mockClient->shouldReceive('search')
            ->once()
            ->andReturn($apiResponse);

        // Mock the model
        $this->mockModel->shouldReceive('searchableAs')->andReturn('test_index');
        $this->mockModel->shouldReceive('getScoutKey')->andReturn('1');
        $this->mockModel->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test Business']);
        $this->mockModel->shouldReceive('getScoutModelsByIds')
            ->andReturn(collect([$this->mockModel]));

        // Create a builder
        $builder = new Builder($this->mockModel, 'londry');

        // Test pagination with typo tolerance
        $result = $this->engine->paginate($builder, 10, 1);

        // Assertions
        $this->assertInstanceOf(OginiPaginator::class, $result);
        $this->assertTrue($result->hasTypoTolerance());
        $this->assertEquals('londry', $result->getOriginalQuery());
        $this->assertEquals('laundry', $result->getCorrectedQuery());
        $this->assertEquals(0.85, $result->getTypoConfidence());
        $this->assertCount(1, $result->getTypoSuggestions());
        $this->assertCount(1, $result->getTypoCorrections());

        // Test array conversion includes typo tolerance
        $array = $result->toArray();
        $this->assertArrayHasKey('typo_tolerance', $array);
        $this->assertEquals('londry', $array['typo_tolerance']['originalQuery']);
        $this->assertEquals('laundry', $array['typo_tolerance']['correctedQuery']);
    }

    /** @test */
    public function it_handles_search_results_without_typo_tolerance()
    {
        // Mock API response without typo tolerance
        $apiResponse = [
            'data' => [
                'hits' => [
                    [
                        'id' => '1',
                        'source' => ['name' => 'Test Business']
                    ]
                ],
                'total' => '1',
                'maxScore' => 1.0
            ],
            'pagination' => [
                'currentPage' => 1,
                'totalPages' => 1,
                'pageSize' => 10,
                'hasNext' => false,
                'hasPrevious' => false,
                'totalResults' => '1'
            ],
            'took' => 50
        ];

        // Mock the search method
        $this->mockClient->shouldReceive('search')
            ->once()
            ->andReturn($apiResponse);

        // Mock the model
        $this->mockModel->shouldReceive('searchableAs')->andReturn('test_index');
        $this->mockModel->shouldReceive('getScoutKey')->andReturn('1');
        $this->mockModel->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test Business']);
        $this->mockModel->shouldReceive('getScoutModelsByIds')
            ->andReturn(collect([$this->mockModel]));

        // Create a builder
        $builder = new Builder($this->mockModel, 'laundry');

        // Test pagination without typo tolerance
        $result = $this->engine->paginate($builder, 10, 1);

        // Assertions
        $this->assertInstanceOf(OginiPaginator::class, $result);
        $this->assertFalse($result->hasTypoTolerance());
        $this->assertNull($result->getOriginalQuery());
        $this->assertNull($result->getCorrectedQuery());
        $this->assertNull($result->getTypoConfidence());
        $this->assertEmpty($result->getTypoSuggestions());
        $this->assertEmpty($result->getTypoCorrections());
    }

    /** @test */
    public function it_extracts_typo_tolerance_from_legacy_response_format()
    {
        // Mock API response with typo tolerance in legacy format
        $apiResponse = [
            'hits' => [
                'hits' => [
                    [
                        'id' => '1',
                        'source' => ['name' => 'Test Business']
                    ]
                ],
                'total' => '1',
                'maxScore' => 1.0
            ],
            'took' => 50,
            'typoTolerance' => [
                'originalQuery' => 'londry',
                'correctedQuery' => 'laundry',
                'confidence' => 0.85
            ]
        ];

        // Mock the search method
        $this->mockClient->shouldReceive('search')
            ->once()
            ->andReturn($apiResponse);

        // Mock the model
        $this->mockModel->shouldReceive('searchableAs')->andReturn('test_index');
        $this->mockModel->shouldReceive('getScoutKey')->andReturn('1');
        $this->mockModel->shouldReceive('toArray')->andReturn(['id' => 1, 'name' => 'Test Business']);
        $this->mockModel->shouldReceive('getScoutModelsByIds')
            ->andReturn(collect([$this->mockModel]));

        // Create a builder
        $builder = new Builder($this->mockModel, 'londry');

        // Test pagination with typo tolerance
        $result = $this->engine->paginate($builder, 10, 1);

        // Assertions
        $this->assertTrue($result->hasTypoTolerance());
        $this->assertEquals('londry', $result->getOriginalQuery());
        $this->assertEquals('laundry', $result->getCorrectedQuery());
        $this->assertEquals(0.85, $result->getTypoConfidence());
    }
}
