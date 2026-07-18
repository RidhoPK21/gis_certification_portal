<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationStatusHistory extends Model
{
    protected $table = 'application_status_history';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'action_date' => 'datetime',
            'system_recorded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
