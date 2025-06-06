<?php

namespace OginiScoutDriver\Tests\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use OginiScoutDriver\Traits\OginiSearchable;

class TestProduct extends Model
{
    use OginiSearchable;

    protected $table = 'test_products';

    protected $fillable = [
        'title',
        'description',
        'price',
        'category',
        'status',
        'is_featured',
        'tags',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_featured' => 'boolean',
        'tags' => 'array',
    ];

    /**
     * Get the indexable data array for the model.
     *
     * @return array
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (float) $this->price,
            'category' => $this->category,
            'status' => $this->status,
            'is_featured' => $this->is_featured,
            'tags' => $this->tags ?? [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get the index name for the model.
     *
     * @return string
     */
    public function searchableAs(): string
    {
        return 'test_products';
    }

    /**
     * Get custom index configuration.
     *
     * @return array
     */
    public function getOginiIndexConfiguration(): array
    {
        return [
            'settings' => [
                'numberOfShards' => 1,
                'refreshInterval' => '1s',
            ],
            'mappings' => [
                'properties' => [
                    'id' => ['type' => 'keyword'],
                    'title' => ['type' => 'text', 'analyzer' => 'standard'],
                    'description' => ['type' => 'text', 'analyzer' => 'standard'],
                    'price' => ['type' => 'float'],
                    'category' => ['type' => 'keyword'],
                    'status' => ['type' => 'keyword'],
                    'is_featured' => ['type' => 'boolean'],
                    'tags' => ['type' => 'keyword'],
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ],
            ],
        ];
    }

    /**
     * Get the fields that should be searched.
     *
     * @return array
     */
    public function getSearchFields(): array
    {
        return ['title', 'description'];
    }

    /**
     * Determine if the model should be searchable.
     * Disabled by default for integration tests to prevent automatic indexing.
     *
     * @return bool
     */
    public function shouldBeSearchable(): bool
    {
        return false;
    }
}
