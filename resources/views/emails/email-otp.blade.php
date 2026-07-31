@php
    $isInvite = $purpose === \App\Models\EmailOtp::PURPOSE_ADMIN_INVITE;
    $isReset = $purpose === \App\Models\EmailOtp::PURPOSE_PASSWORD_RESET;

    $entryUrl = match (true) {
        $isInvite => route('account.activate.show', ['email' => $recipientUser->email]),
        $isReset => route('password.reset.show', ['email' => $recipientUser->email]),
        default => route('register.verify.show'),
    };

    $heading = match (true) {
        $isInvite => 'Aktivasi Akun Staf SystemGIS',
        $isReset => 'Reset Kata Sandi',
        default => 'Kode Verifikasi Email',
    };

    $buttonLabel = match (true) {
        $isInvite => 'Buka Halaman Aktivasi',
        $isReset => 'Buka Halaman Reset',
        default => 'Buka Halaman Verifikasi',
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #152a3d; line-height: 1.6;">
    <h2 style="color: #082f54;">
        {{ $heading }}
    </h2>

    <p>Halo {{ $recipientUser->name }},</p>

    <p>
        @if ($isInvite)
            Akun staf SystemGIS telah dibuat untuk email ini. Gunakan kode di bawah
            untuk mengaktifkan akun dan menentukan kata sandi Anda sendiri.
        @elseif ($isReset)
            Permintaan reset kata sandi diterima untuk akun ini. Gunakan kode di bawah
            untuk menentukan kata sandi baru Anda.
        @else
            Gunakan kode di bawah untuk memverifikasi alamat email Anda dan
            menyelesaikan pendaftaran akun klien.
        @endif
    </p>

    <p style="margin:24px 0;">
        <span style="display:inline-block;padding:14px 24px;background:#edf7ff;border:1px solid #b9d9f2;border-radius:10px;font-family:'Courier New',monospace;font-size:30px;font-weight:bold;letter-spacing:8px;color:#082f54;">
            {{ $code }}
        </span>
    </p>

    <p>
        Kode berlaku selama {{ config('systemgis.otp_ttl_minutes') }} menit.
    </p>

    <p>
        <a href="{{ $entryUrl }}"
           style="display:inline-block;padding:10px 18px;background:#0b70b8;color:#fff;border-radius:8px;text-decoration:none;">
            {{ $buttonLabel }}
        </a>
    </p>

    <p style="font-size:13px;color:#6a7c8d;">
        Jika Anda tidak merasa melakukan permintaan ini, abaikan email ini dan
        jangan bagikan kode tersebut kepada siapa pun.
    </p>

    <p style="font-size:13px;color:#6a7c8d;background:#fffaed;border:1px solid #f2d799;border-radius:8px;padding:10px 12px;">
        Email ini masuk ke folder <strong>Spam</strong> atau <strong>Promosi</strong>?
        Tekan &ldquo;Bukan spam&rdquo; dan tambahkan alamat pengirim ke kontak Anda,
        agar pemberitahuan berikutnya langsung masuk ke kotak masuk.
    </p>

    <hr style="border:none;border-top:1px solid #d9e3ec;margin:24px 0;">
    <p style="font-size:12px;color:#6a7c8d;">
        {{ app(\App\Services\SettingService::class)->get('company_name') }}
    </p>
</body>
</html>
