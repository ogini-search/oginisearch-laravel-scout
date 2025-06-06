<?php

namespace OginiScoutDriver\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeletionCompleted
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
     * Get the operation result.
     *
     * @return array
     */
    public function getResult(): array
    {
        return $this->data['result'] ?? [];
    }
}
