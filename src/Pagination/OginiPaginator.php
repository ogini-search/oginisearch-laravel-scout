<?php

namespace OginiScoutDriver\Pagination;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class OginiPaginator extends LengthAwarePaginator
{
    /**
     * @var array Ogini-specific pagination metadata
     */
    protected array $oginiPagination;

    /**
     * @var float|null Search execution time in milliseconds
     */
    protected ?float $searchTime;

    /**
     * @var float|null Maximum score from search results
     */
    protected ?float $maxScore;

    /**
     * Create a new Ogini paginator instance.
     *
     * @param Collection|array $items
     * @param int $total
     * @param int $perPage
     * @param int $currentPage
     * @param array $options
     * @param array $oginiPagination
     * @param float|null $searchTime
     * @param float|null $maxScore
     */
    public function __construct(
        $items,
        $total,
        $perPage,
        $currentPage,
        array $options = [],
        array $oginiPagination = [],
        ?float $searchTime = null,
        ?float $maxScore = null
    ) {
        parent::__construct($items, $total, $perPage, $currentPage, $options);

        $this->oginiPagination = $oginiPagination;
        $this->searchTime = $searchTime;
        $this->maxScore = $maxScore;
    }

    /**
     * Get Ogini-specific pagination metadata.
     *
     * @return array
     */
    public function getOginiPagination(): array
    {
        return $this->oginiPagination;
    }

    /**
     * Check if there's a next page using Ogini metadata.
     *
     * @return bool
     */
    public function hasNextPage(): bool
    {
        return $this->oginiPagination['hasNext'] ?? parent::hasNextPage();
    }

    /**
     * Check if there's a previous page using Ogini metadata.
     *
     * @return bool
     */
    public function hasPreviousPage(): bool
    {
        return $this->oginiPagination['hasPrevious'] ?? parent::hasPreviousPage();
    }

    /**
     * Get total number of pages from Ogini metadata.
     *
     * @return int
     */
    public function getTotalPages(): int
    {
        return $this->oginiPagination['totalPages'] ?? $this->lastPage();
    }

    /**
     * Get current page number from Ogini metadata.
     *
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->oginiPagination['currentPage'] ?? $this->currentPage();
    }

    /**
     * Get page size from Ogini metadata.
     *
     * @return int
     */
    public function getPageSize(): int
    {
        return $this->oginiPagination['pageSize'] ?? $this->perPage();
    }

    /**
     * Get total results count from Ogini metadata.
     *
     * @return int
     */
    public function getTotalResults(): int
    {
        return $this->oginiPagination['totalResults'] ?? $this->total();
    }

    /**
     * Get search execution time in milliseconds.
     *
     * @return float|null
     */
    public function getSearchTime(): ?float
    {
        return $this->searchTime;
    }

    /**
     * Get maximum score from search results.
     *
     * @return float|null
     */
    public function getMaxScore(): ?float
    {
        return $this->maxScore;
    }

    /**
     * Check if search was successful.
     *
     * @return bool
     */
    public function isSearchSuccessful(): bool
    {
        return $this->searchTime !== null;
    }

    /**
     * Get search performance metrics.
     *
     * @return array
     */
    public function getSearchMetrics(): array
    {
        return [
            'search_time_ms' => $this->searchTime,
            'max_score' => $this->maxScore,
            'total_results' => $this->getTotalResults(),
            'current_page' => $this->getCurrentPage(),
            'total_pages' => $this->getTotalPages(),
            'page_size' => $this->getPageSize(),
            'has_next' => $this->hasNextPage(),
            'has_previous' => $this->hasPreviousPage(),
        ];
    }

    /**
     * Convert the paginator to an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'ogini_metadata' => $this->oginiPagination,
            'search_metrics' => $this->getSearchMetrics(),
        ]);
    }
}
