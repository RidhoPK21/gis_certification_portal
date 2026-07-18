<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationDocumentVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
