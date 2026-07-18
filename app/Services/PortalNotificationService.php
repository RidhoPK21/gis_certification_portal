<?php

namespace App\Services;

use App\Mail\PortalEventMail;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PortalNotificationService
{
    public function send(
        User|int $user,
        string $type,
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ): PortalNotification {
        $model = $user instanceof User ? $user : User::findOrFail($user);

        $notification = PortalNotification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $model->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $url,
            'data' => $data ?: null,
        ]);

        if (config('gis.email_notifications') && filled($model->email)) {
            Mail::to($model->email)->queue(new PortalEventMail($title, $message, $url));
        }

        return $notification;
    }
}
