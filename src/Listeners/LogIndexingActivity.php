<?php

namespace OginiScoutDriver\Listeners;

use OginiScoutDriver\Events\IndexingCompleted;
use OginiScoutDriver\Events\IndexingFailed;
use Illuminate\Support\Facades\Log;

class LogIndexingActivity
{
    /**
     * Handle indexing completed events.
     *
     * @param IndexingCompleted $event
     * @return void
     */
    public function handleIndexingCompleted(IndexingCompleted $event): void
    {
        $message = $event->isBulk() ? 'Bulk indexing completed' : 'Document indexing completed';

        Log::info($message, [
            'job_id' => $event->getJobId(),
            'index_name' => $event->getIndexName(),
            'document_id' => $event->getDocumentId(),
            'is_bulk' => $event->isBulk(),
            'result' => $event->getResult(),
        ]);
    }

    /**
     * Handle indexing failed events.
     *
     * @param IndexingFailed $event
     * @return void
     */
    public function handleIndexingFailed(IndexingFailed $event): void
    {
        $message = $event->isBulk() ? 'Bulk indexing failed' : 'Document indexing failed';

        Log::error($message, [
            'job_id' => $event->getJobId(),
            'index_name' => $event->getIndexName(),
            'document_id' => $event->getDocumentId(),
            'is_bulk' => $event->isBulk(),
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
            IndexingCompleted::class,
            [LogIndexingActivity::class, 'handleIndexingCompleted']
        );

        $events->listen(
            IndexingFailed::class,
            [LogIndexingActivity::class, 'handleIndexingFailed']
        );
    }
}
