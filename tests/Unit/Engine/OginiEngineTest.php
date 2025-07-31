<?php

namespace OginiScoutDriver\Tests\Unit\Engine;

use Laravel\Scout\Builder;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\Exceptions\OginiException;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class OginiEngineTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    protected OginiClient $mockClient;
    protected OginiEngine $engine;
    protected Model $mockModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(OginiClient::class);
        $this->engine = new OginiEngine($this->mockClient, []);

        $this->mockModel = Mockery::mock(Model::class);
        $this->mockModel->shouldReceive('searchableAs')->andReturn('test_index');
        $this->mockModel->shouldReceive('getScoutKey')->andReturn('1');
        $this->mockModel->shouldReceive('toSearchableArray')->andReturn([
            'title' => 'Test Title',
            'content' => 'Test Content',
            'status' => 'published'
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // Update Tests

    public function testUpdateWithModels(): void
    {
        $models = new Collection([$this->mockModel]);

        // The engine calls updateDocument first, then indexDocument if update fails
        $this->mockClient->shouldReceive('updateDocument')
            ->once()
            ->with('test_index', '1', [
                'title' => 'Test Title',
                'content' => 'Test Content',
                'status' => 'published'
            ])
            ->andReturn(['success' => true]);

        $this->engine->update($models);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    public function testUpdateWithEmptyCollection(): void
    {
        $models = new Collection([]);

        $this->mockClient->shouldNotReceive('bulkIndexDocuments');

        $this->engine->update($models);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    public function testUpdateFallsBackToIndividualIndexing(): void
    {
        $models = new Collection([$this->mockModel]);

        // First, updateDocument fails, then indexDocument succeeds
        $this->mockClient->shouldReceive('updateDocument')
            ->once()
            ->with('test_index', '1', [
                'title' => 'Test Title',
                'content' => 'Test Content',
                'status' => 'published'
            ])
            ->andThrow(new OginiException('Update operation failed'));

        $this->mockClient->shouldReceive('indexDocument')
            ->once()
            ->with('test_index', '1', [
                'title' => 'Test Title',
                'content' => 'Test Content',
                'status' => 'published'
            ])
            ->andReturn(['success' => true]);

        $this->engine->update($models);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    // Delete Tests

    public function testDeleteWithModels(): void
    {
        $models = new Collection([$this->mockModel]);

        $this->mockClient->shouldReceive('deleteDocument')
            ->once()
            ->with('test_index', '1')
            ->andReturn(['success' => true]);

        $this->engine->delete($models);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    public function testDeleteWithEmptyCollection(): void
    {
        $models = new Collection([]);

        $this->mockClient->shouldNotReceive('deleteDocument');

        $this->engine->delete($models);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    public function testDeleteContinuesOnError(): void
    {
        $model2 = Mockery::mock(Model::class);
        $model2->shouldReceive('searchableAs')->andReturn('test_index');
        $model2->shouldReceive('getScoutKey')->andReturn('2');

        $models = new Collection([$this->mockModel, $model2]);

        $this->mockClient->shouldReceive('deleteDocument')
            ->with('test_index', '1')
            ->andThrow(new OginiException('Delete failed'));

        $this->mockClient->shouldReceive('deleteDocument')
            ->with('test_index', '2')
            ->andReturn(['success' => true]);

        $this->engine->delete($models);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    // Search Tests

    public function testSearch(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = $this->mockModel;
        $builder->query = 'test query';
        $builder->limit = 10;
        $builder->wheres = [];
        $builder->orders = [];
        $builder->callback = null;

        $searchResults = [
            'data' => [
                'total' => 5,
                'hits' => [
                    ['id' => '1', 'score' => 0.95, 'source' => ['title' => 'Test Title']]
                ]
            ],
            'took' => 15
        ];

        $this->mockClient->shouldReceive('search')
            ->once()
            ->with('test_index', 'test query', Mockery::type('array'))
            ->andReturn($searchResults);

        $result = $this->engine->search($builder);

        $this->assertEquals($searchResults, $result);
    }

    public function testPaginate(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = $this->mockModel;
        $builder->query = 'test query';
        $builder->wheres = [];
        $builder->orders = [];
        $builder->callback = null;

        $searchResults = [
            'data' => [
                'total' => 25,
                'hits' => [
                    ['id' => '11', 'score' => 0.85, 'source' => ['title' => 'Page 2 Title']]
                ]
            ],
            'took' => 20
        ];

        $this->mockClient->shouldReceive('search')
            ->once()
            ->with('test_index', 'test query', Mockery::type('array')) // Page 2, 10 per page
            ->andReturn($searchResults);

        // Mock the model methods needed for pagination
        $this->mockModel->shouldReceive('getScoutModelsByIds')
            ->andReturn(new Collection());

        $result = $this->engine->paginate($builder, 10, 2);

        $this->assertInstanceOf(\OginiScoutDriver\Pagination\OginiPaginator::class, $result);
        $this->assertEquals(25, $result->total());
        $this->assertEquals(10, $result->perPage());
        $this->assertEquals(2, $result->currentPage());
    }

    // Map Tests

    public function testMapWithResults(): void
    {
        $builder = Mockery::mock(Builder::class);

        $results = [
            'hits' => [
                'hits' => [
                    ['id' => '1', 'source' => ['title' => 'First']],
                    ['id' => '2', 'source' => ['title' => 'Second']]
                ]
            ]
        ];

        $model1 = Mockery::mock(Model::class);
        $model1->shouldReceive('getScoutKey')->andReturn('1');
        $model1->shouldReceive('toArray')->andReturn(['id' => '1', 'title' => 'First']);

        $model2 = Mockery::mock(Model::class);
        $model2->shouldReceive('getScoutKey')->andReturn('2');
        $model2->shouldReceive('toArray')->andReturn(['id' => '2', 'title' => 'Second']);

        $modelCollection = new Collection([$model1, $model2]);

        $this->mockModel->shouldReceive('getScoutModelsByIds')
            ->once()
            ->with($builder, ['1', '2'])
            ->andReturn($modelCollection);

        $this->mockModel->shouldReceive('newCollection')
            ->andReturn(new Collection());

        $result = $this->engine->map($builder, $results, $this->mockModel);

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function testMapWithEmptyResults(): void
    {
        $builder = Mockery::mock(Builder::class);
        $results = ['hits' => ['hits' => []]];

        $emptyCollection = new Collection();
        $this->mockModel->shouldReceive('newCollection')->andReturn($emptyCollection);

        $result = $this->engine->map($builder, $results, $this->mockModel);

        $this->assertEquals($emptyCollection, $result);
    }

    // Index Management Tests

    public function testCreateIndex(): void
    {
        $options = [
            'settings' => ['numberOfShards' => 1],
            'mappings' => ['properties' => ['title' => ['type' => 'text']]]
        ];

        $this->mockClient->shouldReceive('createIndex')
            ->once()
            ->with('test_index', $options)
            ->andReturn(['success' => true]);

        $result = $this->engine->createIndex('test_index', $options);

        $this->assertEquals(['success' => true], $result);
    }

    public function testDeleteIndex(): void
    {
        $this->mockClient->shouldReceive('deleteIndex')
            ->once()
            ->with('test_index')
            ->andReturn(['success' => true]);

        $result = $this->engine->deleteIndex('test_index');

        $this->assertEquals(['success' => true], $result);
    }

    public function testFlushWithDeleteByQuery(): void
    {
        $this->mockClient->shouldReceive('deleteByQuery')
            ->once()
            ->with('test_index', ['match_all' => []])
            ->andReturn(['deleted' => 10]);

        $this->engine->flush($this->mockModel);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    public function testFlushFallsBackToIndexRecreation(): void
    {
        $this->mockClient->shouldReceive('deleteByQuery')
            ->once()
            ->andThrow(new OginiException('Delete by query not supported'));

        $this->mockClient->shouldReceive('deleteIndex')
            ->once()
            ->with('test_index')
            ->andReturn(['success' => true]);

        $this->mockClient->shouldReceive('createIndex')
            ->once()
            ->with('test_index')
            ->andReturn(['success' => true]);

        $this->engine->flush($this->mockModel);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    public function testFlushWithCustomIndexConfiguration(): void
    {
        $model = new TestModelWithConfiguration();
        $customConfig = [
            'settings' => ['numberOfShards' => 2],
            'mappings' => ['properties' => ['title' => ['type' => 'text']]]
        ];

        $this->mockClient->shouldReceive('deleteByQuery')
            ->once()
            ->andThrow(new OginiException('Delete by query not supported'));

        $this->mockClient->shouldReceive('deleteIndex')
            ->once()
            ->with('test_index')
            ->andReturn(['success' => true]);

        $this->mockClient->shouldReceive('createIndex')
            ->once()
            ->with('test_index', $customConfig)
            ->andReturn(['success' => true]);

        $this->engine->flush($model);

        // Test passes if no exception is thrown and mock expectations are met
        $this->assertTrue(true);
    }

    // Utility Tests

    public function testGetTotalCount(): void
    {
        $results = ['data' => ['total' => 42]];

        $count = $this->engine->getTotalCount($results);

        $this->assertEquals(42, $count);
    }

    public function testGetTotalCountWithMissingData(): void
    {
        $results = [];

        $count = $this->engine->getTotalCount($results);

        $this->assertEquals(0, $count);
    }

    public function testMapIds(): void
    {
        $results = [
            'data' => [
                'hits' => [
                    ['id' => '1'],
                    ['id' => '2'],
                    ['id' => '3']
                ]
            ]
        ];

        $ids = $this->engine->mapIds($results);

        $this->assertEquals(['1', '2', '3'], $ids->toArray());
    }

    public function testMapIdsWithEmptyResults(): void
    {
        $results = [];

        $ids = $this->engine->mapIds($results);

        $this->assertTrue($ids->isEmpty());
    }

    public function testKeys(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->model = $this->mockModel;
        $builder->query = 'test';
        $builder->limit = null;
        $builder->wheres = [];
        $builder->orders = [];
        $builder->callback = null;

        $searchResults = [
            'data' => [
                'hits' => [
                    ['id' => '1'],
                    ['id' => '2']
                ]
            ]
        ];

        $this->mockClient->shouldReceive('search')
            ->once()
            ->andReturn($searchResults);

        $keys = $this->engine->keys($builder);

        $this->assertEquals(['1', '2'], $keys->toArray());
    }

    public function testGetClient(): void
    {
        $client = $this->engine->getClient();

        $this->assertSame($this->mockClient, $client);
    }

    // Build Search Query Tests

    public function testBuildSearchQueryWithTextQuery(): void
    {
        $model = new TestModelWithConfiguration();
        $builder = Mockery::mock(Builder::class);
        $builder->query = 'test search';
        $builder->limit = null;
        $builder->wheres = [];
        $builder->orders = [];
        $builder->callback = null;
        $builder->model = $model;

        $this->mockClient->shouldReceive('search')
            ->once()
            ->with('test_index', 'test search', Mockery::on(function ($options) {
                return isset($options['query']['match']['value']) &&
                    $options['query']['match']['value'] === 'test search' &&
                    isset($options['fields']) &&
                    $options['fields'] === ['title', 'content'];
            }))
            ->andReturn(['data' => ['hits' => []]]);

        $result = $this->engine->search($builder);

        $this->assertIsArray($result);
    }

    public function testBuildSearchQueryWithFilters(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->query = '';
        $builder->wheres = [
            ['column' => 'status', 'value' => 'published'],
            ['column' => 'category', 'value' => 'tech']
        ];
        $builder->orders = [];
        $builder->callback = null;
        $builder->model = $this->mockModel;

        $this->mockClient->shouldReceive('search')
            ->once()
            ->with('test_index', '', Mockery::on(function ($options) {
                return isset($options['filter']['bool']['must']) &&
                    count($options['filter']['bool']['must']) === 2;
            }))
            ->andReturn(['data' => ['hits' => []]]);

        $result = $this->engine->search($builder);

        $this->assertIsArray($result);
    }

    public function testBuildSearchQueryWithSorting(): void
    {
        $builder = Mockery::mock(Builder::class);
        $builder->query = 'test';
        $builder->wheres = [];
        $builder->orders = [
            ['column' => 'created_at', 'direction' => 'desc'],
            ['column' => 'title', 'direction' => 'asc']
        ];
        $builder->callback = null;
        $builder->model = $this->mockModel;

        $this->mockClient->shouldReceive('search')
            ->once()
            ->with('test_index', 'test', Mockery::on(function ($options) {
                return isset($options['sort']) && $options['sort'] === 'created_at:desc,title:asc';
            }))
            ->andReturn(['data' => ['hits' => []]]);

        $result = $this->engine->search($builder);

        $this->assertIsArray($result);
    }
}

class TestModelWithConfiguration extends Model
{
    public function searchableAs(): string
    {
        return 'test_index';
    }

    public function getScoutKey()
    {
        return '1';
    }

    public function toSearchableArray(): array
    {
        return [
            'title' => 'Test Title',
            'content' => 'Test Content',
            'status' => 'published'
        ];
    }

    public function getOginiIndexConfiguration(): array
    {
        return [
            'settings' => ['numberOfShards' => 2],
            'mappings' => ['properties' => ['title' => ['type' => 'text']]]
        ];
    }

    public function getSearchFields(): array
    {
        return ['title', 'content'];
    }
}
