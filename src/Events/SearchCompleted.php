<?php

namespace OginiScoutDriver\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SearchCompleted
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
     * Get the search query.
     *
     * @return array
     */
    public function getQuery(): array
    {
        return $this->data['query'] ?? [];
    }

    /**
     * Get the search size parameter.
     *
     * @return int|null
     */
    public function getSize(): ?int
    {
        return $this->data['size'] ?? null;
    }

    /**
     * Get the search from parameter.
     *
     * @return int|null
     */
    public function getFrom(): ?int
    {
        return $this->data['from'] ?? null;
    }

    /**
     * Get the search result.
     *
     * @return array
     */
    public function getResult(): array
    {
        return $this->data['result'] ?? [];
    }
}
