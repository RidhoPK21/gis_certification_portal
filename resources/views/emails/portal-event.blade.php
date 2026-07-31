<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; color: #152a3d; line-height: 1.6;">
    <h2 style="color: #082f54;">{{ $mailTitle }}</h2>
    <p>{{ $mailMessage }}</p>

    @if ($actionUrl)
        <p>
            <a href="{{ $actionUrl }}"
               style="display:inline-block;padding:10px 18px;background:#0b70b8;color:#fff;border-radius:8px;text-decoration:none;">
                Buka Portal
            </a>
        </p>
    @endif

    <hr style="border:none;border-top:1px solid #d9e3ec;margin:24px 0;">
    <p style="font-size:12px;color:#6a7c8d;">
        {{ app(\App\Services\SettingService::class)->get('company_name') }}
    </p>
</body>
</html>
