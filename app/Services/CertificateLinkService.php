<?php

namespace App\Services;

use App\Models\CertificateAccessLog;
use App\Models\CertificateShareLink;
use App\Models\CertificationApplication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CertificateLinkService
{
    public function create(CertificationApplication $application, string $type, int $recordId, int $userId, ?\DateTimeInterface $expiresAt = null): array
    {
        $token = Str::random(64);
        $password = $type === 'final' ? strtoupper(Str::random(10)) : null;
        $link = CertificateShareLink::create([
            'application_id' => $application->id,
            'certificate_draft_id' => $type === 'draft' ? $recordId : null,
            'certificate_final_id' => $type === 'final' ? $recordId : null,
            'link_type' => $type,
            'token_hash' => hash('sha256', $token),
            'password_hash' => $password ? Hash::make($password) : null,
            'expires_at' => $expiresAt ?? ($type === 'draft'
                ? now()->addHours(config('gis.draft_link_hours'))
                : now()->addDays(config('gis.final_link_days'))),
            'created_by' => $userId,
        ]);

        return ['link' => $link, 'token' => $token, 'password' => $password];
    }

    public function find(string $token): CertificateShareLink
    {
        return CertificateShareLink::where('token_hash', hash('sha256', $token))->firstOrFail();
    }

    public function log(CertificateShareLink $link, string $action, bool $success = true, ?string $reason = null, ?string $email = null): void
    {
        $link->increment('access_count');
        CertificateAccessLog::create([
            'certificate_share_link_id' => $link->id, 'user_id' => auth()->id(), 'email' => $email,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'action' => $action,
            'is_success' => $success, 'failure_reason' => $reason, 'accessed_at' => now(),
        ]);
    }
}
