<?php

namespace OginiScoutDriver\Search\Sorting;

class SortBuilder
{
    protected array $sorts = [];

    /**
     * Add a field sort.
     *
     * @param string $field
     * @param string $direction
     * @param array $options
     * @return static
     */
    public function field(string $field, string $direction = 'asc', array $options = []): static
    {
        $this->sorts[] = array_merge([
            'field' => $field,
            'direction' => $direction,
        ], $options);

        return $this;
    }

    /**
     * Add an ascending sort.
     *
     * @param string $field
     * @param array $options
     * @return static
     */
    public function asc(string $field, array $options = []): static
    {
        return $this->field($field, 'asc', $options);
    }

    /**
     * Add a descending sort.
     *
     * @param string $field
     * @param array $options
     * @return static
     */
    public function desc(string $field, array $options = []): static
    {
        return $this->field($field, 'desc', $options);
    }

    /**
     * Add a relevance score sort.
     *
     * @param string $direction
     * @return static
     */
    public function score(string $direction = 'desc'): static
    {
        $this->sorts[] = [
            'field' => '_score',
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Add a geo distance sort.
     *
     * @param string $field
     * @param array $location
     * @param string $direction
     * @param array $options
     * @return static
     */
    public function geoDistance(string $field, array $location, string $direction = 'asc', array $options = []): static
    {
        $this->sorts[] = array_merge([
            'type' => 'geo_distance',
            'field' => $field,
            'location' => $location,
            'direction' => $direction,
        ], $options);

        return $this;
    }

    /**
     * Add a script-based sort.
     *
     * @param string $script
     * @param string $direction
     * @param array $options
     * @return static
     */
    public function script(string $script, string $direction = 'asc', array $options = []): static
    {
        $this->sorts[] = array_merge([
            'type' => 'script',
            'script' => $script,
            'direction' => $direction,
        ], $options);

        return $this;
    }

    /**
     * Add a nested field sort.
     *
     * @param string $path
     * @param string $field
     * @param string $direction
     * @param array $options
     * @return static
     */
    public function nested(string $path, string $field, string $direction = 'asc', array $options = []): static
    {
        $this->sorts[] = array_merge([
            'type' => 'nested',
            'path' => $path,
            'field' => $field,
            'direction' => $direction,
        ], $options);

        return $this;
    }

    /**
     * Add a random sort.
     *
     * @param int|null $seed
     * @return static
     */
    public function random(?int $seed = null): static
    {
        $sort = [
            'type' => 'random',
        ];

        if ($seed !== null) {
            $sort['seed'] = $seed;
        }

        $this->sorts[] = $sort;

        return $this;
    }

    /**
     * Add a sort with missing value handling.
     *
     * @param string $field
     * @param string $direction
     * @param mixed $missingValue
     * @return static
     */
    public function withMissing(string $field, string $direction = 'asc', $missingValue = '_last'): static
    {
        return $this->field($field, $direction, [
            'missing' => $missingValue,
        ]);
    }

    /**
     * Add a sort with custom mode for multi-value fields.
     *
     * @param string $field
     * @param string $direction
     * @param string $mode
     * @return static
     */
    public function withMode(string $field, string $direction = 'asc', string $mode = 'min'): static
    {
        return $this->field($field, $direction, [
            'mode' => $mode,
        ]);
    }

    /**
     * Add multiple sorts from an array.
     *
     * @param array $sorts
     * @return static
     */
    public function multiple(array $sorts): static
    {
        foreach ($sorts as $sort) {
            if (is_string($sort)) {
                // Simple field name, default to ascending
                $this->asc($sort);
            } elseif (is_array($sort) && isset($sort['field'])) {
                // Array with field and direction
                $this->field(
                    $sort['field'],
                    $sort['direction'] ?? 'asc',
                    array_diff_key($sort, ['field' => null, 'direction' => null])
                );
            }
        }

        return $this;
    }

    /**
     * Get all sorts.
     *
     * @return array
     */
    public function getSorts(): array
    {
        return $this->sorts;
    }

    /**
     * Build the sort query.
     *
     * @return array
     */
    public function build(): array
    {
        return $this->sorts;
    }

    /**
     * Build as a sort string for simple cases.
     *
     * @return string
     */
    public function buildString(): string
    {
        $sortParts = [];

        foreach ($this->sorts as $sort) {
            if (isset($sort['field']) && !isset($sort['type'])) {
                $sortParts[] = $sort['field'] . ':' . ($sort['direction'] ?? 'asc');
            }
        }

        return implode(',', $sortParts);
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
     * Check if the builder has any sorts.
     *
     * @return bool
     */
    public function hasSorts(): bool
    {
        return !empty($this->sorts);
    }

    /**
     * Clear all sorts.
     *
     * @return static
     */
    public function clear(): static
    {
        $this->sorts = [];
        return $this;
    }

    /**
     * Get the number of sorts.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->sorts);
    }

    /**
     * Create a sort builder from Laravel Scout orders.
     *
     * @param array $orders
     * @return static
     */
    public static function fromScoutOrders(array $orders): static
    {
        $builder = new static();

        foreach ($orders as $order) {
            $builder->field($order['column'], $order['direction']);
        }

        return $builder;
    }
}
