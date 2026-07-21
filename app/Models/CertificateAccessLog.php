<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateAccessLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
            'is_success' => 'boolean',
        ];
    }
}
