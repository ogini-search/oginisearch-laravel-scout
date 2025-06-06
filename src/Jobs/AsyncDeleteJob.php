<?php

namespace OginiScoutDriver\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Events\DeletionCompleted;
use OginiScoutDriver\Events\DeletionFailed;
use Illuminate\Support\Facades\Event;

class AsyncDeleteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $indexName;
    public string $documentId;
    public string $jobId;

    /**
     * Create a new job instance.
     *
     * @param string $indexName
     * @param string $documentId
     * @param string $jobId
     */
    public function __construct(string $indexName, string $documentId, string $jobId)
    {
        $this->indexName = $indexName;
        $this->documentId = $documentId;
        $this->jobId = $jobId;
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
            $result = $client->deleteDocument($this->indexName, $this->documentId);

            Event::dispatch(new DeletionCompleted([
                'job_id' => $this->jobId,
                'index_name' => $this->indexName,
                'document_id' => $this->documentId,
                'result' => $result,
            ]));
        } catch (\Exception $e) {
            Event::dispatch(new DeletionFailed([
                'job_id' => $this->jobId,
                'index_name' => $this->indexName,
                'document_id' => $this->documentId,
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
        Event::dispatch(new DeletionFailed([
            'job_id' => $this->jobId,
            'index_name' => $this->indexName,
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]));
    }
}
