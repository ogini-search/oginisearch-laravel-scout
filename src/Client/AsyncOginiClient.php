<?php

namespace OginiScoutDriver\Client;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;
use OginiScoutDriver\Exceptions\OginiException;
use OginiScoutDriver\Jobs\AsyncIndexJob;
use OginiScoutDriver\Jobs\AsyncDeleteJob;
use OginiScoutDriver\Jobs\AsyncSearchJob;
use Illuminate\Support\Facades\Queue;

class AsyncOginiClient extends OginiClient
{
    protected array $pendingPromises = [];
    protected int $maxConcurrentRequests = 10;
    protected bool $queueEnabled = false;

    /**
     * Set maximum concurrent requests.
     *
     * @param int $max
     * @return void
     */
    public function setMaxConcurrentRequests(int $max): void
    {
        $this->maxConcurrentRequests = $max;
    }

    /**
     * Enable/disable job queue integration.
     *
     * @param bool $enabled
     * @return void
     */
    public function setQueueEnabled(bool $enabled): void
    {
        $this->queueEnabled = $enabled;
    }

    /**
     * Index a document asynchronously.
     *
     * @param string $indexName
     * @param array $document
     * @param string|null $documentId
     * @param callable|null $successCallback
     * @param callable|null $errorCallback
     * @return PromiseInterface|string Job ID if queued
     * @throws OginiException
     */
    public function indexDocumentAsync(
        string $indexName,
        array $document,
        ?string $documentId = null,
        ?callable $successCallback = null,
        ?callable $errorCallback = null
    ) {
        if ($this->queueEnabled) {
            $jobId = uniqid('async_index_');
            Queue::push(new AsyncIndexJob($indexName, $document, $documentId, $jobId));
            return $jobId;
        }

        return $this->createAsyncRequest('POST', "/api/indices/{$indexName}/documents", [
            'document' => $document,
            'id' => $documentId,
        ], $successCallback, $errorCallback);
    }

    /**
     * Bulk index documents asynchronously.
     *
     * @param string $indexName
     * @param array $documents
     * @param callable|null $successCallback
     * @param callable|null $errorCallback
     * @return PromiseInterface|string Job ID if queued
     * @throws OginiException
     */
    public function bulkIndexDocumentsAsync(
        string $indexName,
        array $documents,
        ?callable $successCallback = null,
        ?callable $errorCallback = null
    ) {
        if ($this->queueEnabled) {
            $jobId = uniqid('async_bulk_index_');
            Queue::push(new AsyncIndexJob($indexName, $documents, null, $jobId, true));
            return $jobId;
        }

        return $this->createAsyncRequest('POST', "/api/indices/{$indexName}/documents/bulk", [
            'documents' => $documents,
        ], $successCallback, $errorCallback);
    }

    /**
     * Delete a document asynchronously.
     *
     * @param string $indexName
     * @param string $documentId
     * @param callable|null $successCallback
     * @param callable|null $errorCallback
     * @return PromiseInterface|string Job ID if queued
     * @throws OginiException
     */
    public function deleteDocumentAsync(
        string $indexName,
        string $documentId,
        ?callable $successCallback = null,
        ?callable $errorCallback = null
    ) {
        if ($this->queueEnabled) {
            $jobId = uniqid('async_delete_');
            Queue::push(new AsyncDeleteJob($indexName, $documentId, $jobId));
            return $jobId;
        }

        return $this->createAsyncRequest('DELETE', "/api/indices/{$indexName}/documents/{$documentId}", [], $successCallback, $errorCallback);
    }

    /**
     * Search asynchronously.
     *
     * @param string $indexName
     * @param array $searchQuery
     * @param int|null $size
     * @param int|null $from
     * @param callable|null $successCallback
     * @param callable|null $errorCallback
     * @return PromiseInterface|string Job ID if queued
     * @throws OginiException
     */
    public function searchAsync(
        string $indexName,
        array $searchQuery,
        ?int $size = null,
        ?int $from = null,
        ?callable $successCallback = null,
        ?callable $errorCallback = null
    ) {
        if ($this->queueEnabled) {
            $jobId = uniqid('async_search_');
            Queue::push(new AsyncSearchJob($indexName, $searchQuery, $size, $from, $jobId));
            return $jobId;
        }

        $payload = ['query' => $searchQuery];
        if ($size !== null) {
            $payload['size'] = $size;
        }
        if ($from !== null) {
            $payload['from'] = $from;
        }

        return $this->createAsyncRequest('POST', "/api/indices/{$indexName}/search", $payload, $successCallback, $errorCallback);
    }

    /**
     * Create an asynchronous HTTP request.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param callable|null $successCallback
     * @param callable|null $errorCallback
     * @return PromiseInterface
     */
    protected function createAsyncRequest(
        string $method,
        string $endpoint,
        array $data = [],
        ?callable $successCallback = null,
        ?callable $errorCallback = null
    ): PromiseInterface {
        $options = [];
        if (!empty($data)) {
            $options['json'] = $data;
        }

        $promise = $this->httpClient->requestAsync($method, $endpoint, $options);

        if ($successCallback || $errorCallback) {
            $promise = $promise->then(
                function ($response) use ($successCallback) {
                    $result = $this->handleResponse($response);
                    if ($successCallback) {
                        $successCallback($result);
                    }
                    return $result;
                },
                function ($exception) use ($errorCallback) {
                    if ($errorCallback) {
                        $errorCallback($exception);
                    }
                    throw $exception;
                }
            );
        }

        $this->pendingPromises[] = $promise;

        // Limit concurrent requests
        if (count($this->pendingPromises) >= $this->maxConcurrentRequests) {
            $this->waitForSomePromises();
        }

        return $promise;
    }

    /**
     * Wait for all pending promises to complete.
     *
     * @return array Results from all promises
     */
    public function waitForAll(): array
    {
        if (empty($this->pendingPromises)) {
            return [];
        }

        $results = Utils::settle($this->pendingPromises)->wait();
        $this->pendingPromises = [];

        return $results;
    }

    /**
     * Wait for some promises to complete to maintain concurrency limit.
     *
     * @return void
     */
    protected function waitForSomePromises(): void
    {
        $half = intval(count($this->pendingPromises) / 2);
        $toWait = array_slice($this->pendingPromises, 0, $half);

        Utils::settle($toWait)->wait();
        $this->pendingPromises = array_slice($this->pendingPromises, $half);
    }

    /**
     * Get the number of pending promises.
     *
     * @return int
     */
    public function getPendingCount(): int
    {
        return count($this->pendingPromises);
    }

    /**
     * Cancel all pending promises.
     *
     * @return void
     */
    public function cancelAll(): void
    {
        foreach ($this->pendingPromises as $promise) {
            $promise->cancel();
        }
        $this->pendingPromises = [];
    }

    /**
     * Execute multiple requests in parallel with automatic batching.
     *
     * @param array $requests Array of request configurations
     * @param callable|null $progressCallback Called with progress updates
     * @return array Results from all requests
     */
    public function executeParallel(array $requests, ?callable $progressCallback = null): array
    {
        $promises = [];
        $results = [];
        $completed = 0;
        $total = count($requests);

        foreach ($requests as $index => $request) {
            $promise = $this->createAsyncRequest(
                $request['method'],
                $request['endpoint'],
                $request['data'] ?? [],
                function ($result) use (&$results, $index, &$completed, $total, $progressCallback) {
                    $results[$index] = $result;
                    $completed++;
                    if ($progressCallback) {
                        $progressCallback($completed, $total, $result);
                    }
                },
                function ($error) use (&$results, $index, &$completed, $total, $progressCallback) {
                    $results[$index] = ['error' => $error->getMessage()];
                    $completed++;
                    if ($progressCallback) {
                        $progressCallback($completed, $total, null, $error);
                    }
                }
            );
            $promises[] = $promise;
        }

        Utils::settle($promises)->wait();
        $this->pendingPromises = [];

        // Sort results by original index
        ksort($results);
        return array_values($results);
    }
}
