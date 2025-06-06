<?php

namespace OginiScoutDriver\Listeners;

use OginiScoutDriver\Events\SearchCompleted;
use OginiScoutDriver\Events\SearchFailed;
use OginiScoutDriver\Events\DeletionCompleted;
use OginiScoutDriver\Events\DeletionFailed;
use Illuminate\Support\Facades\Log;

class LogSearchActivity
{
    /**
     * Handle search completed events.
     *
     * @param SearchCompleted $event
     * @return void
     */
    public function handleSearchCompleted(SearchCompleted $event): void
    {
        Log::info('Search completed', [
            'job_id' => $event->getJobId(),
            'index_name' => $event->getIndexName(),
            'query' => $event->getQuery(),
            'size' => $event->getSize(),
            'from' => $event->getFrom(),
            'result_count' => count($event->getResult()['hits'] ?? []),
        ]);
    }

    /**
     * Handle search failed events.
     *
     * @param SearchFailed $event
     * @return void
     */
    public function handleSearchFailed(SearchFailed $event): void
    {
        Log::error('Search failed', [
            'job_id' => $event->getJobId(),
            'index_name' => $event->getIndexName(),
            'query' => $event->getQuery(),
            'error' => $event->getError(),
            'exception' => $event->getException()?->getTraceAsString(),
        ]);
    }

    /**
     * Handle deletion completed events.
     *
     * @param DeletionCompleted $event
     * @return void
     */
    public function handleDeletionCompleted(DeletionCompleted $event): void
    {
        Log::info('Document deletion completed', [
            'job_id' => $event->getJobId(),
            'index_name' => $event->getIndexName(),
            'document_id' => $event->getDocumentId(),
            'result' => $event->getResult(),
        ]);
    }

    /**
     * Handle deletion failed events.
     *
     * @param DeletionFailed $event
     * @return void
     */
    public function handleDeletionFailed(DeletionFailed $event): void
    {
        Log::error('Document deletion failed', [
            'job_id' => $event->getJobId(),
            'index_name' => $event->getIndexName(),
            'document_id' => $event->getDocumentId(),
            'error' => $event->getError(),
            'exception' => $event->getException()?->getTraceAsString(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param \Illuminate\Events\Dispatcher $events
     * @return void
     */
    public function subscribe($events): void
    {
        $events->listen(
            SearchCompleted::class,
            [LogSearchActivity::class, 'handleSearchCompleted']
        );

        $events->listen(
            SearchFailed::class,
            [LogSearchActivity::class, 'handleSearchFailed']
        );

        $events->listen(
            DeletionCompleted::class,
            [LogSearchActivity::class, 'handleDeletionCompleted']
        );

        $events->listen(
            DeletionFailed::class,
            [LogSearchActivity::class, 'handleDeletionFailed']
        );
    }
}
