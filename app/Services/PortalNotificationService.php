<?php

namespace App\Services;

use App\Mail\PortalEventMail;
use App\Models\CertificationApplication;
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

    public function sendToRole(
        string $roleCode,
        string $type,
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ): void {
        /*
         * Role dan akun nonaktif ikut disaring, sejalan dengan User::hasRole.
         * Tanpa ini pemberitahuan tetap mengalir ke akun yang sudah dimatikan.
         */
        $users = User::where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('code', $roleCode)->where('is_active', true))
            ->get();

        foreach ($users as $user) {
            $this->send($user, $type, $title, $message, $url, $data);
        }
    }

    /**
     * Kirim ke tim yang memiliki skema permohonan ini.
     *
     * ISPO ditangani Tim Sustain, skema lain oleh Admin Permohonan dan Tim
     * Teknis. Pemetaannya hidup di SchemeOwnershipService, sehingga pemanggil
     * cukup menyebut peran yang dituju — 'admin' atau 'technical' — tanpa perlu
     * tahu kode role mana yang berlaku untuk skema tersebut.
     */
    public function sendToSchemeOwner(
        CertificationApplication $application,
        string $kind,
        string $type,
        string $title,
        string $message,
        ?string $url = null,
        array $data = []
    ): void {
        $application->loadMissing('scheme');
        $template = $application->scheme?->review_template;

        $ownership = app(SchemeOwnershipService::class);

        $roleCode = $kind === 'technical'
            ? $ownership->technicalRoleFor($template)
            : $ownership->adminRoleFor($template);

        $this->sendToRole($roleCode, $type, $title, $message, $url, $data);
    }
}
