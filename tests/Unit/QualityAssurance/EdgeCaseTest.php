<?php

namespace OginiScoutDriver\Tests\Unit\QualityAssurance;

use PHPUnit\Framework\TestCase;
use OginiScoutDriver\Engine\OginiEngine;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Exceptions\OginiException;
use OginiScoutDriver\Exceptions\ConnectionException;
use OginiScoutDriver\Exceptions\ValidationException;
use OginiScoutDriver\Exceptions\RateLimitException;
use OginiScoutDriver\Exceptions\IndexNotFoundException;
use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Model;
use Mockery;

/**
 * @group quality-assurance
 */
class EdgeCaseTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test handling of extremely large datasets.
     */
    public function testExtremelyLargeDatasets(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        // Test with very large document
        $largeDocument = [
            'id' => 1,
            'title' => str_repeat('Large Title ', 1000),
            'content' => str_repeat('Large content with many words. ', 10000),
            'metadata' => array_fill(0, 1000, 'metadata_value'),
        ];

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');
        $model->shouldReceive('getScoutKey')->andReturn(1);
        $model->shouldReceive('toSearchableArray')->andReturn($largeDocument);

        $client->shouldReceive('bulkIndexDocuments')
            ->once()
            ->with('test_index', Mockery::any())
            ->andReturn(['indexed' => 1]);

        $engine->update(collect([$model]));
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    /**
     * Test handling of empty and null values.
     */
    public function testEmptyAndNullValues(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        // Test empty collection
        $engine->update(collect([]));
        $this->assertTrue(true); // Should handle empty collection gracefully

        // Test null values in document
        $documentWithNulls = [
            'id' => 1,
            'title' => null,
            'content' => '',
            'metadata' => null,
        ];

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');
        $model->shouldReceive('getScoutKey')->andReturn(1);
        $model->shouldReceive('toSearchableArray')->andReturn($documentWithNulls);

        $client->shouldReceive('bulkIndexDocuments')
            ->once()
            ->with('test_index', Mockery::any())
            ->andReturn(['indexed' => 1]);

        $engine->update(collect([$model]));
        $this->assertTrue(true);
    }

    /**
     * Test handling of special characters and encoding.
     */
    public function testSpecialCharactersAndEncoding(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        $specialDocument = [
            'id' => 1,
            'title' => '🚀 Special chars: àáâãäåæçèéêë ñòóôõö ùúûüý',
            'content' => 'Content with emojis 😀😃😄😁 and symbols ©®™',
            'unicode' => 'Unicode: 中文 العربية русский 日本語',
        ];

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');
        $model->shouldReceive('getScoutKey')->andReturn(1);
        $model->shouldReceive('toSearchableArray')->andReturn($specialDocument);

        $client->shouldReceive('bulkIndexDocuments')
            ->once()
            ->with('test_index', Mockery::any())
            ->andReturn(['indexed' => 1]);

        $engine->update(collect([$model]));
        $this->assertTrue(true);
    }

    /**
     * Test network timeout scenarios.
     */
    public function testNetworkTimeoutScenarios(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');

        $builder = Mockery::mock(Builder::class);
        $builder->model = $model;
        $builder->query = 'test';
        $builder->wheres = [];
        $builder->limit = null;
        $builder->callback = null;
        $builder->orders = [];

        $client->shouldReceive('search')
            ->once()
            ->with('test_index', 'test', Mockery::any())
            ->andThrow(ConnectionException::timeout('http://localhost:3000', 30));

        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('Connection to http://localhost:3000 timed out after 30 seconds');

        $engine->search($builder);
    }

    /**
     * Test rate limiting edge cases.
     */
    public function testRateLimitingEdgeCases(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');

        $builder = Mockery::mock(Builder::class);
        $builder->model = $model;
        $builder->query = 'test';
        $builder->wheres = [];
        $builder->limit = null;
        $builder->callback = null;
        $builder->orders = [];

        // Test rate limit with zero remaining requests
        $client->shouldReceive('search')
            ->once()
            ->with('test_index', 'test', Mockery::any())
            ->andThrow(RateLimitException::forSearch(60));

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Search request rate limit exceeded');

        $engine->search($builder);
    }

    /**
     * Test malformed response handling.
     */
    public function testMalformedResponseHandling(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');

        $builder = Mockery::mock(Builder::class);
        $builder->model = $model;
        $builder->query = 'test';
        $builder->wheres = [];
        $builder->limit = null;
        $builder->callback = null;
        $builder->orders = [];

        // Test malformed search response
        $client->shouldReceive('search')
            ->once()
            ->with('test_index', 'test', Mockery::any())
            ->andReturn([
                'hits' => 'invalid_format', // Should be array
                'total' => 'not_a_number',  // Should be integer
            ]);

        $result = $engine->search($builder);

        // Engine should handle malformed response gracefully
        $this->assertIsArray($result);
    }

    /**
     * Test boundary value scenarios.
     */
    public function testBoundaryValueScenarios(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        // Test with maximum integer values
        $boundaryDocument = [
            'id' => PHP_INT_MAX,
            'score' => PHP_FLOAT_MAX,
            'timestamp' => 0,
            'negative_id' => PHP_INT_MIN,
        ];

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');
        $model->shouldReceive('getScoutKey')->andReturn(PHP_INT_MAX);
        $model->shouldReceive('toSearchableArray')->andReturn($boundaryDocument);

        $client->shouldReceive('bulkIndexDocuments')
            ->once()
            ->with('test_index', Mockery::any())
            ->andReturn(['indexed' => 1]);

        $engine->update(collect([$model]));
        $this->assertTrue(true);
    }

    /**
     * Test exception chaining scenarios.
     */
    public function testExceptionChainingScenarios(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');

        $builder = Mockery::mock(Builder::class);
        $builder->model = $model;
        $builder->query = 'test';
        $builder->wheres = [];
        $builder->limit = null;
        $builder->callback = null;
        $builder->orders = [];

        $originalException = new \Exception('Original error');
        $chainedException = new OginiException(
            'Chained error',
            500,
            $originalException,
            ['error' => 'chained'],
            'CHAINED_ERROR'
        );

        $client->shouldReceive('search')
            ->once()
            ->with('test_index', 'test', Mockery::any())
            ->andThrow($chainedException);

        try {
            $engine->search($builder);
            $this->fail('Expected exception was not thrown');
        } catch (OginiException $e) {
            $this->assertEquals('Chained error', $e->getMessage());
            $this->assertEquals('Original error', $e->getPrevious()->getMessage());
            $this->assertEquals('CHAINED_ERROR', $e->getErrorCode());
        }
    }

    /**
     * Test validation edge cases.
     */
    public function testValidationEdgeCases(): void
    {
        $client = Mockery::mock(OginiClient::class);
        $engine = new OginiEngine($client, ['index' => 'test_index']);

        // Test validation with edge case data
        $edgeCaseDocument = [
            'id' => '',  // Empty string ID
            'title' => 0, // Numeric title
            'content' => false, // Boolean content
            'array_field' => [null, '', 0, false], // Mixed array
        ];

        $model = Mockery::mock(Model::class);
        $model->shouldReceive('searchableAs')->andReturn('test_index');
        $model->shouldReceive('getScoutKey')->andReturn('');
        $model->shouldReceive('toSearchableArray')->andReturn($edgeCaseDocument);

        $client->shouldReceive('bulkIndexDocuments')
            ->once()
            ->with('test_index', Mockery::any())
            ->andThrow(new ValidationException(
                'Validation failed',
                ['id' => 'ID cannot be empty'],
                $edgeCaseDocument
            ));

        // The engine will fallback to indexDocument when bulk fails
        $client->shouldReceive('indexDocument')
            ->once()
            ->with('test_index', '', $edgeCaseDocument)
            ->andThrow(new ValidationException(
                'Validation failed',
                ['id' => 'ID cannot be empty'],
                $edgeCaseDocument
            ));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $engine->update(collect([$model]));
    }
}
