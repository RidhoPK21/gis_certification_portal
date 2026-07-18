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
        @yield('title', 'Dashboard') — {{ config('app.name') }}
    </title>

    <style>
        :root {
            --navy: #082f54;
            --blue: #0b70b8;
            --blue-light: #eaf5fc;
            --background: #f3f7fb;
            --border: #d9e3ec;
            --text: #152a3d;
            --muted: #6a7c8d;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: var(--text);
            background: var(--background);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        button {
            font: inherit;
        }

        .app-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            z-index: 20;
            width: 270px;
            overflow-y: auto;
            color: #ffffff;
            background:
                linear-gradient(
                    180deg,
                    #062c4e,
                    #073a64
                );
        }

        .sidebar-brand {
            padding: 25px 23px;
            border-bottom:
                1px solid rgba(255, 255, 255, 0.10);
        }

        .sidebar-brand strong {
            display: block;
            font-size: 19px;
        }

        .sidebar-brand small {
            display: block;
            margin-top: 5px;
            color: rgba(255, 255, 255, 0.66);
        }

        .sidebar-navigation {
            padding: 18px 13px 30px;
        }

        .navigation-section {
            margin-top: 20px;
        }

        .navigation-section:first-child {
            margin-top: 0;
        }

        .navigation-title {
            padding: 0 12px 8px;
            color: rgba(255, 255, 255, 0.49);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.09em;
            text-transform: uppercase;
        }

        .navigation-link {
            display: flex;
            align-items: center;
            gap: 11px;
            min-height: 44px;
            margin: 3px 0;
            padding: 10px 12px;
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.78);
            text-decoration: none;
            font-size: 14px;
            font-weight: 650;
        }

        .navigation-link::before {
            content: "";
            width: 8px;
            height: 8px;
            flex: 0 0 auto;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.36);
        }

        .navigation-link:hover,
        .navigation-link.active {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.12);
        }

        .navigation-link.active::before {
            background: #59c8ff;
            box-shadow:
                0 0 0 4px rgba(89, 200, 255, 0.15);
        }

        .main-area {
            width: calc(100% - 270px);
            margin-left: 270px;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 72px;
            padding: 0 30px;
            border-bottom: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.96);
        }

        .topbar-role {
            color: var(--muted);
            font-size: 13px;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .user-information {
            text-align: right;
        }

        .user-information strong {
            display: block;
            font-size: 14px;
        }

        .user-information small {
            color: var(--muted);
        }

        .logout-button {
            padding: 9px 14px;
            border: 1px solid #cbd8e3;
            border-radius: 9px;
            color: var(--navy);
            background: #ffffff;
            font-weight: 700;
            cursor: pointer;
        }

        .page-content {
            width: min(1320px, calc(100% - 44px));
            margin: 0 auto;
            padding: 32px 0 48px;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            margin: 0 0 8px;
            color: var(--navy);
            font-size: 30px;
        }

        .page-header p {
            margin: 0;
            color: var(--muted);
            line-height: 1.65;
        }

        .content-card {
            padding: 26px;
            border: 1px solid var(--border);
            border-radius: 17px;
            background: #ffffff;
            box-shadow:
                0 10px 30px rgba(20, 53, 82, 0.06);
        }

        /* ---- Utility classes untuk modul (skema, form builder, dll) ---- */
        .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }

        .page-head h1 {
            margin: 0 0 8px;
            color: var(--navy);
            font-size: 28px;
        }

        .page-head p {
            margin: 0;
            color: var(--muted);
            line-height: 1.6;
        }

        .card {
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(20, 53, 82, 0.05);
        }

        .card h2 {
            margin: 0 0 16px;
            color: var(--navy);
            font-size: 18px;
        }

        .grid-2 {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .grid-3 {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
        }

        .flex {
            display: flex;
            align-items: center;
        }

        .gap-1 {
            gap: 10px;
        }

        .mt-1 {
            margin-top: 12px;
        }

        .mt-2 {
            margin-top: 20px;
        }

        .muted {
            color: var(--muted);
        }

        .small {
            font-size: 12px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            color: var(--text);
            font-size: 13px;
            font-weight: 650;
        }

        .form-control,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #ffffff;
            color: var(--text);
            font-size: 14px;
        }

        .form-textarea {
            min-height: 90px;
            resize: vertical;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 16px;
            border: 1px solid transparent;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--blue);
            color: #ffffff;
        }

        .btn-light {
            border-color: #cbd8e3;
            background: #ffffff;
            color: var(--navy);
        }

        .btn-sm {
            padding: 7px 12px;
            font-size: 13px;
        }

        .badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }

        .badge-success {
            background: #e2f6ea;
            color: #1c7a45;
        }

        .badge-neutral {
            background: #eef2f6;
            color: #64748b;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table th,
        .table td {
            padding: 11px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        .table th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #f7fafc;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid transparent;
            line-height: 1.6;
        }

        .alert-info {
            border-color: #bfe0f5;
            background: #eef7fd;
            color: #0b5a92;
        }

        .alert-success {
            border-color: #b6e0c2;
            background: #f2fbf5;
            color: #1c7a45;
        }

        details summary {
            cursor: pointer;
            padding: 6px 0;
        }

        /* ---- Utility tambahan Fase 3 (permohonan klien) ---- */
        :root {
            --line: var(--border);
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .gap-2 {
            gap: 18px;
        }

        .wrap {
            flex-wrap: wrap;
        }

        .justify-between {
            justify-content: space-between;
        }

        .items-center {
            align-items: center;
        }

        .text-right {
            text-align: right;
        }

        .text-success {
            color: #1c7a45;
        }

        .required {
            color: #c0392b;
        }

        .empty {
            padding: 18px;
            color: var(--muted);
            text-align: center;
        }

        .badge-warning {
            background: #fdf0d9;
            color: #9a6a12;
        }

        .badge-info {
            background: #e6f2fb;
            color: #0b5a92;
        }

        .badge-danger {
            background: #fdecec;
            color: #a12626;
        }

        .alert-warning {
            border-color: #f0dcb0;
            background: #fdf8ee;
            color: #8a6212;
        }

        .btn-block {
            display: flex;
            width: 100%;
        }

        .stat-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            margin-bottom: 20px;
        }

        .stat-card {
            padding: 18px 20px;
            border: 1px solid var(--border);
            border-radius: 14px;
            background: #ffffff;
        }

        .stat-card small {
            color: var(--muted);
            font-size: 12px;
        }

        .stat-card strong {
            display: block;
            margin-top: 6px;
            color: var(--navy);
            font-size: 26px;
        }

        .scheme-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        }

        .scheme-card {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 20px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: #ffffff;
        }

        .scheme-card h3 {
            margin: 4px 0 0;
            color: var(--navy);
        }

        .scheme-card p {
            flex: 1;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.55;
        }

        .scheme-code {
            align-self: flex-start;
            padding: 3px 10px;
            border-radius: 999px;
            background: var(--blue-light);
            color: var(--blue);
            font-size: 12px;
            font-weight: 700;
        }

        .detail-list {
            display: grid;
            grid-template-columns: 130px 1fr;
            gap: 8px 14px;
            margin: 0;
        }

        .detail-list dt {
            color: var(--muted);
            font-size: 13px;
        }

        .detail-list dd {
            margin: 0;
            font-size: 14px;
        }

        .progress {
            height: 9px;
            border-radius: 999px;
            background: #e6edf3;
            overflow: hidden;
        }

        .progress span {
            display: block;
            height: 100%;
            background: var(--blue);
        }

        .timeline-item {
            display: flex;
            gap: 12px;
            padding-bottom: 14px;
        }

        .timeline-dot {
            display: block;
            width: 12px;
            height: 12px;
            margin-top: 4px;
            border-radius: 999px;
            background: var(--blue);
        }

        .wizard-layout {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 18px;
            align-items: start;
        }

        .wizard-nav {
            position: sticky;
            top: 88px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .wizard-nav a {
            padding: 7px 10px;
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            font-size: 13px;
        }

        .wizard-nav a:hover {
            background: var(--blue-light);
        }

        .field-grid {
            display: grid;
            gap: 16px;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        }

        .field-full {
            grid-column: 1 / -1;
        }

        .form-help {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .error-text {
            margin-top: 5px;
            color: #c0392b;
            font-size: 12px;
        }

        .doc-item {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            align-items: flex-start;
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .doc-item h4 {
            margin: 0 0 4px;
        }

        .doc-item p {
            margin: 0;
            color: var(--muted);
            font-size: 12px;
        }

        .hidden {
            display: none;
        }

        @media (max-width: 720px) {
            .wizard-layout {
                grid-template-columns: 1fr;
            }

            .wizard-nav {
                position: static;
            }
        }

        code {
            padding: 1px 6px;
            border-radius: 6px;
            background: #eef2f6;
            font-size: 12px;
        }

        @media (max-width: 900px) {
            .sidebar {
                position: static;
                width: 100%;
                max-height: none;
            }

            .app-shell {
                display: block;
            }

            .main-area {
                width: 100%;
                margin-left: 0;
            }

            .topbar {
                padding: 14px 18px;
            }

            .page-content {
                width: min(100% - 28px, 1320px);
            }
        }
    </style>
</head>

<body>
    @php
        $user = auth()->user();

        $user->loadMissing('roles');

        $userRoles = $user
            ->roles
            ->pluck('code')
            ->all();

        $primaryRole = $user
            ->roles
            ->sortBy('sort_order')
            ->first();

        $navigation = collect(config('navigation'))
            ->filter(function (array $item) use ($userRoles) {
                return count(
                    array_intersect(
                        $userRoles,
                        $item['roles']
                    )
                ) > 0;
            })
            ->groupBy('section');
    @endphp

    <div class="app-shell">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <strong>SystemGIS</strong>

                <small>
                    Certification Portal
                </small>
            </div>

            <nav class="sidebar-navigation">
                @foreach ($navigation as $section => $items)
                    <div class="navigation-section">
                        <div class="navigation-title">
                            {{ $section }}
                        </div>

                        @foreach ($items as $item)
                            <a
                                class="navigation-link {{ request()->routeIs($item['active']) ? 'active' : '' }}"
                                href="{{ route($item['route']) }}"
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </nav>
        </aside>

        <div class="main-area">
            <header class="topbar">
                <div>
                    <strong>
                        {{ config('systemgis.company_name') }}
                    </strong>

                    <div class="topbar-role">
                        {{ $primaryRole?->name ?? 'Tanpa Role' }}
                    </div>
                </div>

                <div class="topbar-user">
                    <div class="user-information">
                        <strong>{{ $user->name }}</strong>
                        <small>{{ $user->email }}</small>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('logout') }}"
                    >
                        @csrf

                        <button
                            class="logout-button"
                            type="submit"
                        >
                            Keluar
                        </button>
                    </form>
                </div>
            </header>

            <main class="page-content">
                @if (session('success'))
                    <div class="alert alert-success mt-1" style="margin-bottom: 20px;">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert" style="margin-bottom: 20px; border-color: #f0c2c2; background: #fdf2f2; color: #a12626;">
                        <strong>Periksa kembali isian berikut:</strong>
                        <ul style="margin: 8px 0 0; padding-left: 18px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
</body>
</html>