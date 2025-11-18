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
     * @var array|null Typo tolerance information from search results
     */
    protected ?array $typoTolerance;

    /**
     * @var array Raw search hits from the search engine (contains indexed data)
     */
    protected array $rawHits = [];

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
     * @param array|null $typoTolerance
     * @param array $rawHits
     */
    public function __construct(
        $items,
        $total,
        $perPage,
        $currentPage,
        array $options = [],
        array $oginiPagination = [],
        ?float $searchTime = null,
        ?float $maxScore = null,
        ?array $typoTolerance = null,
        array $rawHits = []
    ) {
        parent::__construct($items, $total, $perPage, $currentPage, $options);

        $this->oginiPagination = $oginiPagination;
        $this->searchTime = $searchTime;
        $this->maxScore = $maxScore;
        $this->typoTolerance = $typoTolerance;
        $this->rawHits = $rawHits;
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
     * Get typo tolerance information from search results.
     *
     * @return array|null
     */
    public function getTypoTolerance(): ?array
    {
        return $this->typoTolerance;
    }

    /**
     * Check if typo tolerance was applied to the search.
     *
     * @return bool
     */
    public function hasTypoTolerance(): bool
    {
        return $this->typoTolerance !== null;
    }

    /**
     * Get the original query if typo tolerance was applied.
     *
     * @return string|null
     */
    public function getOriginalQuery(): ?string
    {
        return $this->typoTolerance['originalQuery'] ?? null;
    }

    /**
     * Get the corrected query if typo tolerance was applied.
     *
     * @return string|null
     */
    public function getCorrectedQuery(): ?string
    {
        return $this->typoTolerance['correctedQuery'] ?? null;
    }

    /**
     * Get the confidence score for typo tolerance correction.
     *
     * @return float|null
     */
    public function getTypoConfidence(): ?float
    {
        return $this->typoTolerance['confidence'] ?? null;
    }

    /**
     * Get typo tolerance suggestions.
     *
     * @return array
     */
    public function getTypoSuggestions(): array
    {
        return $this->typoTolerance['suggestions'] ?? [];
    }

    /**
     * Get typo tolerance corrections.
     *
     * @return array
     */
    public function getTypoCorrections(): array
    {
        return $this->typoTolerance['corrections'] ?? [];
    }

    /**
     * Get raw search hits from the search engine.
     * This contains the indexed data (location_text, category_name, etc.)
     *
     * @return array
     */
    public function getRawHits(): array
    {
        return $this->rawHits;
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
            'typo_tolerance' => $this->typoTolerance,
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
            'typo_tolerance' => $this->typoTolerance,
        ]);
    }
}
