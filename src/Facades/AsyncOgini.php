<?php

namespace OginiScoutDriver\Facades;

use Illuminate\Support\Facades\Facade;
use OginiScoutDriver\Client\AsyncOginiClient;

/**
 * @method static void setMaxConcurrentRequests(int $max)
 * @method static void setQueueEnabled(bool $enabled)
 * @method static \GuzzleHttp\Promise\PromiseInterface|string indexDocumentAsync(string $indexName, array $document, ?string $documentId = null, ?callable $successCallback = null, ?callable $errorCallback = null)
 * @method static \GuzzleHttp\Promise\PromiseInterface|string bulkIndexDocumentsAsync(string $indexName, array $documents, ?callable $successCallback = null, ?callable $errorCallback = null)
 * @method static \GuzzleHttp\Promise\PromiseInterface|string deleteDocumentAsync(string $indexName, string $documentId, ?callable $successCallback = null, ?callable $errorCallback = null)
 * @method static \GuzzleHttp\Promise\PromiseInterface|string searchAsync(string $indexName, array $searchQuery, ?int $size = null, ?int $from = null, ?callable $successCallback = null, ?callable $errorCallback = null)
 * @method static array waitForAll()
 * @method static int getPendingCount()
 * @method static void cancelAll()
 * @method static array executeParallel(array $requests, ?callable $progressCallback = null)
 */
class AsyncOgini extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return AsyncOginiClient::class;
    }
}
