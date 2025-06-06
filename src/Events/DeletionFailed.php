<?php

namespace OginiScoutDriver\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeletionFailed
{
    use Dispatchable, SerializesModels;

    public array $data;

    /**
     * Create a new event instance.
     *
     * @param array $data Event data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Get the job ID.
     *
     * @return string
     */
    public function getJobId(): string
    {
        return $this->data['job_id'];
    }

    /**
     * Get the index name.
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->data['index_name'];
    }

    /**
     * Get the document ID.
     *
     * @return string
     */
    public function getDocumentId(): string
    {
        return $this->data['document_id'];
    }

    /**
     * Get the error message.
     *
     * @return string
     */
    public function getError(): string
    {
        return $this->data['error'] ?? 'Unknown error';
    }

    /**
     * Get the exception.
     *
     * @return \Exception|null
     */
    public function getException(): ?\Exception
    {
        return $this->data['exception'] ?? null;
    }
}
