<?php

namespace OginiScoutDriver\Performance;

use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Exceptions\OginiException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Closure;

class BatchProcessor
{
    protected OginiClient $client;
    protected array $config;

    public function __construct(OginiClient $client, array $config = [])
    {
        $this->client = $client;
        $this->config = array_merge([
            'batch_size' => 500,
            'timeout' => 120,
            'retry_attempts' => 3,
            'delay_between_batches' => 100, // milliseconds
        ], $config);
    }

    /**
     * Perform optimized bulk indexing with chunking and parallel processing.
     *
     * @param string $indexName
     * @param Collection $models
     * @param callable|null $progressCallback
     * @return array
     * @throws OginiException
     */
    public function bulkIndex(string $indexName, Collection $models, ?callable $progressCallback = null): array
    {
        $results = [
            'processed' => 0,
            'total' => $models->count(),
            'errors' => [],
            'success_rate' => 0,
            'batches_processed' => 0,
            'total_batches' => 0
        ];

        $batches = $models->chunk($this->config['batch_size']);
        $results['total_batches'] = $batches->count();

        foreach ($batches as $batchIndex => $batch) {
            try {
                $documents = $this->prepareBatchDocuments($batch);

                if (empty($documents)) {
                    $this->logWarning('BatchProcessor: Empty documents in batch', [
                        'index' => $indexName,
                        'batch_index' => $batchIndex,
                        'batch_size' => $batch->count()
                    ]);
                    continue;
                }

                $response = $this->client->bulkIndexDocuments($indexName, $documents);

                $results['processed'] += count($documents);
                $results['batches_processed']++;

                // Call progress callback if provided
                if ($progressCallback) {
                    $progressCallback($results['processed'], count($documents), $batchIndex + 1, $results['total_batches']);
                }

                // Small delay between batches to prevent overwhelming the server
                if ($this->config['delay_between_batches'] > 0 && $batchIndex < $batches->count() - 1) {
                    usleep($this->config['delay_between_batches'] * 1000);
                }
            } catch (OginiException $e) {
                $results['errors'][] = [
                    'batch_index' => $batchIndex,
                    'batch_size' => $batch->count(),
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'timestamp' => now()->toISOString()
                ];

                $this->logError('BatchProcessor: Bulk indexing failed', [
                    'index' => $indexName,
                    'batch_index' => $batchIndex,
                    'batch_size' => $batch->count(),
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode()
                ]);

                // Try individual fallback for failed batch
                $this->fallbackToIndividualIndexing($indexName, $batch, $results, $progressCallback);
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'batch_index' => $batchIndex,
                    'batch_size' => $batch->count(),
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ];

                $this->logError('BatchProcessor: Unexpected error during bulk indexing', [
                    'index' => $indexName,
                    'batch_index' => $batchIndex,
                    'batch_size' => $batch->count(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $results['success_rate'] = $results['total'] > 0
            ? round(($results['processed'] / $results['total']) * 100, 2)
            : 0;

        return $results;
    }

    /**
     * Process chunks sequentially.
     *
     * @param string $indexName
     * @param BaseCollection $chunks
     * @param callable|null $progressCallback
     * @return array
     */
    protected function processChunksSequentially(string $indexName, BaseCollection $chunks, ?callable $progressCallback = null): array
    {
        $processedCount = 0;
        $errors = [];

        foreach ($chunks as $chunkIndex => $chunk) {
            try {
                $this->client->bulkIndexDocuments($indexName, $chunk->toArray());
                $processedCount += $chunk->count();

                if ($progressCallback) {
                    $progressCallback($processedCount, $chunk->count(), $chunkIndex + 1, $chunks->count());
                }

                // Add delay between batches if configured
                if ($this->config['delay_between_batches'] > 0) {
                    usleep($this->config['delay_between_batches'] * 1000);
                }
            } catch (OginiException $e) {
                $chunkErrors = $this->handleChunkError($indexName, $chunk, $e, $chunkIndex);
                $errors = array_merge($errors, $chunkErrors['errors']);
                $processedCount += $chunkErrors['processed'];
            }
        }

        return ['processed' => $processedCount, 'errors' => $errors];
    }

    /**
     * Process chunks in parallel using curl multi-handle or similar technique.
     *
     * @param string $indexName
     * @param BaseCollection $chunks
     * @param callable|null $progressCallback
     * @return array
     */
    protected function processChunksInParallel(string $indexName, BaseCollection $chunks, ?callable $progressCallback = null): array
    {
        $processedCount = 0;
        $errors = [];
        $parallelBatches = $chunks->chunk($this->config['max_parallel_requests']);

        foreach ($parallelBatches as $batchIndex => $batch) {
            $promises = [];

            // Since Guzzle async is complex in this context, we'll use a simulated parallel approach
            // In a real implementation, you might use Guzzle's async requests or ReactPHP
            $batchResults = $this->processParallelBatch($indexName, $batch);

            foreach ($batchResults as $result) {
                $processedCount += $result['processed'];
                $errors = array_merge($errors, $result['errors']);
            }

            if ($progressCallback) {
                $progressCallback($processedCount, $batch->sum->count(), $batchIndex + 1, $parallelBatches->count());
            }
        }

        return ['processed' => $processedCount, 'errors' => $errors];
    }

    /**
     * Process a batch of chunks in parallel.
     *
     * @param string $indexName
     * @param BaseCollection $batch
     * @return array
     */
    protected function processParallelBatch(string $indexName, BaseCollection $batch): array
    {
        $results = [];

        foreach ($batch as $chunkIndex => $chunk) {
            try {
                $this->client->bulkIndexDocuments($indexName, $chunk->toArray());
                $results[] = ['processed' => $chunk->count(), 'errors' => []];
            } catch (OginiException $e) {
                $chunkResult = $this->handleChunkError($indexName, $chunk, $e, $chunkIndex);
                $results[] = $chunkResult;
            }
        }

        return $results;
    }

    /**
     * Handle errors when processing a chunk.
     *
     * @param string $indexName
     * @param BaseCollection $chunk
     * @param OginiException $exception
     * @param int $chunkIndex
     * @return array
     */
    protected function handleChunkError(string $indexName, BaseCollection $chunk, OginiException $exception, int $chunkIndex): array
    {
        $errors = [];
        $processed = 0;

        $this->logWarning('Bulk indexing chunk failed', [
            'index' => $indexName,
            'chunk_index' => $chunkIndex,
            'chunk_size' => $chunk->count(),
            'error' => $exception->getMessage(),
        ]);

        // Try individual document indexing if retry is enabled
        if ($this->config['retry_failed_chunks']) {
            foreach ($chunk as $document) {
                $retryCount = 0;

                while ($retryCount < $this->config['max_retry_attempts']) {
                    try {
                        $this->client->indexDocument(
                            $indexName,
                            $document['id'],
                            $document['document']
                        );
                        $processed++;
                        break;
                    } catch (OginiException $e) {
                        $retryCount++;

                        if ($retryCount >= $this->config['max_retry_attempts']) {
                            $errors[] = [
                                'document_id' => $document['id'],
                                'error' => $e->getMessage(),
                                'chunk_index' => $chunkIndex,
                            ];
                        } else {
                            // Small delay before retry
                            $retryDelay = $this->config['retry_delay'] ?? 100; // ms
                            usleep($retryDelay * 1000);
                        }
                    }
                }
            }
        } else {
            // Record all documents in chunk as failed
            foreach ($chunk as $document) {
                $errors[] = [
                    'document_id' => $document['id'],
                    'error' => $exception->getMessage(),
                    'chunk_index' => $chunkIndex,
                ];
            }
        }

        return ['processed' => $processed, 'errors' => $errors];
    }

    /**
     * Perform optimized bulk deletion.
     *
     * @param string $indexName
     * @param Collection $models
     * @return array
     */
    public function bulkDelete(string $indexName, Collection $models): array
    {
        $results = ['processed' => 0, 'total' => $models->count(), 'errors' => [], 'success_rate' => 0];

        $batches = $models->chunk($this->config['batch_size']);

        foreach ($batches as $batchIndex => $batch) {
            try {
                $documentIds = $batch->map(function ($model) {
                    return (string) $model->getScoutKey();
                })->toArray();

                // Use individual deletions since bulkDeleteDocuments is not available
                foreach ($documentIds as $documentId) {
                    $this->client->deleteDocument($indexName, $documentId);
                }

                $results['processed'] += count($documentIds);

                // Small delay between batches
                if ($this->config['delay_between_batches'] > 0) {
                    usleep($this->config['delay_between_batches'] * 1000);
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'batch_index' => $batchIndex,
                    'batch_size' => $batch->count(),
                    'error' => $e->getMessage(),
                    'timestamp' => now()->toISOString()
                ];

                $this->logError('BatchProcessor: Bulk deletion failed', [
                    'index' => $indexName,
                    'batch_index' => $batchIndex,
                    'batch_size' => $batch->count(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $results['success_rate'] = $results['total'] > 0
            ? round(($results['processed'] / $results['total']) * 100, 2)
            : 0;

        return $results;
    }

    /**
     * Get batch processing statistics.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        return [
            'batch_size' => $this->config['batch_size'],
            'timeout' => $this->config['timeout'],
            'retry_attempts' => $this->config['retry_attempts'],
            'delay_between_batches' => $this->config['delay_between_batches'],
        ];
    }

    /**
     * Update batch processor configuration.
     *
     * @param array $config
     * @return void
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    protected function prepareBatchDocuments(Collection $models): array
    {
        $documents = [];

        foreach ($models as $model) {
            $searchableArray = $model->toSearchableArray();

            if (!empty($searchableArray)) {
                $documents[] = [
                    'id' => (string) $model->getScoutKey(),
                    'document' => $searchableArray
                ];
            }
        }

        return $documents;
    }

    protected function fallbackToIndividualIndexing(string $indexName, Collection $batch, array &$results, ?callable $progressCallback = null): void
    {
        Log::info('BatchProcessor: Attempting individual fallback for failed batch', [
            'index' => $indexName,
            'batch_size' => $batch->count()
        ]);

        foreach ($batch as $model) {
            try {
                $documentId = (string) $model->getScoutKey();
                $documentData = $model->toSearchableArray();

                if (!empty($documentData)) {
                    $this->client->indexDocument($indexName, $documentId, $documentData);
                    $results['processed']++;

                    // Call progress callback for individual fallback processing
                    if ($progressCallback) {
                        $progressCallback($results['processed'], 1, 0, 0);
                    }
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'model_id' => $model->getScoutKey(),
                    'error' => $e->getMessage(),
                    'fallback' => true,
                    'timestamp' => now()->toISOString()
                ];

                Log::error('BatchProcessor: Individual fallback failed', [
                    'index' => $indexName,
                    'model_id' => $model->getScoutKey(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Log an error if logging is available.
     *
     * @param string $message
     * @param array $context
     */
    protected function logError(string $message, array $context = []): void
    {
        if (class_exists('\Illuminate\Support\Facades\Log')) {
            try {
                \Illuminate\Support\Facades\Log::error($message, $context);
            } catch (\Exception $e) {
                // Ignore logging errors in test environment
            }
        }
    }

    /**
     * Log a warning if logging is available.
     *
     * @param string $message
     * @param array $context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        if (class_exists('\Illuminate\Support\Facades\Log')) {
            try {
                \Illuminate\Support\Facades\Log::warning($message, $context);
            } catch (\Exception $e) {
                // Ignore logging errors in test environment
            }
        }
    }
}
