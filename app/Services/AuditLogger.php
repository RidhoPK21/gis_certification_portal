<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        string $event,
        Model|string|null $subject = null,
        array $old = [],
        array $new = [],
        array $metadata = []
    ): ActivityLog {
        $request = request();

        return ActivityLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'subject_type' => $subject instanceof Model
                ? $subject::class
                : (is_string($subject) ? $subject : null),
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'ip_address' => $request?->ip(),
            'user_agent' => str($request?->userAgent())->limit(1000)->toString(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'metadata' => $metadata ?: null,
            'occurred_at' => now(),
        ]);
    }
}
