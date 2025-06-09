<?php

namespace OginiScoutDriver\Tests\Unit\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use OginiScoutDriver\Client\OginiClient;
use PHPUnit\Framework\TestCase;

class OginiClientHealthCheckTest extends TestCase
{
    protected OginiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new OginiClient('https://api.example.com', 'test-api-key');
    }

    /** @test */
    public function it_performs_basic_health_check_successfully()
    {
        // Mock successful health check response
        $healthResponse = new Response(200, [], json_encode([
            'status' => 'healthy',
            'version' => '1.0.0',
            'server_info' => [
                'uptime' => '5 days',
                'memory_usage' => '512MB',
            ],
        ]));

        $mock = new MockHandler([$healthResponse]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->healthCheck();

        $this->assertTrue($result['api_accessible']);
        $this->assertTrue($result['authenticated']);
        $this->assertEquals('healthy', $result['status']);
        $this->assertEquals('1.0.0', $result['version']);
        $this->assertIsFloat($result['response_time_ms']);
        $this->assertArrayHasKey('server', $result['details']);
    }

    /** @test */
    public function it_handles_connection_errors_in_health_check()
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('GET', '/health'))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->healthCheck();

        $this->assertFalse($result['api_accessible']);
        $this->assertFalse($result['authenticated']);
        $this->assertEquals('unreachable', $result['status']);
        $this->assertArrayHasKey('error', $result['details']);
        $this->assertEquals('Cannot connect to OginiSearch API', $result['details']['error']);
    }

    /** @test */
    public function it_handles_authentication_errors_in_health_check()
    {
        $response = new Response(401, [], '{"error": "Unauthorized"}');
        $exception = new ClientException('Unauthorized', new Request('GET', '/health'), $response);

        $mock = new MockHandler([$exception]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->healthCheck();

        $this->assertTrue($result['api_accessible']);
        $this->assertFalse($result['authenticated']);
        $this->assertEquals('authentication_failed', $result['status']);
        $this->assertArrayHasKey('error', $result['details']);
        $this->assertEquals('Invalid API key or authentication failed', $result['details']['error']);
    }

    /** @test */
    public function it_handles_server_errors_in_health_check()
    {
        $response = new Response(500, [], '{"error": "Internal Server Error"}');
        $exception = new ServerException('Server Error', new Request('GET', '/health'), $response);

        $mock = new MockHandler([$exception]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->healthCheck();

        $this->assertTrue($result['api_accessible']);
        $this->assertFalse($result['authenticated']);
        $this->assertEquals('server_error', $result['status']);
        $this->assertArrayHasKey('error', $result['details']);
        $this->assertStringStartsWith('Server error:', $result['details']['error']);
    }

    /** @test */
    public function it_performs_detailed_health_check()
    {
        // Mock health endpoint response
        $healthResponse = new Response(200, [], json_encode([
            'status' => 'healthy',
            'version' => '1.0.0',
        ]));

        // Mock list indices response
        $listResponse = new Response(200, [], json_encode([
            'data' => [
                ['name' => 'test_index_1'],
                ['name' => 'test_index_2'],
            ],
        ]));

        // Mock search response
        $searchResponse = new Response(200, [], json_encode([
            'data' => [
                'hits' => [],
                'total' => 0,
            ],
        ]));

        $mock = new MockHandler([$healthResponse, $listResponse, $searchResponse]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->healthCheck(true);

        $this->assertTrue($result['api_accessible']);
        $this->assertTrue($result['authenticated']);
        $this->assertEquals('healthy', $result['status']);

        // Check detailed results
        $this->assertArrayHasKey('index_listing', $result['details']);
        $this->assertTrue($result['details']['index_listing']['accessible']);
        $this->assertEquals(2, $result['details']['index_listing']['index_count']);

        $this->assertArrayHasKey('search_functionality', $result['details']);
        $this->assertTrue($result['details']['search_functionality']['accessible']);

        $this->assertArrayHasKey('configuration', $result['details']);
        $this->assertArrayHasKey('base_url', $result['details']['configuration']);
    }

    /** @test */
    public function is_healthy_returns_true_for_healthy_api()
    {
        $healthResponse = new Response(200, [], json_encode([
            'status' => 'healthy',
        ]));

        $mock = new MockHandler([$healthResponse]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $this->assertTrue($this->client->isHealthy());
    }

    /** @test */
    public function is_healthy_returns_false_for_unhealthy_api()
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('GET', '/health'))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $this->assertFalse($this->client->isHealthy());
    }

    /** @test */
    public function health_check_includes_timestamp()
    {
        $healthResponse = new Response(200, [], json_encode([
            'status' => 'healthy',
        ]));

        $mock = new MockHandler([$healthResponse]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->healthCheck();

        $this->assertArrayHasKey('timestamp', $result);
        $this->assertIsString($result['timestamp']);
    }

    /** @test */
    public function health_check_measures_response_time()
    {
        $healthResponse = new Response(200, [], json_encode([
            'status' => 'healthy',
        ]));

        $mock = new MockHandler([$healthResponse]);
        $handlerStack = HandlerStack::create($mock);
        $httpClient = new Client(['handler' => $handlerStack]);
        $this->client->setHttpClient($httpClient);

        $result = $this->client->healthCheck();

        $this->assertArrayHasKey('response_time_ms', $result);
        $this->assertIsFloat($result['response_time_ms']);
        $this->assertGreaterThan(0, $result['response_time_ms']);
    }
}
