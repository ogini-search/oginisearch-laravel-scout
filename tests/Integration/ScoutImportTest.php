<?php

namespace OginiScoutDriver\Tests\Integration;

use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\Exceptions\OginiException;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * @group integration-tests
 */
class ScoutImportTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected OginiEngine $engine;
    protected OginiClient $mockClient;
    protected Model $mockModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(OginiClient::class);
        $this->engine = new OginiEngine($this->mockClient);

        $this->mockModel = Mockery::mock(Model::class);
        $this->mockModel->shouldReceive('searchableAs')->andReturn('searchable_models');
        $this->mockModel->shouldReceive('getScoutKey')->andReturn('1');
        $this->mockModel->shouldReceive('toSearchableArray')->andReturn([
            'id' => 1,
            'title' => 'First Article'
        ]);
    }

    /**
     * Test that scout:import calls the update method correctly.
     *
     * @test
     */
    public function it_handles_scout_import_correctly()
    {
        // Create additional mock models
        $model2 = Mockery::mock(Model::class);
        $model2->shouldReceive('searchableAs')->andReturn('searchable_models');
        $model2->shouldReceive('getScoutKey')->andReturn('2');
        $model2->shouldReceive('toSearchableArray')->andReturn(['id' => 2, 'title' => 'Second Article']);

        $model3 = Mockery::mock(Model::class);
        $model3->shouldReceive('searchableAs')->andReturn('searchable_models');
        $model3->shouldReceive('getScoutKey')->andReturn('3');
        $model3->shouldReceive('toSearchableArray')->andReturn(['id' => 3, 'title' => 'Third Article']);

        $models = new Collection([$this->mockModel, $model2, $model3]);

        // Expected documents for bulk indexing
        $expectedDocuments = [
            ['id' => '1', 'document' => ['id' => 1, 'title' => 'First Article']],
            ['id' => '2', 'document' => ['id' => 2, 'title' => 'Second Article']],
            ['id' => '3', 'document' => ['id' => 3, 'title' => 'Third Article']],
        ];

        // Mock the bulk indexing call
        $this->mockClient->shouldReceive('bulkIndexDocuments')
            ->once()
            ->with('searchable_models', $expectedDocuments)
            ->andReturn(['success' => true]);

        // This simulates what scout:import does
        $this->engine->update($models);

        $this->assertTrue(true); // If we get here without exception, the test passes
    }

    /**
     * Test fallback to individual indexing when bulk fails.
     *
     * @test
     */
    public function it_falls_back_to_individual_indexing_when_bulk_fails()
    {
        $model2 = Mockery::mock(Model::class);
        $model2->shouldReceive('searchableAs')->andReturn('searchable_models');
        $model2->shouldReceive('getScoutKey')->andReturn('2');
        $model2->shouldReceive('toSearchableArray')->andReturn(['id' => 2, 'title' => 'Second Article']);

        $models = new Collection([$this->mockModel, $model2]);

        // Mock bulk indexing to fail
        $this->mockClient->shouldReceive('bulkIndexDocuments')
            ->once()
            ->andThrow(new OginiException('Bulk indexing failed'));

        // Mock individual indexing calls with correct parameter order
        $this->mockClient->shouldReceive('indexDocument')
            ->with('searchable_models', '1', ['id' => 1, 'title' => 'First Article'])
            ->once()
            ->andReturn(['success' => true]);

        $this->mockClient->shouldReceive('indexDocument')
            ->with('searchable_models', '2', ['id' => 2, 'title' => 'Second Article'])
            ->once()
            ->andReturn(['success' => true]);

        // This should fallback to individual indexing
        $this->engine->update($models);

        $this->assertTrue(true); // If we get here without exception, the test passes
    }

    /**
     * Test search functionality works correctly.
     *
     * @test
     */
    public function it_handles_search_correctly()
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = $this->mockModel;
        $builder->query = 'test query';
        $builder->limit = 10;
        $builder->wheres = [];
        $builder->orders = [];
        $builder->callback = null;

        $expectedSearchResponse = [
            'data' => [
                'hits' => [
                    ['id' => 1, 'score' => 1.0],
                    ['id' => 2, 'score' => 0.8],
                ]
            ],
            'total' => 2,
        ];

        // Mock the search call with correct parameters
        $this->mockClient->shouldReceive('search')
            ->with('searchable_models', 'test query', Mockery::type('array'))
            ->once()
            ->andReturn($expectedSearchResponse);

        $result = $this->engine->search($builder);

        $this->assertEquals($expectedSearchResponse, $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('hits', $result['data']);
        $this->assertCount(2, $result['data']['hits']);
    }

    /**
     * Test mapping search results to models.
     *
     * @test
     */
    public function it_maps_search_results_to_models_correctly()
    {
        $builder = Mockery::mock(Builder::class);
        $searchResults = [
            'data' => [
                'hits' => [
                    ['id' => '1', 'score' => 1.0],
                    ['id' => '2', 'score' => 0.8],
                ]
            ]
        ];

        // Mock model with necessary methods
        $modelMock = Mockery::mock(Model::class);
        $expectedModels = new Collection([$this->mockModel]);

        $modelMock->shouldReceive('newCollection')->andReturn(new Collection());
        $modelMock->shouldReceive('getScoutModelsByIds')
            ->with($builder, ['1', '2'])
            ->andReturn($expectedModels);

        $mappedResults = $this->engine->map($builder, $searchResults, $modelMock);

        $this->assertInstanceOf(Collection::class, $mappedResults);
    }

    /**
     * Test empty collection handling.
     *
     * @test
     */
    public function it_handles_empty_model_collections()
    {
        $emptyCollection = new Collection();

        // Mock client should not be called for empty collections
        $this->mockClient->shouldNotReceive('bulkIndexDocuments');
        $this->mockClient->shouldNotReceive('indexDocument');

        // This should return early without any API calls
        $this->engine->update($emptyCollection);

        $this->assertTrue(true); // If we get here without exception, the test passes
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
