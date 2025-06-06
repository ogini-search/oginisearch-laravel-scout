<?php

namespace OginiScoutDriver\Tests\Integration\Models;

use Illuminate\Database\Eloquent\Model;
use OginiScoutDriver\Traits\OginiSearchable;

class TestUser extends Model
{
    use OginiSearchable;

    protected $table = 'test_users';

    protected $fillable = [
        'name',
        'email',
        'role',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
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
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'active' => $this->active,
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
        return 'test_users';
    }

    /**
     * Get the fields that should be searched.
     *
     * @return array
     */
    public function getSearchFields(): array
    {
        return ['name', 'email'];
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
