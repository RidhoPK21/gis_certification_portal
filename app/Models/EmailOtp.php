<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailOtp extends Model
{
    public const PURPOSE_REGISTRATION = 'registration';

    public const PURPOSE_ADMIN_INVITE = 'admin_invite';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
