<?php

namespace OginiScoutDriver\Tests\Unit\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Exceptions\OginiException;
use PHPUnit\Framework\TestCase;

class IndexManagementTest extends TestCase
{
    protected OginiClient $client;
    protected string $baseUrl = 'https://api.example.com';
    protected string $apiKey = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OginiClient($this->baseUrl, $this->apiKey);
    }

    public function testCreateIndexWithFullConfiguration(): void
    {
        $indexName = 'products';
        $configuration = [
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text'],
                    'description' => ['type' => 'text'],
                    'price' => ['type' => 'float'],
                    'tags' => ['type' => 'keyword']
                ]
            ]
        ];
        $responseData = [
            'success' => true,
            'index' => $indexName,
            'id' => 'idx_123'
        ];

        $mock = new MockHandler([
            new Response(201, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->createIndex($indexName, $configuration);

        $this->assertEquals($responseData, $result);
    }

    public function testCreateIndexWithMinimalConfiguration(): void
    {
        $indexName = 'simple-index';
        $responseData = [
            'success' => true,
            'index' => $indexName,
            'id' => 'idx_456'
        ];

        $mock = new MockHandler([
            new Response(201, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->createIndex($indexName);

        $this->assertEquals($responseData, $result);
    }

    public function testCreateIndexThrowsExceptionWhenIndexExists(): void
    {
        $this->expectException(OginiException::class);
        $this->expectExceptionMessage('Index already exists');

        $errorResponse = [
            'error' => 'Index already exists',
            'code' => 'INDEX_EXISTS'
        ];

        $mock = new MockHandler([
            new Response(409, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        try {
            $this->client->createIndex('existing-index');
        } catch (OginiException $e) {
            $this->assertEquals(409, $e->getCode());
            $this->assertEquals('INDEX_EXISTS', $e->getErrorCode());
            $this->assertEquals($errorResponse, $e->getResponse());
            throw $e;
        }
    }

    public function testCreateIndexThrowsExceptionOnValidationError(): void
    {
        $this->expectException(OginiException::class);

        $errorResponse = [
            'error' => 'Invalid mapping configuration',
            'code' => 'VALIDATION_ERROR',
            'details' => ['field_type_invalid' => 'Unknown field type: unknown']
        ];

        $mock = new MockHandler([
            new Response(400, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $invalidConfiguration = [
            'mappings' => [
                'properties' => [
                    'field' => ['type' => 'unknown']
                ]
            ]
        ];

        $this->client->createIndex('invalid-index', $invalidConfiguration);
    }

    public function testGetIndexReturnsIndexInformation(): void
    {
        $indexName = 'products';
        $responseData = [
            'name' => $indexName,
            'id' => 'idx_123',
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text'],
                    'description' => ['type' => 'text'],
                    'price' => ['type' => 'float']
                ]
            ],
            'document_count' => 1250,
            'created_at' => '2023-01-01T00:00:00Z',
            'updated_at' => '2023-01-15T10:30:00Z'
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->getIndex($indexName);

        $this->assertEquals($responseData, $result);
        $this->assertEquals($indexName, $result['name']);
        $this->assertEquals(1250, $result['document_count']);
    }

    public function testGetIndexThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(OginiException::class);
        $this->expectExceptionMessage('Index not found');

        $errorResponse = [
            'error' => 'Index not found',
            'code' => 'INDEX_NOT_FOUND'
        ];

        $mock = new MockHandler([
            new Response(404, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        try {
            $this->client->getIndex('non-existent-index');
        } catch (OginiException $e) {
            $this->assertEquals(404, $e->getCode());
            $this->assertEquals('INDEX_NOT_FOUND', $e->getErrorCode());
            $this->assertTrue($e->isClientError());
            throw $e;
        }
    }

    public function testDeleteIndexSuccess(): void
    {
        $indexName = 'old-index';
        $responseData = [
            'success' => true,
            'message' => 'Index deleted successfully',
            'index' => $indexName
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->deleteIndex($indexName);

        $this->assertEquals($responseData, $result);
        $this->assertTrue($result['success']);
    }

    public function testDeleteIndexWithNoContentResponse(): void
    {
        $indexName = 'deleted-index';

        $mock = new MockHandler([
            new Response(204, [], '') // No content response
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->deleteIndex($indexName);

        $this->assertEquals([], $result); // Empty array for no content
    }

    public function testDeleteIndexThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(OginiException::class);

        $errorResponse = [
            'error' => 'Index not found',
            'code' => 'INDEX_NOT_FOUND'
        ];

        $mock = new MockHandler([
            new Response(404, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $this->client->deleteIndex('non-existent-index');
    }

    public function testDeleteIndexThrowsExceptionWhenIndexHasDocuments(): void
    {
        $this->expectException(OginiException::class);

        $errorResponse = [
            'error' => 'Cannot delete index with documents',
            'code' => 'INDEX_NOT_EMPTY',
            'details' => ['document_count' => 150]
        ];

        $mock = new MockHandler([
            new Response(409, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        try {
            $this->client->deleteIndex('index-with-docs');
        } catch (OginiException $e) {
            $this->assertEquals(409, $e->getCode());
            $this->assertEquals('INDEX_NOT_EMPTY', $e->getErrorCode());
            $this->assertTrue($e->isClientError());
            $this->assertArrayHasKey('details', $e->getResponse());
            throw $e;
        }
    }
}
