<?php

namespace OginiScoutDriver\Search\Facets;

use Illuminate\Support\Collection;

class FacetResult
{
    protected string $field;
    protected string $type;
    protected array $buckets;
    protected array $metadata;

    public function __construct(string $field, string $type, array $buckets = [], array $metadata = [])
    {
        $this->field = $field;
        $this->type = $type;
        $this->buckets = $buckets;
        $this->metadata = $metadata;
    }

    /**
     * Create a facet result from raw search response data.
     *
     * @param string $field
     * @param array $data
     * @return static
     */
    public static function fromResponse(string $field, array $data): static
    {
        $type = $data['type'] ?? FacetDefinition::TYPE_TERMS;
        $buckets = $data['buckets'] ?? [];
        $metadata = array_diff_key($data, ['type' => null, 'buckets' => null]);

        return new static($field, $type, $buckets, $metadata);
    }

    /**
     * Get the field name.
     *
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Get the facet type.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get all buckets.
     *
     * @return array
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    /**
     * Get buckets as a collection.
     *
     * @return Collection
     */
    public function buckets(): Collection
    {
        return collect($this->buckets);
    }

    /**
     * Get metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get a specific metadata value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get the total number of documents.
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->getMetadataValue('total') ?? array_sum(array_column($this->buckets, 'count'));
    }

    /**
     * Get buckets with count greater than zero.
     *
     * @return Collection
     */
    public function nonEmptyBuckets(): Collection
    {
        return $this->buckets()->filter(function ($bucket) {
            return ($bucket['count'] ?? 0) > 0;
        });
    }

    /**
     * Get the top N buckets by count.
     *
     * @param int $limit
     * @return Collection
     */
    public function topBuckets(int $limit = 10): Collection
    {
        return $this->buckets()
            ->sortByDesc('count')
            ->take($limit);
    }

    /**
     * Get buckets sorted by key.
     *
     * @param bool $ascending
     * @return Collection
     */
    public function sortedByKey(bool $ascending = true): Collection
    {
        return $this->buckets()->sortBy('key', SORT_REGULAR, !$ascending);
    }

    /**
     * Get buckets sorted by count.
     *
     * @param bool $ascending
     * @return Collection
     */
    public function sortedByCount(bool $ascending = false): Collection
    {
        return $this->buckets()->sortBy('count', SORT_REGULAR, !$ascending);
    }

    /**
     * Find a bucket by key.
     *
     * @param mixed $key
     * @return array|null
     */
    public function findBucket($key): ?array
    {
        return $this->buckets()->firstWhere('key', $key);
    }

    /**
     * Check if a bucket exists for the given key.
     *
     * @param mixed $key
     * @return bool
     */
    public function hasBucket($key): bool
    {
        return $this->findBucket($key) !== null;
    }

    /**
     * Get the count for a specific bucket key.
     *
     * @param mixed $key
     * @return int
     */
    public function getBucketCount($key): int
    {
        $bucket = $this->findBucket($key);
        return $bucket['count'] ?? 0;
    }

    /**
     * Get all bucket keys.
     *
     * @return Collection
     */
    public function getKeys(): Collection
    {
        return $this->buckets()->pluck('key');
    }

    /**
     * Get all bucket counts.
     *
     * @return Collection
     */
    public function getCounts(): Collection
    {
        return $this->buckets()->pluck('count');
    }

    /**
     * Convert to array format suitable for JSON responses.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'type' => $this->type,
            'buckets' => $this->buckets,
            'metadata' => $this->metadata,
            'total' => $this->getTotal(),
        ];
    }

    /**
     * Convert to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Check if the facet result is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->buckets);
    }

    /**
     * Get the number of buckets.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->buckets);
    }
}
