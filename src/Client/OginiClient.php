<?php

namespace OginiScoutDriver\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use OginiScoutDriver\Exceptions\OginiException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OginiClient
{
    protected Client $httpClient;
    protected string $baseUrl;
    protected string $apiKey;
    protected array $config;

    /**
     * Create a new OginiClient instance.
     *
     * @param string $baseUrl The base URL of the OginiSearch API
     * @param string $apiKey The API key for authentication
     * @param array $config Additional configuration options
     */
    public function __construct(string $baseUrl, string $apiKey, array $config = [])
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->config = array_merge([
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 100,
        ], $config);

        $this->setupHttpClient();
    }

    /**
     * Set up the Guzzle HTTP client with authentication and middleware.
     *
     * @return void
     */
    protected function setupHttpClient(): void
    {
        $stack = HandlerStack::create();

        // Add retry middleware
        $stack->push(Middleware::retry($this->getRetryDecider(), $this->getRetryDelay()));

        // Add request middleware for logging/debugging
        $stack->push(Middleware::mapRequest(function (RequestInterface $request) {
            return $request;
        }));

        $this->httpClient = new Client([
            'base_uri' => $this->baseUrl,
            'handler' => $stack,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'OginiScoutDriver/1.0',
            ],
            'timeout' => $this->config['timeout'],
            'connect_timeout' => 10,
            'http_errors' => false, // We'll handle HTTP errors manually
        ]);
    }

    /**
     * Create a new index.
     *
     * @param string $indexName The name of the index to create
     * @param array $configuration Index configuration including mappings
     * @return array Response data
     * @throws OginiException
     */
    public function createIndex(string $indexName, array $configuration = []): array
    {
        $payload = array_merge(['name' => $indexName], $configuration);
        return $this->request('POST', '/api/indices', $payload);
    }

    /**
     * Get information about an index.
     *
     * @param string $indexName The name of the index to retrieve
     * @return array Index information
     * @throws OginiException
     */
    public function getIndex(string $indexName): array
    {
        return $this->request('GET', "/api/indices/{$indexName}");
    }

    /**
     * Delete an index.
     *
     * @param string $indexName The name of the index to delete
     * @return array Response data
     * @throws OginiException
     */
    public function deleteIndex(string $indexName): array
    {
        return $this->request('DELETE', "/api/indices/{$indexName}");
    }

    /**
     * List all indices.
     *
     * @param string|null $status Optional status filter
     * @return array Response data
     * @throws OginiException
     */
    public function listIndices(?string $status = null): array
    {
        $params = [];
        if ($status !== null) {
            $params['status'] = $status;
        }
        return $this->request('GET', '/api/indices', $params);
    }

    /**
     * Update index settings.
     *
     * @param string $indexName The name of the index to update
     * @param array $settings New settings
     * @return array Response data
     * @throws OginiException
     */
    public function updateIndexSettings(string $indexName, array $settings): array
    {
        $payload = ['settings' => $settings];
        return $this->request('PUT', "/api/indices/{$indexName}/settings", $payload);
    }

    /**
     * Index a document.
     *
     * @param string $indexName The index to store the document in
     * @param string $documentId The document ID
     * @param array $document Document data
     * @return array Response data
     * @throws OginiException
     */
    public function indexDocument(string $indexName, string $documentId, array $document): array
    {
        $payload = [
            'id' => $documentId,
            'document' => $document
        ];
        return $this->request('POST', "/api/indices/{$indexName}/documents", $payload);
    }

    /**
     * Get a document by ID.
     *
     * @param string $indexName The index name
     * @param string $documentId The document ID
     * @return array Document data
     * @throws OginiException
     */
    public function getDocument(string $indexName, string $documentId): array
    {
        return $this->request('GET', "/api/indices/{$indexName}/documents/{$documentId}");
    }

    /**
     * Update a document.
     *
     * @param string $indexName The index name
     * @param string $documentId The document ID
     * @param array $document Updated document data
     * @return array Response data
     * @throws OginiException
     */
    public function updateDocument(string $indexName, string $documentId, array $document): array
    {
        $payload = ['document' => $document];
        return $this->request('PUT', "/api/indices/{$indexName}/documents/{$documentId}", $payload);
    }

    /**
     * Delete a document.
     *
     * @param string $indexName The index name
     * @param string $documentId The document ID
     * @return array Response data
     * @throws OginiException
     */
    public function deleteDocument(string $indexName, string $documentId): array
    {
        return $this->request('DELETE', "/api/indices/{$indexName}/documents/{$documentId}");
    }

    /**
     * Bulk index documents.
     *
     * @param string $indexName The index name
     * @param array $documents Array of documents to index
     * @return array Response data
     * @throws OginiException
     */
    public function bulkIndexDocuments(string $indexName, array $documents): array
    {
        $payload = ['documents' => $documents];
        return $this->request('POST', "/api/indices/{$indexName}/documents/_bulk", $payload);
    }

    /**
     * Delete documents by query.
     *
     * @param string $indexName The index name
     * @param array $query Query to match documents for deletion
     * @return array Response data
     * @throws OginiException
     */
    public function deleteByQuery(string $indexName, array $query): array
    {
        $payload = ['query' => $query];
        return $this->request('POST', "/api/indices/{$indexName}/documents/_delete_by_query", $payload);
    }

    /**
     * List documents in an index.
     *
     * @param string $indexName The index name
     * @param int $limit Number of documents to return
     * @param int $offset Starting offset
     * @param string|null $filter Optional filter
     * @return array Response data
     * @throws OginiException
     */
    public function listDocuments(string $indexName, int $limit = 10, int $offset = 0, ?string $filter = null): array
    {
        $params = [
            'limit' => $limit,
            'offset' => $offset,
        ];
        if ($filter !== null) {
            $params['filter'] = $filter;
        }
        return $this->request('GET', "/api/indices/{$indexName}/documents", $params);
    }

    /**
     * Search for documents.
     *
     * @param string $indexName The index to search in
     * @param string $query Search query string
     * @param array $options Search options (size, from, filters, etc.)
     * @return array Search results
     * @throws OginiException
     */
    public function search(string $indexName, string $query, array $options = []): array
    {
        // Build the search payload
        if (empty($query)) {
            $payload = [
                'query' => ['match_all' => []]
            ];
        } else {
            $payload = [
                'query' => [
                    'match' => [
                        'value' => $query
                    ]
                ]
            ];
        }

        // Add size if provided
        if (isset($options['size'])) {
            $payload['size'] = $options['size'];
        }

        // Add from (offset) if provided
        if (isset($options['from'])) {
            $payload['from'] = $options['from'];
        }

        // Add other options (filters, sort, etc.) but preserve query structure
        foreach ($options as $key => $value) {
            if (!in_array($key, ['size', 'from']) && $key !== 'query') {
                $payload[$key] = $value;
            }
        }

        // Allow options to override query if specifically provided
        if (isset($options['query'])) {
            $payload['query'] = $options['query'];
        }

        return $this->request('POST', "/api/indices/{$indexName}/_search", $payload);
    }

    /**
     * Get search suggestions.
     *
     * @param string $indexName The index name
     * @param string $text Text to get suggestions for
     * @param string|null $field Field to get suggestions from
     * @param int|null $size Number of suggestions to return
     * @return array Suggestions
     * @throws OginiException
     */
    public function suggest(string $indexName, string $text, ?string $field = null, ?int $size = null): array
    {
        $payload = ['text' => $text];
        if ($field !== null) {
            $payload['field'] = $field;
        }
        if ($size !== null) {
            $payload['size'] = $size;
        }
        return $this->request('POST', "/api/indices/{$indexName}/_search/_suggest", $payload);
    }

    /**
     * Make an HTTP request to the OginiSearch API.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (without base URL)
     * @param array $data Request data
     * @param array $options Additional Guzzle options
     * @return array Response data
     * @throws OginiException
     */
    protected function request(string $method, string $endpoint, array $data = [], array $options = []): array
    {
        try {
            $defaultOptions = [];

            // Handle request data based on method
            if (in_array(strtoupper($method), ['GET', 'DELETE'])) {
                if (!empty($data)) {
                    $defaultOptions['query'] = $data;
                }
            } else {
                if (!empty($data)) {
                    $defaultOptions['json'] = $data;
                }
            }

            $options = array_merge($defaultOptions, $options);

            $response = $this->httpClient->request($method, $endpoint, $options);

            return $this->handleResponse($response);
        } catch (ConnectException $e) {
            throw new OginiException(
                'Failed to connect to OginiSearch API: ' . $e->getMessage(),
                0,
                $e
            );
        } catch (RequestException $e) {
            return $this->handleRequestException($e);
        } catch (\Exception $e) {
            throw new OginiException(
                'Unexpected error occurred: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Handle the HTTP response.
     *
     * @param ResponseInterface $response
     * @return array
     * @throws OginiException
     */
    protected function handleResponse(ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $body = $response->getBody()->getContents();

        // Handle successful responses
        if ($statusCode >= 200 && $statusCode < 300) {
            // Handle empty responses (like 204 No Content)
            if (empty($body)) {
                return [];
            }

            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new OginiException(
                    'Invalid JSON response from API: ' . json_last_error_msg(),
                    $statusCode
                );
            }

            return $decoded ?? [];
        }

        // Handle error responses
        return $this->handleErrorResponse($statusCode, $body);
    }

    /**
     * Handle error responses from the API.
     *
     * @param int $statusCode
     * @param string $body
     * @return array This method always throws, never returns
     * @throws OginiException
     */
    protected function handleErrorResponse(int $statusCode, string $body): array
    {
        $errorData = json_decode($body, true);
        $message = 'OginiSearch API request failed';
        $code = null;

        if (is_array($errorData)) {
            $rawMessage = $errorData['message'] ?? $errorData['error'] ?? $message;

            // Ensure message is always a string
            if (is_array($rawMessage)) {
                $message = json_encode($rawMessage);
            } else {
                $message = (string) $rawMessage;
            }

            $code = $errorData['code'] ?? null;
        }

        throw new OginiException($message, $statusCode, null, $errorData, $code);
    }

    /**
     * Handle request exceptions.
     *
     * @param RequestException $e
     * @return array This method always throws, never returns
     * @throws OginiException
     */
    protected function handleRequestException(RequestException $e): array
    {
        $response = $e->getResponse();

        if ($response) {
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            return $this->handleErrorResponse($statusCode, $body);
        }

        throw new OginiException(
            'Request failed: ' . $e->getMessage(),
            $e->getCode(),
            $e
        );
    }

    /**
     * Get the retry decider function.
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
        ) {
            // Don't retry if we've exceeded the maximum number of retries
            if ($retries >= $this->config['retry_attempts']) {
                return false;
            }

            // Retry on connection exceptions
            if ($exception instanceof ConnectException) {
                return true;
            }

            // Retry on server errors (5xx)
            if ($response && $response->getStatusCode() >= 500) {
                return true;
            }

            // Retry on specific client errors that might be transient
            if ($response && in_array($response->getStatusCode(), [408, 429])) {
                return true;
            }

            return false;
        };
    }

    /**
     * Get the retry delay function.
     *
     * @return callable
     */
    protected function getRetryDelay(): callable
    {
        return function (int $numberOfRetries) {
            // Exponential backoff with jitter
            $delay = $this->config['retry_delay'] * pow(2, $numberOfRetries - 1);
            $jitter = rand(0, (int)($delay * 0.1));
            return ($delay + $jitter) * 1000; // Convert to microseconds
        };
    }

    /**
     * Set the HTTP client (useful for testing).
     *
     * @param Client $client
     * @return void
     */
    public function setHttpClient(Client $client): void
    {
        $this->httpClient = $client;
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the API key.
     *
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    /**
     * Get the configuration.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get advanced query suggestions based on partial input.
     *
     * @param string $indexName The index name
     * @param string $text Partial text input
     * @param array $options Suggestion options
     * @return array Suggestion results
     * @throws OginiException
     */
    public function getQuerySuggestions(string $indexName, string $text, array $options = []): array
    {
        $payload = array_merge([
            'text' => $text,
            'size' => 10,
            'field' => null,
            'highlight' => true,
            'fuzzy' => true,
        ], $options);

        return $this->request('POST', "/api/indices/{$indexName}/suggestions", $payload);
    }

    /**
     * Get autocomplete suggestions for search queries.
     *
     * @param string $indexName The index name
     * @param string $prefix Query prefix
     * @param array $options Autocomplete options
     * @return array Autocomplete results
     * @throws OginiException
     */
    public function getAutocompleteSuggestions(string $indexName, string $prefix, array $options = []): array
    {
        $payload = array_merge([
            'prefix' => $prefix,
            'size' => 10,
            'completion_field' => 'suggest',
            'contexts' => [],
        ], $options);

        return $this->request('POST', "/api/indices/{$indexName}/autocomplete", $payload);
    }

    /**
     * Add synonyms to an index.
     *
     * @param string $indexName The index name
     * @param array $synonyms Array of synonym groups
     * @return array Response data
     * @throws OginiException
     */
    public function addSynonyms(string $indexName, array $synonyms): array
    {
        $payload = ['synonyms' => $synonyms];
        return $this->request('POST', "/api/indices/{$indexName}/synonyms", $payload);
    }

    /**
     * Get all synonyms for an index.
     *
     * @param string $indexName The index name
     * @return array Synonym data
     * @throws OginiException
     */
    public function getSynonyms(string $indexName): array
    {
        return $this->request('GET', "/api/indices/{$indexName}/synonyms");
    }

    /**
     * Update synonyms for an index.
     *
     * @param string $indexName The index name
     * @param array $synonyms Updated synonym groups
     * @return array Response data
     * @throws OginiException
     */
    public function updateSynonyms(string $indexName, array $synonyms): array
    {
        $payload = ['synonyms' => $synonyms];
        return $this->request('PUT', "/api/indices/{$indexName}/synonyms", $payload);
    }

    /**
     * Delete synonyms from an index.
     *
     * @param string $indexName The index name
     * @param array $synonymGroups Optional specific synonym groups to delete
     * @return array Response data
     * @throws OginiException
     */
    public function deleteSynonyms(string $indexName, array $synonymGroups = []): array
    {
        $payload = empty($synonymGroups) ? [] : ['synonym_groups' => $synonymGroups];
        return $this->request('DELETE', "/api/indices/{$indexName}/synonyms", $payload);
    }

    /**
     * Configure stopwords for an index.
     *
     * @param string $indexName The index name
     * @param array $stopwords Array of stopwords
     * @param string $language Language code (optional)
     * @return array Response data
     * @throws OginiException
     */
    public function configureStopwords(string $indexName, array $stopwords, ?string $language = null): array
    {
        $payload = [
            'stopwords' => $stopwords,
            'language' => $language ?? 'en',
        ];
        return $this->request('POST', "/api/indices/{$indexName}/stopwords", $payload);
    }

    /**
     * Get stopwords configuration for an index.
     *
     * @param string $indexName The index name
     * @return array Stopwords data
     * @throws OginiException
     */
    public function getStopwords(string $indexName): array
    {
        return $this->request('GET', "/api/indices/{$indexName}/stopwords");
    }

    /**
     * Update stopwords for an index.
     *
     * @param string $indexName The index name
     * @param array $stopwords Updated stopwords array
     * @param string $language Language code (optional)
     * @return array Response data
     * @throws OginiException
     */
    public function updateStopwords(string $indexName, array $stopwords, ?string $language = null): array
    {
        $payload = [
            'stopwords' => $stopwords,
            'language' => $language ?? 'en',
        ];
        return $this->request('PUT', "/api/indices/{$indexName}/stopwords", $payload);
    }

    /**
     * Reset stopwords to default for an index.
     *
     * @param string $indexName The index name
     * @param string $language Language code (optional)
     * @return array Response data
     * @throws OginiException
     */
    public function resetStopwords(string $indexName, ?string $language = null): array
    {
        $payload = ['language' => $language ?? 'en'];
        return $this->request('DELETE', "/api/indices/{$indexName}/stopwords", $payload);
    }

    /**
     * Perform a basic health check on the OginiSearch API.
     * 
     * This method tests connectivity, authentication, and basic API functionality.
     *
     * @param bool $detailed Whether to perform detailed health checks
     * @return array Health check results
     * @throws OginiException
     */
    public function healthCheck(bool $detailed = false): array
    {
        $healthData = [
            'status' => 'unknown',
            'api_accessible' => false,
            'authenticated' => false,
            'response_time_ms' => null,
            'version' => null,
            'timestamp' => now()->toISOString(),
            'details' => [],
        ];

        $startTime = microtime(true);

        try {
            // Basic connectivity test - try to get server status/info
            $response = $this->request('GET', '/api/health');

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            $healthData['response_time_ms'] = $responseTime;
            $healthData['api_accessible'] = true;

            // Check if we got a valid response
            if (isset($response['status'])) {
                $healthData['status'] = $response['status'];
                $healthData['authenticated'] = true;

                if (isset($response['version'])) {
                    $healthData['version'] = $response['version'];
                }

                // Add server details if available
                if (isset($response['server_info'])) {
                    $healthData['details']['server'] = $response['server_info'];
                }
            }

            // Perform detailed checks if requested
            if ($detailed) {
                $healthData['details'] = array_merge($healthData['details'], $this->performDetailedHealthChecks());
            }
        } catch (OginiException $e) {
            $healthData['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);

            // Check if this is a connection error (wrapped ConnectException)
            $previous = $e->getPrevious();
            if ($previous instanceof ConnectException) {
                $healthData['status'] = 'unreachable';
                $healthData['details']['error'] = 'Cannot connect to OginiSearch API';
                $healthData['details']['message'] = $e->getMessage();
            } elseif ($e->getCode() === 401) {
                // Authentication failed
                $healthData['api_accessible'] = true;
                $healthData['status'] = 'authentication_failed';
                $healthData['details']['error'] = 'Invalid API key or authentication failed';
            } elseif ($e->getCode() >= 500) {
                // Server error
                $healthData['api_accessible'] = true;
                $healthData['status'] = 'server_error';
                $healthData['details']['error'] = 'Server error: ' . $e->getMessage();
            } elseif ($e->getCode() >= 400) {
                // Client error
                $healthData['api_accessible'] = true;
                $healthData['status'] = 'client_error';
                $healthData['details']['error'] = 'Client error: ' . $e->getMessage();
            } else {
                // General error
                $healthData['status'] = 'error';
                $healthData['details']['error'] = $e->getMessage();
            }
        } catch (\Exception $e) {
            $healthData['status'] = 'error';
            $healthData['details']['error'] = 'Unexpected error: ' . $e->getMessage();
            $healthData['response_time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        }

        return $healthData;
    }

    /**
     * Perform detailed health checks.
     *
     * @return array Detailed health check results
     */
    protected function performDetailedHealthChecks(): array
    {
        $details = [];

        try {
            // Test index listing
            $listStart = microtime(true);
            $indices = $this->listIndices();
            $details['index_listing'] = [
                'accessible' => true,
                'response_time_ms' => round((microtime(true) - $listStart) * 1000, 2),
                'index_count' => count($indices['data'] ?? []),
            ];
        } catch (\Exception $e) {
            $details['index_listing'] = [
                'accessible' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Test search functionality with a simple test
        try {
            $searchStart = microtime(true);
            // Try a basic search on any available index
            $searchResults = $this->search('*', '', ['query' => ['match_all' => []], 'size' => 1]);
            $details['search_functionality'] = [
                'accessible' => true,
                'response_time_ms' => round((microtime(true) - $searchStart) * 1000, 2),
            ];
        } catch (\Exception $e) {
            $details['search_functionality'] = [
                'accessible' => false,
                'error' => $e->getMessage(),
            ];
        }

        // Add configuration details
        $details['configuration'] = [
            'base_url' => $this->baseUrl,
            'timeout' => $this->config['timeout'],
            'retry_attempts' => $this->config['retry_attempts'],
        ];

        return $details;
    }

    /**
     * Quick health check - simplified version for frequent monitoring.
     *
     * @return bool True if the API is accessible and authenticated
     */
    public function isHealthy(): bool
    {
        try {
            $health = $this->healthCheck(false);
            return $health['api_accessible'] && $health['authenticated'] && $health['status'] !== 'error';
        } catch (\Exception $e) {
            return false;
        }
    }
}
