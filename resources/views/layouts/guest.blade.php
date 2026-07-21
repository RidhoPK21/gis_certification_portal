<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Portal Sertifikasi GIS')</title>
    <style>
        :root {
            --navy: #082f54;
            --blue: #0b70b8;
            --border: #d9e3ec;
            --text: #152a3d;
            --muted: #6a7c8d;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--text);
            background: #f3f7fb;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
        }

        .container {
            width: min(1400px, calc(100% - 32px));
            margin: 0 auto;
        }

        .guest-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
        }

        .guest-card {
            width: min(440px, 100%);
            padding: 32px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 12px 32px rgba(15, 45, 75, 0.10);
        }

        .brand-mark {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: #073a67;
            color: #fff;
            font-weight: 700;
        }

        h1, h2 { color: var(--navy); }

        .muted { color: var(--muted); }
        .small { font-size: 12px; }
        .mt-2 { margin-top: 18px; }

        .detail-list {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 8px 14px;
            margin: 16px 0;
        }
        .detail-list dt { color: var(--muted); font-size: 13px; }
        .detail-list dd { margin: 0; font-size: 14px; }

        .form-group { margin-bottom: 14px; }
        .form-label { display: block; margin-bottom: 6px; font-weight: 650; font-size: 13px; }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 18px;
            border: 0;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary { background: var(--blue); color: #fff; }
        .btn-block { display: flex; width: 100%; }

        .alert {
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid transparent;
            line-height: 1.55;
        }
        .alert-danger { border-color: #f0c2c2; background: #fdf2f2; color: #a12626; }
    </style>
</head>
<body>
@yield('body')
</body>
</html>
