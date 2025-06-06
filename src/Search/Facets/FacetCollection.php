<?php

namespace OginiScoutDriver\Search\Facets;

use Illuminate\Support\Collection;
use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;

class FacetCollection implements ArrayAccess, Countable, IteratorAggregate
{
    protected array $facets = [];

    public function __construct(array $facets = [])
    {
        foreach ($facets as $field => $facet) {
            if ($facet instanceof FacetResult) {
                $this->facets[$field] = $facet;
            } elseif (is_array($facet)) {
                $this->facets[$field] = FacetResult::fromResponse($field, $facet);
            }
        }
    }

    /**
     * Create a facet collection from search response data.
     *
     * @param array $data
     * @return static
     */
    public static function fromResponse(array $data): static
    {
        $facets = [];

        foreach ($data as $field => $facetData) {
            $facets[$field] = FacetResult::fromResponse($field, $facetData);
        }

        return new static($facets);
    }

    /**
     * Add a facet result.
     *
     * @param string $field
     * @param FacetResult $facet
     * @return static
     */
    public function add(string $field, FacetResult $facet): static
    {
        $this->facets[$field] = $facet;
        return $this;
    }

    /**
     * Get a facet by field name.
     *
     * @param string $field
     * @return FacetResult|null
     */
    public function get(string $field): ?FacetResult
    {
        return $this->facets[$field] ?? null;
    }

    /**
     * Check if a facet exists.
     *
     * @param string $field
     * @return bool
     */
    public function has(string $field): bool
    {
        return isset($this->facets[$field]);
    }

    /**
     * Remove a facet.
     *
     * @param string $field
     * @return static
     */
    public function remove(string $field): static
    {
        unset($this->facets[$field]);
        return $this;
    }

    /**
     * Get all facets.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->facets;
    }

    /**
     * Get all facet fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return array_keys($this->facets);
    }

    /**
     * Get facets as a Laravel collection.
     *
     * @return Collection
     */
    public function collect(): Collection
    {
        return collect($this->facets);
    }

    /**
     * Filter facets by a callback.
     *
     * @param callable $callback
     * @return static
     */
    public function filter(callable $callback): static
    {
        $filtered = array_filter($this->facets, $callback, ARRAY_FILTER_USE_BOTH);
        return new static($filtered);
    }

    /**
     * Get only non-empty facets.
     *
     * @return static
     */
    public function nonEmpty(): static
    {
        return $this->filter(function (FacetResult $facet) {
            return !$facet->isEmpty();
        });
    }

    /**
     * Get facets by type.
     *
     * @param string $type
     * @return static
     */
    public function byType(string $type): static
    {
        return $this->filter(function (FacetResult $facet) use ($type) {
            return $facet->getType() === $type;
        });
    }

    /**
     * Convert to array format.
     *
     * @return array
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->facets as $field => $facet) {
            $result[$field] = $facet->toArray();
        }

        return $result;
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
     * Check if the collection is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->facets);
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return $this->has($offset);
    }

    public function offsetGet($offset): ?FacetResult
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value): void
    {
        if ($value instanceof FacetResult) {
            $this->facets[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        $this->remove($offset);
    }

    // Countable implementation
    public function count(): int
    {
        return count($this->facets);
    }

    // IteratorAggregate implementation
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->facets);
    }
}
