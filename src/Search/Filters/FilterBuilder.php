<?php

namespace OginiScoutDriver\Search\Filters;

class FilterBuilder
{
    protected array $filters = [];

    /**
     * Add a term filter (exact match).
     *
     * @param string $field
     * @param mixed $value
     * @return static
     */
    public function term(string $field, $value): static
    {
        $this->filters[] = [
            'type' => 'term',
            'field' => $field,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add a terms filter (match any of the values).
     *
     * @param string $field
     * @param array $values
     * @return static
     */
    public function terms(string $field, array $values): static
    {
        $this->filters[] = [
            'type' => 'terms',
            'field' => $field,
            'values' => $values,
        ];

        return $this;
    }

    /**
     * Add a range filter.
     *
     * @param string $field
     * @param array $range
     * @return static
     */
    public function range(string $field, array $range): static
    {
        $this->filters[] = [
            'type' => 'range',
            'field' => $field,
            'range' => $range,
        ];

        return $this;
    }

    /**
     * Add a greater than filter.
     *
     * @param string $field
     * @param mixed $value
     * @param bool $inclusive
     * @return static
     */
    public function greaterThan(string $field, $value, bool $inclusive = false): static
    {
        $operator = $inclusive ? 'gte' : 'gt';

        return $this->range($field, [$operator => $value]);
    }

    /**
     * Add a less than filter.
     *
     * @param string $field
     * @param mixed $value
     * @param bool $inclusive
     * @return static
     */
    public function lessThan(string $field, $value, bool $inclusive = false): static
    {
        $operator = $inclusive ? 'lte' : 'lt';

        return $this->range($field, [$operator => $value]);
    }

    /**
     * Add a between filter.
     *
     * @param string $field
     * @param mixed $min
     * @param mixed $max
     * @param bool $inclusive
     * @return static
     */
    public function between(string $field, $min, $max, bool $inclusive = true): static
    {
        $range = [];

        if ($inclusive) {
            $range['gte'] = $min;
            $range['lte'] = $max;
        } else {
            $range['gt'] = $min;
            $range['lt'] = $max;
        }

        return $this->range($field, $range);
    }

    /**
     * Add an exists filter (field has a value).
     *
     * @param string $field
     * @return static
     */
    public function exists(string $field): static
    {
        $this->filters[] = [
            'type' => 'exists',
            'field' => $field,
        ];

        return $this;
    }

    /**
     * Add a missing filter (field does not exist or is null).
     *
     * @param string $field
     * @return static
     */
    public function missing(string $field): static
    {
        $this->filters[] = [
            'type' => 'missing',
            'field' => $field,
        ];

        return $this;
    }

    /**
     * Add a prefix filter.
     *
     * @param string $field
     * @param string $prefix
     * @return static
     */
    public function prefix(string $field, string $prefix): static
    {
        $this->filters[] = [
            'type' => 'prefix',
            'field' => $field,
            'value' => $prefix,
        ];

        return $this;
    }

    /**
     * Add a wildcard filter.
     *
     * @param string $field
     * @param string $pattern
     * @return static
     */
    public function wildcard(string $field, string $pattern): static
    {
        $this->filters[] = [
            'type' => 'wildcard',
            'field' => $field,
            'value' => $pattern,
        ];

        return $this;
    }

    /**
     * Add a regexp filter.
     *
     * @param string $field
     * @param string $pattern
     * @return static
     */
    public function regexp(string $field, string $pattern): static
    {
        $this->filters[] = [
            'type' => 'regexp',
            'field' => $field,
            'value' => $pattern,
        ];

        return $this;
    }

    /**
     * Add a geo distance filter.
     *
     * @param string $field
     * @param array $location
     * @param string $distance
     * @return static
     */
    public function geoDistance(string $field, array $location, string $distance): static
    {
        $this->filters[] = [
            'type' => 'geo_distance',
            'field' => $field,
            'location' => $location,
            'distance' => $distance,
        ];

        return $this;
    }

    /**
     * Add a geo bounding box filter.
     *
     * @param string $field
     * @param array $boundingBox
     * @return static
     */
    public function geoBoundingBox(string $field, array $boundingBox): static
    {
        $this->filters[] = [
            'type' => 'geo_bounding_box',
            'field' => $field,
            'bounding_box' => $boundingBox,
        ];

        return $this;
    }

    /**
     * Add a nested filter.
     *
     * @param string $path
     * @param callable $callback
     * @return static
     */
    public function nested(string $path, callable $callback): static
    {
        $nestedBuilder = new static();
        $callback($nestedBuilder);

        $this->filters[] = [
            'type' => 'nested',
            'path' => $path,
            'query' => $nestedBuilder->build(),
        ];

        return $this;
    }

    /**
     * Create a bool filter with must conditions.
     *
     * @param callable $callback
     * @return static
     */
    public function must(callable $callback): static
    {
        $mustBuilder = new static();
        $callback($mustBuilder);

        $this->filters[] = [
            'type' => 'bool',
            'bool' => [
                'must' => $mustBuilder->build(),
            ],
        ];

        return $this;
    }

    /**
     * Create a bool filter with should conditions (OR).
     *
     * @param callable $callback
     * @return static
     */
    public function should(callable $callback): static
    {
        $shouldBuilder = new static();
        $callback($shouldBuilder);

        $this->filters[] = [
            'type' => 'bool',
            'bool' => [
                'should' => $shouldBuilder->build(),
            ],
        ];

        return $this;
    }

    /**
     * Create a bool filter with must_not conditions.
     *
     * @param callable $callback
     * @return static
     */
    public function mustNot(callable $callback): static
    {
        $mustNotBuilder = new static();
        $callback($mustNotBuilder);

        $this->filters[] = [
            'type' => 'bool',
            'bool' => [
                'must_not' => $mustNotBuilder->build(),
            ],
        ];

        return $this;
    }

    /**
     * Get all filters.
     *
     * @return array
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Build the filter query.
     *
     * @return array
     */
    public function build(): array
    {
        if (empty($this->filters)) {
            return [];
        }

        if (count($this->filters) === 1) {
            return $this->filters[0];
        }

        return [
            'type' => 'bool',
            'bool' => [
                'must' => $this->filters,
            ],
        ];
    }

    /**
     * Convert to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->build();
    }

    /**
     * Check if the builder has any filters.
     *
     * @return bool
     */
    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    /**
     * Clear all filters.
     *
     * @return static
     */
    public function clear(): static
    {
        $this->filters = [];
        return $this;
    }
}
