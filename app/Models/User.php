<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company_name',
        'job_title',
        'locale',
        'signature_path',
        'password',
        'is_active',
        'last_login_at',
    ];

    /**
     * Role internal yang menandatangani dokumen resmi dan boleh
     * mengelola tanda tangan elektronik di profil.
     */
    public const SIGNATURE_ROLES = ['admin_application', 'technical', 'auditor', 'superadmin'];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
{
    return $this
        ->belongsToMany(Role::class)
        ->withTimestamps();
}

public function hasRole(array|string $roleCode): bool
{
    if (is_array($roleCode)) {
        return $this->hasAnyRole($roleCode);
    }

    return $this
        ->roles()
        ->where('code', $roleCode)
        ->where('is_active', true)
        ->exists();
}

public function hasAnyRole(array|string $roleCodes): bool
{
    if (is_string($roleCodes)) {
        $roleCodes = array_filter(
            preg_split('/[|,]/', $roleCodes) ?: []
        );
    }

    if ($roleCodes === []) {
        return false;
    }

    return $this
        ->roles()
        ->whereIn('code', $roleCodes)
        ->where('is_active', true)
        ->exists();
}

public function hasPermission(string $permissionCode): bool
{
    return $this
        ->roles()
        ->where('is_active', true)
        ->whereHas(
            'permissions',
            fn ($query) => $query->where(
                'code',
                $permissionCode
            )
        )
        ->exists();
}

public function primaryRole(): ?Role
{
    return $this
        ->roles()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->first();
}

/**
 * Apakah user boleh mengelola tanda tangan elektronik di profil.
 */
public function canManageSignature(): bool
{
    return $this->hasAnyRole(self::SIGNATURE_ROLES);
}

/**
 * Apakah user punya file tanda tangan yang tersimpan.
 */
public function hasSignature(): bool
{
    return filled($this->signature_path)
        && Storage::disk('private')->exists($this->signature_path);
}

/**
 * Path absolut file tanda tangan (untuk disisipkan ke PDF), null bila tidak ada.
 */
public function signatureAbsolutePath(): ?string
{
    return $this->hasSignature()
        ? Storage::disk('private')->path($this->signature_path)
        : null;
}

}