<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateDownloadLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['downloaded_at' => 'datetime'];
    }
}
