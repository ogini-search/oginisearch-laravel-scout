<?php

namespace OginiScoutDriver\Search\Facets;

class FacetDefinition
{
    protected string $field;
    protected string $type;
    protected array $options;

    public const TYPE_TERMS = 'terms';
    public const TYPE_RANGE = 'range';
    public const TYPE_DATE_HISTOGRAM = 'date_histogram';
    public const TYPE_HISTOGRAM = 'histogram';

    public function __construct(string $field, string $type = self::TYPE_TERMS, array $options = [])
    {
        $this->field = $field;
        $this->type = $type;
        $this->options = $options;
    }

    /**
     * Create a terms facet for categorical data.
     *
     * @param string $field
     * @param int $size
     * @param array $options
     * @return static
     */
    public static function terms(string $field, int $size = 10, array $options = []): static
    {
        return new static($field, self::TYPE_TERMS, array_merge([
            'size' => $size,
        ], $options));
    }

    /**
     * Create a range facet for numerical data.
     *
     * @param string $field
     * @param array $ranges
     * @param array $options
     * @return static
     */
    public static function range(string $field, array $ranges, array $options = []): static
    {
        return new static($field, self::TYPE_RANGE, array_merge([
            'ranges' => $ranges,
        ], $options));
    }

    /**
     * Create a date histogram facet for date data.
     *
     * @param string $field
     * @param string $interval
     * @param array $options
     * @return static
     */
    public static function dateHistogram(string $field, string $interval = '1d', array $options = []): static
    {
        return new static($field, self::TYPE_DATE_HISTOGRAM, array_merge([
            'interval' => $interval,
        ], $options));
    }

    /**
     * Create a histogram facet for numerical data.
     *
     * @param string $field
     * @param float $interval
     * @param array $options
     * @return static
     */
    public static function histogram(string $field, float $interval, array $options = []): static
    {
        return new static($field, self::TYPE_HISTOGRAM, array_merge([
            'interval' => $interval,
        ], $options));
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
     * Get the facet options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Set a facet option.
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function setOption(string $key, $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * Get a facet option.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Convert the facet definition to an array for the search query.
     *
     * @return array
     */
    public function toArray(): array
    {
        $facet = [
            'field' => $this->field,
            'type' => $this->type,
        ];

        return array_merge($facet, $this->options);
    }

    /**
     * Convert the facet definition to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
