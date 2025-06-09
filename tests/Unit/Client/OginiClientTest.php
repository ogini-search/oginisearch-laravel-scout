<?php

namespace OginiScoutDriver\Tests\Unit\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Exceptions\OginiException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class OginiClientTest extends TestCase
{
    protected OginiClient $client;
    protected string $baseUrl = 'https://api.example.com';
    protected string $apiKey = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OginiClient($this->baseUrl, $this->apiKey);
    }

    public function testConstructorSetsPropertiesCorrectly(): void
    {
        $config = ['timeout' => 60, 'retry_attempts' => 5];
        $client = new OginiClient($this->baseUrl, $this->apiKey, $config);

        $this->assertEquals($this->baseUrl, $client->getBaseUrl());
        $this->assertEquals($this->apiKey, $client->getApiKey());
        $this->assertEquals(60, $client->getConfig()['timeout']);
        $this->assertEquals(5, $client->getConfig()['retry_attempts']);
    }

    public function testConstructorTrimsBaseUrl(): void
    {
        $urlWithTrailingSlash = 'https://api.example.com/';
        $client = new OginiClient($urlWithTrailingSlash, $this->apiKey);

        $this->assertEquals('https://api.example.com', $client->getBaseUrl());
    }

    public function testConstructorMergesDefaultConfig(): void
    {
        $client = new OginiClient($this->baseUrl, $this->apiKey);
        $config = $client->getConfig();

        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals(3, $config['retry_attempts']);
        $this->assertEquals(100, $config['retry_delay']);
    }

    // Index Management Tests

    public function testCreateIndexSuccess(): void
    {
        $indexName = 'test-index';
        $configuration = [
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text'],
                    'description' => ['type' => 'text'],
                    'price' => ['type' => 'float']
                ]
            ]
        ];
        $responseData = ['success' => true, 'index' => $indexName];

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
        $indexName = 'minimal-index';
        $responseData = ['success' => true, 'index' => $indexName];

        $mock = new MockHandler([
            new Response(201, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->createIndex($indexName);

        $this->assertEquals($responseData, $result);
    }

    public function testCreateIndexThrowsExceptionOnError(): void
    {
        $this->expectException(OginiException::class);

        $errorResponse = ['error' => 'Index already exists', 'code' => 'INDEX_EXISTS'];
        $mock = new MockHandler([
            new Response(409, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $this->client->createIndex('existing-index');
    }

    public function testGetIndexSuccess(): void
    {
        $indexName = 'test-index';
        $responseData = [
            'name' => $indexName,
            'mappings' => [
                'properties' => [
                    'title' => ['type' => 'text'],
                    'description' => ['type' => 'text']
                ]
            ],
            'created_at' => '2023-01-01T00:00:00Z'
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->getIndex($indexName);

        $this->assertEquals($responseData, $result);
    }

    public function testGetIndexThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(OginiException::class);

        $errorResponse = ['error' => 'Index not found', 'code' => 'INDEX_NOT_FOUND'];
        $mock = new MockHandler([
            new Response(404, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $this->client->getIndex('non-existent-index');
    }

    public function testDeleteIndexSuccess(): void
    {
        $indexName = 'test-index';
        $responseData = ['success' => true, 'message' => 'Index deleted successfully'];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->deleteIndex($indexName);

        $this->assertEquals($responseData, $result);
    }

    public function testDeleteIndexThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(OginiException::class);

        $errorResponse = ['error' => 'Index not found', 'code' => 'INDEX_NOT_FOUND'];
        $mock = new MockHandler([
            new Response(404, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $this->client->deleteIndex('non-existent-index');
    }

    // Original Request Tests

    public function testSuccessfulRequest(): void
    {
        $responseData = ['status' => 'success', 'data' => ['id' => 1]];
        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->callProtectedMethod('request', ['GET', '/test']);

        $this->assertEquals($responseData, $result);
    }

    public function testRequestWithQueryParameters(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"success": true}')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->callProtectedMethod('request', [
            'GET',
            '/test',
            ['param1' => 'value1', 'param2' => 'value2']
        ]);

        $this->assertEquals(['success' => true], $result);
    }

    public function testRequestWithJsonData(): void
    {
        $mock = new MockHandler([
            new Response(201, [], '{"created": true}')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->callProtectedMethod('request', [
            'POST',
            '/test',
            ['name' => 'Test', 'email' => 'test@example.com']
        ]);

        $this->assertEquals(['created' => true], $result);
    }

    public function testRequestThrowsExceptionOnConnectError(): void
    {
        $this->expectException(OginiException::class);
        $this->expectExceptionMessage('Failed to connect to OginiSearch API');

        $mock = new MockHandler([
            new ConnectException(
                'Connection failed',
                new Request('GET', '/test')
            )
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $this->callProtectedMethod('request', ['GET', '/test']);
    }

    public function testRequestThrowsExceptionOnClientError(): void
    {
        $this->expectException(OginiException::class);

        $errorResponse = ['error' => 'Not found', 'code' => 'NOT_FOUND'];
        $mock = new MockHandler([
            new Response(404, [], json_encode($errorResponse))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        try {
            $this->callProtectedMethod('request', ['GET', '/test']);
        } catch (OginiException $e) {
            $this->assertEquals(404, $e->getCode());
            $this->assertEquals('Not found', $e->getMessage());
            $this->assertEquals($errorResponse, $e->getResponse());
            $this->assertEquals('NOT_FOUND', $e->getErrorCode());
            throw $e;
        }
    }

    public function testRequestThrowsExceptionOnServerError(): void
    {
        $this->expectException(OginiException::class);

        $mock = new MockHandler([
            new Response(500, [], '{"error": "Internal server error"}')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        try {
            $this->callProtectedMethod('request', ['GET', '/test']);
        } catch (OginiException $e) {
            $this->assertEquals(500, $e->getCode());
            $this->assertTrue($e->isServerError());
            throw $e;
        }
    }

    public function testRequestThrowsExceptionOnInvalidJson(): void
    {
        $this->expectException(OginiException::class);
        $this->expectExceptionMessage('Invalid JSON response from API');

        $mock = new MockHandler([
            new Response(200, [], 'invalid json')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $this->callProtectedMethod('request', ['GET', '/test']);
    }

    public function testRetryDeciderReturnsFalseWhenMaxRetriesExceeded(): void
    {
        $retryDecider = $this->callProtectedMethod('getRetryDecider');

        $result = $retryDecider(5, new Request('GET', '/test'), null, null);

        $this->assertFalse($result);
    }

    public function testRetryDeciderReturnsTrueForConnectException(): void
    {
        $retryDecider = $this->callProtectedMethod('getRetryDecider');
        $exception = new ConnectException('Connection failed', new Request('GET', '/test'));

        // Cast ConnectException to RequestException for the method signature
        $result = $retryDecider(1, new Request('GET', '/test'), null, $exception);

        $this->assertTrue($result);
    }

    public function testRetryDeciderReturnsTrueForServerError(): void
    {
        $retryDecider = $this->callProtectedMethod('getRetryDecider');
        $response = new Response(500);

        $result = $retryDecider(1, new Request('GET', '/test'), $response, null);

        $this->assertTrue($result);
    }

    public function testRetryDeciderReturnsTrueForRateLimitError(): void
    {
        $retryDecider = $this->callProtectedMethod('getRetryDecider');
        $response = new Response(429);

        $result = $retryDecider(1, new Request('GET', '/test'), $response, null);

        $this->assertTrue($result);
    }

    public function testRetryDelayCalculatesExponentialBackoff(): void
    {
        $retryDelay = $this->callProtectedMethod('getRetryDelay');

        $delay1 = $retryDelay(1);
        $delay2 = $retryDelay(2);

        // First retry should be around 100ms (plus jitter)
        $this->assertGreaterThanOrEqual(100000, $delay1); // microseconds
        $this->assertLessThanOrEqual(120000, $delay1); // with jitter

        // Second retry should be around 200ms (plus jitter)
        $this->assertGreaterThanOrEqual(200000, $delay2);
        $this->assertLessThanOrEqual(240000, $delay2);
    }

    // Document Management Tests

    public function testIndexDocumentSuccess(): void
    {
        $indexName = 'products';
        $document = ['title' => 'Test Product', 'price' => 99.99];
        $documentId = 'prod-123';
        $responseData = ['id' => $documentId, 'index' => $indexName, 'result' => 'created'];

        $mock = new MockHandler([
            new Response(201, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->indexDocument($indexName, $documentId, $document);

        $this->assertEquals($responseData, $result);
    }

    public function testIndexDocumentWithoutId(): void
    {
        $indexName = 'products';
        $document = ['title' => 'Test Product', 'price' => 99.99];
        $responseData = ['id' => 'auto-generated-id', 'index' => $indexName, 'result' => 'created'];

        $mock = new MockHandler([
            new Response(201, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->indexDocument($indexName, 'auto-generated-id', $document);

        $this->assertEquals($responseData, $result);
    }

    public function testGetDocumentSuccess(): void
    {
        $indexName = 'products';
        $documentId = 'prod-123';
        $responseData = [
            'id' => $documentId,
            'index' => $indexName,
            'found' => true,
            'source' => ['title' => 'Test Product', 'price' => 99.99]
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->getDocument($indexName, $documentId);

        $this->assertEquals($responseData, $result);
    }

    public function testUpdateDocumentSuccess(): void
    {
        $indexName = 'products';
        $documentId = 'prod-123';
        $document = ['title' => 'Updated Product', 'price' => 149.99];
        $responseData = ['id' => $documentId, 'index' => $indexName, 'result' => 'updated', 'version' => 2];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->updateDocument($indexName, $documentId, $document);

        $this->assertEquals($responseData, $result);
    }

    public function testDeleteDocumentSuccess(): void
    {
        $indexName = 'products';
        $documentId = 'prod-123';
        $responseData = ['id' => $documentId, 'index' => $indexName, 'result' => 'deleted'];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->deleteDocument($indexName, $documentId);

        $this->assertEquals($responseData, $result);
    }

    public function testBulkIndexDocumentsSuccess(): void
    {
        $indexName = 'products';
        $documents = [
            ['id' => 'prod-1', 'document' => ['title' => 'Product 1']],
            ['id' => 'prod-2', 'document' => ['title' => 'Product 2']]
        ];
        $responseData = [
            'items' => [
                ['id' => 'prod-1', 'status' => 201, 'success' => true],
                ['id' => 'prod-2', 'status' => 201, 'success' => true]
            ],
            'successCount' => 2,
            'errors' => false,
            'took' => 50
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->bulkIndexDocuments($indexName, $documents);

        $this->assertEquals($responseData, $result);
    }

    public function testListDocumentsSuccess(): void
    {
        $indexName = 'products';
        $responseData = [
            'total' => 100,
            'documents' => [
                ['id' => 'prod-1', 'source' => ['title' => 'Product 1']],
                ['id' => 'prod-2', 'source' => ['title' => 'Product 2']]
            ],
            'took' => 25
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->listDocuments($indexName, 10, 0);

        $this->assertEquals($responseData, $result);
    }

    // Search Tests

    public function testSearchSuccess(): void
    {
        $indexName = 'products';
        $searchQuery = [
            'query' => [
                'match' => [
                    'field' => 'title',
                    'value' => 'smartphone'
                ]
            ]
        ];
        $responseData = [
            'data' => [
                'total' => 5,
                'maxScore' => 0.95,
                'hits' => [
                    ['id' => 'prod-1', 'score' => 0.95, 'source' => ['title' => 'Smartphone Pro']]
                ]
            ],
            'took' => 15
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->search($indexName, 'smartphone', ['size' => 10, 'from' => 0, 'query' => $searchQuery['query']]);

        $this->assertEquals($responseData, $result);
    }

    public function testSuggestSuccess(): void
    {
        $indexName = 'products';
        $text = 'phon';
        $responseData = [
            'suggestions' => [
                ['text' => 'phone', 'score' => 0.9, 'freq' => 50],
                ['text' => 'phones', 'score' => 0.8, 'freq' => 30]
            ],
            'took' => 5
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->suggest($indexName, $text, 'title', 5);

        $this->assertEquals($responseData, $result);
    }

    // Additional Index Management Tests

    public function testListIndicesSuccess(): void
    {
        $responseData = [
            'indices' => [
                ['name' => 'products', 'status' => 'open', 'documentCount' => 100],
                ['name' => 'users', 'status' => 'open', 'documentCount' => 50]
            ],
            'total' => 2
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->listIndices();

        $this->assertEquals($responseData, $result);
    }

    public function testListIndicesWithStatusFilter(): void
    {
        $responseData = [
            'indices' => [
                ['name' => 'products', 'status' => 'open', 'documentCount' => 100]
            ],
            'total' => 1
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->listIndices('open');

        $this->assertEquals($responseData, $result);
    }

    public function testUpdateIndexSettingsSuccess(): void
    {
        $indexName = 'products';
        $settings = ['refreshInterval' => '2s'];
        $responseData = [
            'name' => $indexName,
            'settings' => $settings,
            'status' => 'open'
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($responseData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->updateIndexSettings($indexName, $settings);

        $this->assertEquals($responseData, $result);
    }

    /**
     * Call a protected method on the client.
     *
     * @param string $methodName
     * @param array $args
     * @return mixed
     */
    protected function callProtectedMethod(string $methodName, array $args = [])
    {
        $reflection = new ReflectionClass($this->client);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->client, $args);
    }
}
