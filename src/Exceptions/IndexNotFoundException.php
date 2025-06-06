<?php

namespace OginiScoutDriver\Exceptions;

use Throwable;

/**
 * Exception thrown when an index is not found.
 */
class IndexNotFoundException extends OginiException
{
    protected string $indexName;

    /**
     * Create a new IndexNotFoundException instance.
     *
     * @param string $indexName The name of the index that was not found
     * @param Throwable|null $previous The previous exception
     * @param array|null $response The API response data
     */
    public function __construct(
        string $indexName,
        ?Throwable $previous = null,
        ?array $response = null
    ) {
        $message = "Index '{$indexName}' not found";
        parent::__construct($message, 404, $previous, $response, 'INDEX_NOT_FOUND');

        $this->indexName = $indexName;
    }

    /**
     * Get the index name that was not found.
     *
     * @return string
     */
    public function getIndexName(): string
    {
        return $this->indexName;
    }

    /**
     * Get detailed error information.
     *
     * @return array
     */
    public function getContext(): array
    {
        return array_merge(parent::toArray(), [
            'index_name' => $this->indexName,
            'index_not_found' => true,
        ]);
    }

    /**
     * Create an exception for missing index during search.
     *
     * @param string $indexName
     * @param Throwable|null $previous
     * @return static
     */
    public static function forSearch(string $indexName, ?Throwable $previous = null): static
    {
        $exception = new static($indexName, $previous);
        $exception->message = "Cannot search in index '{$indexName}' because it does not exist";
        return $exception;
    }

    /**
     * Create an exception for missing index during indexing.
     *
     * @param string $indexName
     * @param Throwable|null $previous
     * @return static
     */
    public static function forIndexing(string $indexName, ?Throwable $previous = null): static
    {
        $exception = new static($indexName, $previous);
        $exception->message = "Cannot index documents to '{$indexName}' because the index does not exist";
        return $exception;
    }

    /**
     * Create an exception for missing index during deletion.
     *
     * @param string $indexName
     * @param Throwable|null $previous
     * @return static
     */
    public static function forDeletion(string $indexName, ?Throwable $previous = null): static
    {
        $exception = new static($indexName, $previous);
        $exception->message = "Cannot delete index '{$indexName}' because it does not exist";
        return $exception;
    }

    /**
     * Create an exception for missing index during configuration.
     *
     * @param string $indexName
     * @param Throwable|null $previous
     * @return static
     */
    public static function forConfiguration(string $indexName, ?Throwable $previous = null): static
    {
        $exception = new static($indexName, $previous);
        $exception->message = "Cannot configure index '{$indexName}' because it does not exist";
        return $exception;
    }
}
