<?php

namespace OginiScoutDriver\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OginiScoutDriver\Client\OginiClient;
use OginiScoutDriver\Events\SearchCompleted;
use OginiScoutDriver\Events\SearchFailed;
use Illuminate\Support\Facades\Event;

class AsyncSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $indexName;
    public array $searchQuery;
    public ?int $size;
    public ?int $from;
    public string $jobId;

    /**
     * Create a new job instance.
     *
     * @param string $indexName
     * @param array $searchQuery
     * @param int|null $size
     * @param int|null $from
     * @param string $jobId
     */
    public function __construct(
        string $indexName,
        array $searchQuery,
        ?int $size,
        ?int $from,
        string $jobId
    ) {
        $this->indexName = $indexName;
        $this->searchQuery = $searchQuery;
        $this->size = $size;
        $this->from = $from;
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
            // Build options array for the search call
            $options = [];
            if ($this->size !== null) {
                $options['size'] = $this->size;
            }
            if ($this->from !== null) {
                $options['from'] = $this->from;
            }

            $result = $client->search($this->indexName, '', array_merge($this->searchQuery, $options));

            Event::dispatch(new SearchCompleted([
                'job_id' => $this->jobId,
                'index_name' => $this->indexName,
                'query' => $this->searchQuery,
                'size' => $this->size,
                'from' => $this->from,
                'result' => $result,
            ]));
        } catch (\Exception $e) {
            Event::dispatch(new SearchFailed([
                'job_id' => $this->jobId,
                'index_name' => $this->indexName,
                'query' => $this->searchQuery,
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
        Event::dispatch(new SearchFailed([
            'job_id' => $this->jobId,
            'index_name' => $this->indexName,
            'query' => $this->searchQuery,
            'error' => $exception->getMessage(),
            'exception' => $exception,
        ]));
    }
}
