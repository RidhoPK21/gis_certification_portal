<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class)
            ->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this
            ->belongsToMany(Permission::class)
            ->withTimestamps();
    }
}