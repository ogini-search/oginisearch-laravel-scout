<?php

namespace OginiScoutDriver\Search\Highlighting;

class HighlightBuilder
{
    protected array $fields = [];
    protected array $globalOptions = [];

    /**
     * Add a field to highlight.
     *
     * @param string $field
     * @param array $options
     * @return static
     */
    public function field(string $field, array $options = []): static
    {
        $this->fields[$field] = $options;
        return $this;
    }

    /**
     * Add multiple fields to highlight.
     *
     * @param array $fields
     * @return static
     */
    public function fields(array $fields): static
    {
        foreach ($fields as $field => $options) {
            if (is_numeric($field)) {
                // Simple field name without options
                $this->field($options);
            } else {
                // Field with options
                $this->field($field, $options);
            }
        }

        return $this;
    }

    /**
     * Set the pre-tag for highlighting.
     *
     * @param string $preTag
     * @return static
     */
    public function preTag(string $preTag): static
    {
        $this->globalOptions['pre_tag'] = $preTag;
        return $this;
    }

    /**
     * Set the post-tag for highlighting.
     *
     * @param string $postTag
     * @return static
     */
    public function postTag(string $postTag): static
    {
        $this->globalOptions['post_tag'] = $postTag;
        return $this;
    }

    /**
     * Set both pre and post tags.
     *
     * @param string $preTag
     * @param string $postTag
     * @return static
     */
    public function tags(string $preTag, string $postTag): static
    {
        return $this->preTag($preTag)->postTag($postTag);
    }

    /**
     * Set HTML tags for highlighting.
     *
     * @param string $tag
     * @param array $attributes
     * @return static
     */
    public function htmlTag(string $tag = 'em', array $attributes = []): static
    {
        $attributeString = '';
        if (!empty($attributes)) {
            $attributePairs = [];
            foreach ($attributes as $key => $value) {
                $attributePairs[] = $key . '="' . htmlspecialchars($value) . '"';
            }
            $attributeString = ' ' . implode(' ', $attributePairs);
        }

        return $this->tags("<{$tag}{$attributeString}>", "</{$tag}>");
    }

    /**
     * Set the fragment size.
     *
     * @param int $size
     * @return static
     */
    public function fragmentSize(int $size): static
    {
        $this->globalOptions['fragment_size'] = $size;
        return $this;
    }

    /**
     * Set the number of fragments.
     *
     * @param int $count
     * @return static
     */
    public function numberOfFragments(int $count): static
    {
        $this->globalOptions['number_of_fragments'] = $count;
        return $this;
    }

    /**
     * Set the fragment offset.
     *
     * @param int $offset
     * @return static
     */
    public function fragmentOffset(int $offset): static
    {
        $this->globalOptions['fragment_offset'] = $offset;
        return $this;
    }

    /**
     * Set the highlighter type.
     *
     * @param string $type
     * @return static
     */
    public function type(string $type): static
    {
        $this->globalOptions['type'] = $type;
        return $this;
    }

    /**
     * Use plain highlighter.
     *
     * @return static
     */
    public function plain(): static
    {
        return $this->type('plain');
    }

    /**
     * Use unified highlighter.
     *
     * @return static
     */
    public function unified(): static
    {
        return $this->type('unified');
    }

    /**
     * Use fast vector highlighter.
     *
     * @return static
     */
    public function fastVector(): static
    {
        return $this->type('fvh');
    }

    /**
     * Set the boundary scanner.
     *
     * @param string $scanner
     * @return static
     */
    public function boundaryScanner(string $scanner): static
    {
        $this->globalOptions['boundary_scanner'] = $scanner;
        return $this;
    }

    /**
     * Set boundary characters.
     *
     * @param string $chars
     * @return static
     */
    public function boundaryChars(string $chars): static
    {
        $this->globalOptions['boundary_chars'] = $chars;
        return $this;
    }

    /**
     * Set the maximum boundary scan.
     *
     * @param int $max
     * @return static
     */
    public function boundaryMaxScan(int $max): static
    {
        $this->globalOptions['boundary_max_scan'] = $max;
        return $this;
    }

    /**
     * Enable or disable highlighting on matched fields only.
     *
     * @param bool $matchedFieldsOnly
     * @return static
     */
    public function matchedFieldsOnly(bool $matchedFieldsOnly = true): static
    {
        $this->globalOptions['matched_fields_only'] = $matchedFieldsOnly;
        return $this;
    }

    /**
     * Set the order of highlighted fragments.
     *
     * @param string $order
     * @return static
     */
    public function order(string $order): static
    {
        $this->globalOptions['order'] = $order;
        return $this;
    }

    /**
     * Order fragments by score.
     *
     * @return static
     */
    public function orderByScore(): static
    {
        return $this->order('score');
    }

    /**
     * Order fragments by position.
     *
     * @return static
     */
    public function orderByPosition(): static
    {
        return $this->order('position');
    }

    /**
     * Set phrase limit.
     *
     * @param int $limit
     * @return static
     */
    public function phraseLimit(int $limit): static
    {
        $this->globalOptions['phrase_limit'] = $limit;
        return $this;
    }

    /**
     * Enable require field match.
     *
     * @param bool $require
     * @return static
     */
    public function requireFieldMatch(bool $require = true): static
    {
        $this->globalOptions['require_field_match'] = $require;
        return $this;
    }

    /**
     * Set no match size.
     *
     * @param int $size
     * @return static
     */
    public function noMatchSize(int $size): static
    {
        $this->globalOptions['no_match_size'] = $size;
        return $this;
    }

    /**
     * Add a field with specific highlighting options.
     *
     * @param string $field
     * @param int $fragmentSize
     * @param int $numberOfFragments
     * @param array $additionalOptions
     * @return static
     */
    public function fieldWithOptions(
        string $field,
        int $fragmentSize = 150,
        int $numberOfFragments = 3,
        array $additionalOptions = []
    ): static {
        $options = array_merge([
            'fragment_size' => $fragmentSize,
            'number_of_fragments' => $numberOfFragments,
        ], $additionalOptions);

        return $this->field($field, $options);
    }

    /**
     * Get all highlight fields.
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get global options.
     *
     * @return array
     */
    public function getGlobalOptions(): array
    {
        return $this->globalOptions;
    }

    /**
     * Build the highlight query.
     *
     * @return array
     */
    public function build(): array
    {
        $highlight = $this->globalOptions;

        if (!empty($this->fields)) {
            $highlight['fields'] = $this->fields;
        }

        return $highlight;
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
     * Check if the builder has any fields.
     *
     * @return bool
     */
    public function hasFields(): bool
    {
        return !empty($this->fields);
    }

    /**
     * Clear all fields and options.
     *
     * @return static
     */
    public function clear(): static
    {
        $this->fields = [];
        $this->globalOptions = [];
        return $this;
    }

    /**
     * Create a simple highlight builder with common settings.
     *
     * @param array $fields
     * @param string $preTag
     * @param string $postTag
     * @return static
     */
    public static function simple(array $fields, string $preTag = '<em>', string $postTag = '</em>'): static
    {
        return (new static())
            ->fields($fields)
            ->tags($preTag, $postTag)
            ->fragmentSize(150)
            ->numberOfFragments(3);
    }

    /**
     * Create a highlight builder for HTML output.
     *
     * @param array $fields
     * @param string $tag
     * @param array $attributes
     * @return static
     */
    public static function html(array $fields, string $tag = 'mark', array $attributes = ['class' => 'highlight']): static
    {
        return (new static())
            ->fields($fields)
            ->htmlTag($tag, $attributes)
            ->fragmentSize(200)
            ->numberOfFragments(5)
            ->unified();
    }
}
