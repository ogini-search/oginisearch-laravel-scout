<?php

namespace OginiScoutDriver\Tests\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use OginiScoutDriver\Traits\OginiSearchable;

class TestArticle extends Model
{
    use OginiSearchable;

    protected $table = 'test_articles';

    protected $fillable = [
        'title',
        'content',
        'author',
        'status',
        'published_at',
        'views',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views' => 'integer',
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
            'content' => $this->content,
            'author' => $this->author,
            'status' => $this->status,
            'published_at' => $this->published_at?->toISOString(),
            'views' => $this->views,
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
        return 'test_articles';
    }

    /**
     * Get the fields that should be searched.
     *
     * @return array
     */
    public function getSearchFields(): array
    {
        return ['title', 'content', 'author'];
    }

    /**
     * Scope for published articles.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at');
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
