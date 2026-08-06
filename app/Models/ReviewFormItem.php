<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewFormItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['remark_date' => 'date'];
    }
}
