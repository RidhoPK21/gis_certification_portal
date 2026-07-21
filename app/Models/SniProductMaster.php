<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SniProductMaster extends Model
{
    protected $table = 'sni_product_master';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
