<?php

namespace OginiScoutDriver\Performance;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise\PromiseInterface;
use OginiScoutDriver\Exceptions\OginiException;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ConnectionPool
{
    protected array $connections = [];
    protected array $config;
    protected int $currentConnectionIndex = 0;
    protected array $connectionStats = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'pool_size' => 5,
            'max_connections_per_pool' => 10,
            'connection_timeout' => 10,
            'request_timeout' => 30,
            'keep_alive_timeout' => 60,
            'max_idle_time' => 300, // 5 minutes
            'enable_connection_reuse' => true,
            'enable_keep_alive' => true,
            'enable_http2' => false,
            'max_concurrent_requests' => 10,
            'base_url' => 'http://localhost:3000',
            'api_key' => '',
            'user_agent' => 'OginiScoutDriver/1.0',
        ], $config);

        $this->initializeConnectionPool();
    }

    /**
     * Initialize the connection pool with configured number of connections.
     *
     * @return void
     */
    protected function initializeConnectionPool(): void
    {
        for ($i = 0; $i < $this->config['pool_size']; $i++) {
            $this->connections[$i] = $this->createConnection($i);
            $this->connectionStats[$i] = [
                'created_at' => Carbon::now(),
                'last_used' => null,
                'request_count' => 0,
                'error_count' => 0,
                'is_healthy' => true,
            ];
        }
    }

    /**
     * Create a new HTTP client connection with optimized settings.
     *
     * @param int $connectionId
     * @return Client
     */
    protected function createConnection(int $connectionId): Client
    {
        $stack = HandlerStack::create($this->createHandler());

        // Add keep-alive middleware
        if ($this->config['enable_keep_alive']) {
            $stack->push($this->createKeepAliveMiddleware());
        }

        // Add connection monitoring middleware
        $stack->push($this->createConnectionMonitoringMiddleware($connectionId));

        // Add retry middleware
        $stack->push(Middleware::retry($this->getRetryDecider(), $this->getRetryDelay()));

        return new Client([
            'base_uri' => $this->config['base_url'],
            'handler' => $stack,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->config['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $this->config['user_agent'],
                'Connection' => $this->config['enable_keep_alive'] ? 'keep-alive' : 'close',
            ],
            'timeout' => $this->config['request_timeout'],
            'connect_timeout' => $this->config['connection_timeout'],
            'http_errors' => false,
            'curl' => array_filter([
                CURLOPT_TCP_KEEPALIVE => $this->config['enable_keep_alive'] ? 1 : 0,
                CURLOPT_TCP_KEEPIDLE => $this->config['keep_alive_timeout'],
                CURLOPT_TCP_KEEPINTVL => 30,
                defined('CURLOPT_TCP_KEEPCNT') ? CURLOPT_TCP_KEEPCNT : null => defined('CURLOPT_TCP_KEEPCNT') ? 3 : null,
                CURLOPT_MAXCONNECTS => $this->config['max_connections_per_pool'],
            ], function ($value, $key) {
                return $key !== null && $value !== null;
            }, ARRAY_FILTER_USE_BOTH),
            'version' => $this->config['enable_http2'] ? '2.0' : '1.1',
        ]);
    }

    /**
     * Create the HTTP handler for the connection.
     *
     * @return CurlMultiHandler
     */
    protected function createHandler(): CurlMultiHandler
    {
        return new CurlMultiHandler([
            'max_handles' => $this->config['max_concurrent_requests'],
        ]);
    }

    /**
     * Get a connection from the pool using round-robin selection.
     *
     * @return Client
     */
    public function getConnection(): Client
    {
        if (!$this->config['enable_connection_reuse']) {
            return $this->createConnection(-1);
        }

        $connectionId = $this->currentConnectionIndex;
        $this->currentConnectionIndex = ($this->currentConnectionIndex + 1) % $this->config['pool_size'];

        $this->updateConnectionStats($connectionId);
        return $this->connections[$connectionId];
    }

    /**
     * Update connection statistics after use.
     *
     * @param int $connectionId
     * @return void
     */
    protected function updateConnectionStats(int $connectionId): void
    {
        if (isset($this->connectionStats[$connectionId])) {
            $this->connectionStats[$connectionId]['last_used'] = Carbon::now();
            $this->connectionStats[$connectionId]['request_count']++;
        }
    }

    /**
     * Mark a connection as unhealthy.
     *
     * @param int $connectionId
     * @param string $reason
     * @return void
     */
    protected function markConnectionUnhealthy(int $connectionId, string $reason): void
    {
        if (isset($this->connectionStats[$connectionId])) {
            $this->connectionStats[$connectionId]['is_healthy'] = false;
            $this->connectionStats[$connectionId]['error_count']++;

            Log::warning('Connection marked as unhealthy', [
                'connection_id' => $connectionId,
                'reason' => $reason,
            ]);
        }
    }

    /**
     * Clean up idle connections and replace them with fresh ones.
     *
     * @return void
     */
    protected function cleanupIdleConnections(): void
    {
        $now = Carbon::now();

        foreach ($this->connectionStats as $connectionId => $stats) {
            if ($stats['last_used'] && $now->diffInSeconds($stats['last_used']) > $this->config['max_idle_time']) {
                $this->replaceConnection($connectionId);
            }
        }
    }

    /**
     * Replace a connection with a fresh one.
     *
     * @param int $connectionId
     * @return void
     */
    protected function replaceConnection(int $connectionId): void
    {
        $this->connections[$connectionId] = $this->createConnection($connectionId);
        $this->connectionStats[$connectionId] = [
            'created_at' => Carbon::now(),
            'last_used' => null,
            'request_count' => 0,
            'error_count' => 0,
            'is_healthy' => true,
        ];

        Log::debug('Connection replaced', ['connection_id' => $connectionId]);
    }

    /**
     * Create keep-alive middleware.
     *
     * @return callable
     */
    protected function createKeepAliveMiddleware(): callable
    {
        return Middleware::mapRequest(function (RequestInterface $request) {
            if ($this->config['enable_keep_alive']) {
                return $request->withHeader('Connection', 'keep-alive')
                    ->withHeader('Keep-Alive', 'timeout=' . $this->config['keep_alive_timeout']);
            }

            return $request->withHeader('Connection', 'close');
        });
    }

    /**
     * Create connection monitoring middleware.
     *
     * @param int $connectionId
     * @return callable
     */
    protected function createConnectionMonitoringMiddleware(int $connectionId): callable
    {
        return function (callable $handler) use ($connectionId) {
            return function (RequestInterface $request, array $options) use ($handler, $connectionId) {
                $promise = $handler($request, $options);

                return $promise->then(
                    function (ResponseInterface $response) use ($connectionId) {
                        // Connection successful
                        return $response;
                    },
                    function (\Exception $reason) use ($connectionId) {
                        // Connection failed
                        if ($connectionId >= 0) {
                            $this->markConnectionUnhealthy($connectionId, 'request_failed');
                        }
                        throw $reason;
                    }
                );
            };
        };
    }

    /**
     * Get retry decider for failed requests.
     *
     * @return callable
     */
    protected function getRetryDecider(): callable
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?\Exception $exception = null
        ): bool {
            if ($retries >= 3) {
                return false;
            }

            if ($exception !== null) {
                return true;
            }

            if ($response && $response->getStatusCode() >= 500) {
                return true;
            }

            return false;
        };
    }

    /**
     * Get retry delay function.
     *
     * @return callable
     */
    protected function getRetryDelay(): callable
    {
        return function (int $numberOfRetries): int {
            return 1000 * pow(2, $numberOfRetries); // Exponential backoff
        };
    }

    /**
     * Send multiple requests concurrently using the connection pool.
     *
     * @param array $requests
     * @param int $concurrency
     * @return array
     */
    public function sendConcurrentRequests(array $requests, ?int $concurrency = null): array
    {
        $concurrency = $concurrency ?? $this->config['max_concurrent_requests'];
        $responses = [];

        $pool = new Pool($this->getConnection(), $requests, [
            'concurrency' => $concurrency,
            'fulfilled' => function (ResponseInterface $response, int $index) use (&$responses) {
                $responses[$index] = $response;
            },
            'rejected' => function (\Exception $reason, int $index) use (&$responses) {
                $responses[$index] = $reason;
            },
        ]);

        $promise = $pool->promise();
        $promise->wait();

        return $responses;
    }

    /**
     * Get connection pool statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $totalRequests = array_sum(array_column($this->connectionStats, 'request_count'));
        $totalErrors = array_sum(array_column($this->connectionStats, 'error_count'));
        $healthyConnections = count(array_filter($this->connectionStats, fn($stats) => $stats['is_healthy']));

        return [
            'pool_size' => $this->config['pool_size'],
            'healthy_connections' => $healthyConnections,
            'total_requests' => $totalRequests,
            'total_errors' => $totalErrors,
            'error_rate' => $totalRequests > 0 ? ($totalErrors / $totalRequests) * 100 : 0,
            'connection_reuse_enabled' => $this->config['enable_connection_reuse'],
            'keep_alive_enabled' => $this->config['enable_keep_alive'],
            'connections' => $this->connectionStats,
        ];
    }

    /**
     * Health check for all connections in the pool.
     *
     * @return array
     */
    public function healthCheck(): array
    {
        $results = [];

        foreach ($this->connections as $connectionId => $connection) {
            try {
                $response = $connection->get('/health', ['timeout' => 5]);
                $results[$connectionId] = [
                    'healthy' => $response->getStatusCode() === 200,
                    'status_code' => $response->getStatusCode(),
                    'response_time' => 0, // Would need timing implementation
                ];
            } catch (\Exception $e) {
                $results[$connectionId] = [
                    'healthy' => false,
                    'error' => $e->getMessage(),
                ];
                $this->markConnectionUnhealthy($connectionId, 'health_check_failed');
            }
        }

        return $results;
    }

    /**
     * Shutdown the connection pool and clean up resources.
     *
     * @return void
     */
    public function shutdown(): void
    {
        foreach ($this->connections as $connectionId => $connection) {
            // Gracefully close connections
            unset($this->connections[$connectionId]);
        }

        $this->connections = [];
        $this->connectionStats = [];

        Log::info('Connection pool shutdown completed');
    }

    /**
     * Update pool configuration.
     *
     * @param array $config
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);

        // Reinitialize pool if critical settings changed
        $criticalSettings = ['pool_size', 'base_url', 'api_key'];
        if (array_intersect_key($config, array_flip($criticalSettings))) {
            $this->shutdown();
            $this->initializeConnectionPool();
        }
    }
}
