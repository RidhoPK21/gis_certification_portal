<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'Masuk') — {{ config('app.name') }}
    </title>

    <style>
        :root {
            --navy: #07365f;
            --blue: #0878c9;
            --light-blue: #edf7ff;
            --border: #d6e2ec;
            --text: #14263a;
            --muted: #687b8e;
            --danger: #b42318;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background: #f3f8fc;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        button,
        input {
            font: inherit;
        }

        .auth-page {
            display: grid;
            grid-template-columns:
                minmax(360px, 0.95fr)
                minmax(440px, 1.05fr);
            min-height: 100vh;
        }

        .auth-side {
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
            padding: 64px;
            color: #ffffff;
            background:
                linear-gradient(
                    145deg,
                    #052b4e,
                    #0968aa
                );
        }

        .auth-side::before {
            content: "";
            position: absolute;
            width: 380px;
            height: 380px;
            top: -170px;
            right: -160px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
        }

        .auth-side-content {
            position: relative;
            z-index: 1;
            width: min(520px, 100%);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 54px;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 56px;
            height: 56px;
            border: 1px solid rgba(255, 255, 255, 0.28);
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.12);
            font-weight: 800;
        }

        .brand-title {
            font-size: 18px;
            font-weight: 800;
        }

        .brand-subtitle {
            display: block;
            margin-top: 3px;
            font-size: 12px;
            font-weight: 400;
            opacity: 0.75;
        }

        .auth-side h1 {
            max-width: 500px;
            margin: 0 0 20px;
            font-size: clamp(36px, 4vw, 54px);
            line-height: 1.08;
            letter-spacing: -0.035em;
        }

        .auth-side p {
            max-width: 480px;
            margin: 0;
            color: rgba(255, 255, 255, 0.78);
            font-size: 16px;
            line-height: 1.75;
        }

        .security-note {
            margin-top: 40px;
            padding: 16px 18px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.84);
            font-size: 13px;
            line-height: 1.6;
        }

        .auth-content {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 42px;
        }

        .auth-wrapper {
            width: min(440px, 100%);
        }

        .auth-card {
            padding: 38px;
            border: 1px solid var(--border);
            border-radius: 22px;
            background: #ffffff;
            box-shadow:
                0 24px 60px rgba(21, 55, 84, 0.11);
        }

        .auth-heading {
            margin-bottom: 30px;
        }

        .auth-heading h2 {
            margin: 0 0 9px;
            color: var(--navy);
            font-size: 29px;
        }

        .auth-heading p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #213a52;
            font-size: 14px;
            font-weight: 700;
        }

        .form-control {
            width: 100%;
            height: 51px;
            padding: 0 15px;
            border: 1px solid #cbd9e5;
            border-radius: 12px;
            outline: none;
            color: var(--text);
            background: #ffffff;
        }

        .form-control:focus {
            border-color: var(--blue);
            box-shadow:
                0 0 0 4px rgba(8, 120, 201, 0.11);
        }

        .form-error {
            margin-top: 7px;
            color: var(--danger);
            font-size: 13px;
        }

        .form-options {
            margin: 5px 0 25px;
        }

        .remember-option {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted);
            font-size: 14px;
        }

        .remember-option input {
            width: 17px;
            height: 17px;
            accent-color: var(--blue);
        }

        .login-button {
            width: 100%;
            min-height: 52px;
            border: 0;
            border-radius: 12px;
            color: #ffffff;
            background:
                linear-gradient(
                    135deg,
                    #075e9e,
                    #087dce
                );
            font-weight: 750;
            cursor: pointer;
        }

        .login-button:hover {
            filter: brightness(1.04);
        }

        .login-help {
            margin-top: 20px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
            text-align: center;
        }

        @media (max-width: 900px) {
            .auth-page {
                display: block;
            }

            .auth-side {
                display: none;
            }

            .auth-content {
                min-height: 100vh;
                padding: 24px 18px;
            }

            .auth-card {
                padding: 30px 24px;
            }
        }
    </style>
</head>

<body>
    <div class="auth-page">
        <aside class="auth-side">
            <div class="auth-side-content">
                <div class="brand">
                    <div class="brand-mark">
                        GIS
                    </div>

                    <div class="brand-title">
                        SystemGIS

                        <span class="brand-subtitle">
                            {{ config('systemgis.company_name') }}
                        </span>
                    </div>
                </div>

                <h1>
                    Portal Permohonan Sertifikasi Terintegrasi
                </h1>

                <p>
                    Mengelola permohonan, peninjauan dokumen,
                    pembayaran, audit, hingga penerbitan
                    sertifikat dalam satu sistem.
                </p>

                <div class="security-note">
                    Gunakan akun resmi yang diberikan oleh
                    administrator. Aktivitas penting pada sistem
                    akan dicatat untuk keamanan dan ketertelusuran.
                </div>
            </div>
        </aside>

        <main class="auth-content">
            <div class="auth-wrapper">
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>