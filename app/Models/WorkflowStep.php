<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_skippable' => 'boolean',
            'entry_rules' => 'array',
            'completion_rules' => 'array',
        ];
    }
}
