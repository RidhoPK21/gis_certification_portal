<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationRevisionItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }
}
