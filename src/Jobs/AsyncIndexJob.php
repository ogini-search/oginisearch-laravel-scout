<?php

namespace OginiScoutDriver\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Events\IndexingCompleted;
use OginiScoutDriver\Events\IndexingFailed;
use Illuminate\Support\Facades\Event;

class AsyncIndexJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $indexName;
    public array $document;
    public ?string $documentId;
    public string $jobId;
    public bool $isBulk;

    /**
     * Create a new job instance.
     *
     * @param string $indexName
     * @param array $document
     * @param string|null $documentId
     * @param string $jobId
     * @param bool $isBulk
     */
    public function __construct(
        string $indexName,
        array $document,
        ?string $documentId,
        string $jobId,
        bool $isBulk = false
    ) {
        $this->indexName = $indexName;
        $this->document = $document;
        $this->documentId = $documentId;
        $this->jobId = $jobId;
        $this->isBulk = $isBulk;
    }

    /**
     * Execute the job.
     *
     * @param OginiClient $client
     * @return void
     */
    public function handle(OginiClient $client): void
    {
        try {
            if ($this->isBulk) {
                $result = $client->bulkIndexDocuments($this->indexName, $this->document);
            } else {
                $result = $client->indexDocument($this->indexName, $this->document, $this->documentId);
            }

            Event::dispatch(new IndexingCompleted([
                'job_id' => $this->jobId,
                'index_name' => $this->indexName,
                'document_id' => $this->documentId,
                'is_bulk' => $this->isBulk,
                'result' => $result,
            ]));
        } catch (\Exception $e) {
            Event::dispatch(new IndexingFailed([
                'job_id' => $this->jobId,
                'index_name' => $this->indexName,
                'document_id' => $this->documentId,
                'is_bulk' => $this->isBulk,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]));

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Exception $exception
     * @return void
     */
    public function failed(\Exception $exception): void
    {
        Event::dispatch(new IndexingFailed([
            'job_id' => $this->jobId,
            'index_name' => $this->indexName,
            'document_id' => $this->documentId,
            'is_bulk' => $this->isBulk,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]));
    }
}
