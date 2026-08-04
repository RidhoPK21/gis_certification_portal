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
        @yield('title', 'Masuk') — {{ $branding['app_name'] }}
    </title>

    <link rel="icon" href="{{ $brandFaviconUrl }}">

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

        @font-face {
            font-family: "Poppins";
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url("{{ asset('fonts/poppins/poppins-400.woff2') }}") format("woff2");
        }

        @font-face {
            font-family: "Poppins";
            font-style: normal;
            font-weight: 600;
            font-display: swap;
            src: url("{{ asset('fonts/poppins/poppins-600.woff2') }}") format("woff2");
        }

        @font-face {
            font-family: "Poppins";
            font-style: normal;
            font-weight: 700;
            font-display: swap;
            src: url("{{ asset('fonts/poppins/poppins-700.woff2') }}") format("woff2");
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
            width: min(560px, 100%);
            margin: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            font-family: "Poppins", Inter, ui-sans-serif, system-ui, sans-serif;
        }

        .brand {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
            margin-bottom: 40px;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 156px;
            height: 156px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 12px 34px rgba(0, 0, 0, 0.28);
            overflow: hidden;
        }

        .brand-mark img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
        }

        .brand-title {
            font-family: "Poppins", sans-serif;
            font-size: 26px;
            font-weight: 700;
            letter-spacing: -0.01em;
        }

        .brand-subtitle {
            display: block;
            margin-top: 4px;
            font-size: 14px;
            font-weight: 400;
            opacity: 0.8;
        }

        .auth-side h1 {
            max-width: 520px;
            margin: 0 0 20px;
            font-family: "Poppins", sans-serif;
            font-size: clamp(30px, 3.2vw, 42px);
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .auth-side p {
            max-width: 460px;
            margin: 0 auto;
            font-family: "Poppins", sans-serif;
            color: rgba(255, 255, 255, 0.82);
            font-size: 16px;
            font-weight: 400;
            line-height: 1.8;
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

        /* Halaman publik memuat hasil pelacakan + QR, jadi butuh kolom lebih lebar. */
        .auth-wrapper--wide {
            width: min(680px, 100%);
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

        .form-control.is-invalid {
            border-color: var(--danger);
            background: #fff7f6;
        }

        .form-control.is-invalid:focus {
            box-shadow:
                0 0 0 4px rgba(180, 35, 24, 0.12);
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

        .secondary-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 50px;
            margin-top: 12px;
            border: 1px solid #b9d3e8;
            border-radius: 12px;
            color: var(--navy);
            background: var(--light-blue);
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }

        .secondary-button:hover {
            border-color: var(--blue);
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
                        <img
                            src="{{ $brandLoginLogoUrl }}"
                            alt="Logo {{ $branding['app_name'] }}"
                        >
                    </div>

                    <div class="brand-title">
                        {{ $branding['app_name'] }}

                        <span class="brand-subtitle">
                            {{ $branding['company_name'] }}
                        </span>
                    </div>
                </div>

                <h1>
                    @yield('side_heading', $branding['login_heading'])
                </h1>

                <p>
                    @yield('side_subheading', $branding['login_subheading'])
                </p>
            </div>
        </aside>

        <main class="auth-content">
            <div class="auth-wrapper @yield('wrapper_class')">
                @yield('content')
            </div>
        </main>
    </div>

    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    @stack('scripts')
</body>
</html>