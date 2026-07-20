<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorrectiveActionReview extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['action_date' => 'date'];
    }
}
