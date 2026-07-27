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

    <script>
        (function () {
            try {
                if (localStorage.getItem('theme') === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (e) {}
        })();
    </script>

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
            --surface: #ffffff;
            --surface-alt: #f3f7fb;
            --shadow: rgba(20, 53, 82, 0.06);
            color-scheme: light;
        }

        :root[data-theme="dark"] {
            --navy: #8ec9f2;
            --blue-light: rgba(11, 112, 184, 0.20);
            --background: #0d1b2a;
            --border: #263b52;
            --text: #e7eef5;
            --muted: #93a7ba;
            --surface: #17293c;
            --surface-alt: #1c3149;
            --shadow: rgba(0, 0, 0, 0.35);
            color-scheme: dark;
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
            background: var(--surface);
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
            border: 1px solid var(--border);
            border-radius: 9px;
            color: var(--navy);
            background: var(--surface);
            font-weight: 700;
            cursor: pointer;
        }

        .notif-bell {
            position: relative;
        }

        .theme-toggle,
        .notif-toggle {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            color: var(--navy);
            cursor: pointer;
        }

        .theme-toggle:hover,
        .notif-toggle:hover {
            background: var(--blue-light);
        }

        .theme-toggle svg {
            display: block;
        }

        .theme-toggle .icon-moon {
            display: none;
        }

        [data-theme="dark"] .theme-toggle .icon-sun {
            display: none;
        }

        [data-theme="dark"] .theme-toggle .icon-moon {
            display: block;
        }

        .notif-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #b42318;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
        }

        .notif-dropdown {
            position: absolute;
            top: 48px;
            right: 0;
            width: 340px;
            max-width: 86vw;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 12px 32px var(--shadow);
            overflow: hidden;
            z-index: 30;
        }

        .notif-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
        }

        .notif-readall {
            border: 0;
            background: transparent;
            color: var(--blue);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .notif-list {
            max-height: 380px;
            overflow-y: auto;
        }

        .notif-item {
            border-bottom: 1px solid var(--border);
        }

        .notif-item button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 11px 14px;
            border: 0;
            background: transparent;
            cursor: pointer;
        }

        .notif-item.is-unread button {
            background: var(--blue-light);
        }

        .notif-item:hover button {
            background: var(--surface-alt);
        }

        .notif-title {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 700;
            color: var(--navy);
        }

        .notif-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #b42318;
        }

        .notif-message {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            color: var(--text);
        }

        .notif-time {
            display: block;
            margin-top: 3px;
            font-size: 11px;
            color: var(--muted);
        }

        .notif-empty {
            padding: 22px 14px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
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
            background: var(--surface);
            box-shadow:
                0 10px 30px var(--shadow);
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
            background: var(--surface);
            box-shadow: 0 8px 24px var(--shadow);
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
            background: var(--surface);
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
            border-color: var(--border);
            background: var(--surface);
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
            background: var(--surface-alt);
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

        .alert-danger {
            border-color: #f0c2c2;
            background: #fdf2f2;
            color: #a12626;
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
            background: var(--surface);
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
            background: var(--surface);
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

        .card-cta {
            color: var(--blue);
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

        /* ---- Utility tambahan Fase 4 (review admin) ---- */
        .btn-blue {
            background: #082f54;
            color: #ffffff;
        }

        .btn-success {
            background: #1c7a45;
            color: #ffffff;
        }

        .btn-danger {
            background: #b5342c;
            color: #ffffff;
        }

        .btn-warning {
            background: #cf8b1a;
            color: #ffffff;
        }

        .text-danger {
            color: #b5342c;
        }

        .tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .tab {
            padding: 9px 14px;
            border-radius: 9px 9px 0 0;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 650;
        }

        .tab:hover,
        .tab.active {
            color: var(--navy);
            background: var(--blue-light);
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

        /* ---- Mode gelap: override warna literal yang tidak lewat variable ---- */
        [data-theme="dark"] .notif-readall,
        [data-theme="dark"] .scheme-code,
        [data-theme="dark"] .card-cta {
            color: #7fc4f2;
        }

        [data-theme="dark"] .progress {
            background: #24384d;
        }

        [data-theme="dark"] code {
            background: #24384d;
            color: var(--text);
        }

        [data-theme="dark"] .error-text,
        [data-theme="dark"] .required,
        [data-theme="dark"] .text-danger {
            color: #f28b82;
        }

        [data-theme="dark"] .badge-success {
            background: rgba(28, 122, 69, 0.25);
            color: #7fdba0;
        }

        [data-theme="dark"] .badge-neutral {
            background: rgba(100, 116, 139, 0.25);
            color: #b7c2cc;
        }

        [data-theme="dark"] .badge-warning {
            background: rgba(207, 139, 26, 0.22);
            color: #f0be6c;
        }

        [data-theme="dark"] .badge-info {
            background: rgba(11, 90, 146, 0.25);
            color: #7fc4f2;
        }

        [data-theme="dark"] .badge-danger {
            background: rgba(180, 35, 24, 0.22);
            color: #f28b82;
        }

        [data-theme="dark"] .alert-info {
            border-color: #164b6e;
            background: rgba(11, 90, 146, 0.18);
            color: #7fc4f2;
        }

        [data-theme="dark"] .alert-success {
            border-color: #1c5e3a;
            background: rgba(28, 122, 69, 0.18);
            color: #7fdba0;
        }

        [data-theme="dark"] .alert-warning {
            border-color: #6b4e12;
            background: rgba(207, 139, 26, 0.18);
            color: #f0be6c;
        }

        [data-theme="dark"] .alert-danger {
            border-color: #6e2020;
            background: rgba(180, 35, 24, 0.18);
            color: #f28b82;
        }

        /*
         * SweetAlert menyuntikkan CSS-nya sendiri saat runtime, jadi selector
         * di bawah sengaja dibuat lebih spesifik agar tidak tertimpa.
         */
        [data-theme="dark"] .swal2-container .swal2-popup {
            background: var(--surface);
            color: var(--text);
        }

        [data-theme="dark"] .swal2-container .swal2-title,
        [data-theme="dark"] .swal2-container .swal2-html-container,
        [data-theme="dark"] .swal2-container .swal2-toast .swal2-title {
            color: var(--text);
        }

        [data-theme="dark"] .swal2-container .swal2-close {
            color: var(--muted);
        }

        [data-theme="dark"] .swal2-container .swal2-input,
        [data-theme="dark"] .swal2-container .swal2-textarea {
            border-color: var(--border);
            background: var(--surface-alt);
            color: var(--text);
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

        $headerNotifications = \App\Models\PortalNotification::where('user_id', $user->id)
            ->latest()
            ->limit(10)
            ->get();

        $headerUnreadCount = \App\Models\PortalNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
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
                                class="navigation-link {{ request()->routeIs(...(array) $item['active']) ? 'active' : '' }}"
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
                    <button type="button" class="theme-toggle" id="theme-toggle" aria-label="Ganti mode gelap/terang">
                        <svg class="icon-sun" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="4.5"></circle>
                            <path d="M12 3v2M12 19v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M3 12h2M19 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"></path>
                        </svg>
                        <svg class="icon-moon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"></path>
                        </svg>
                    </button>

                    <div class="notif-bell" id="notif-bell">
                        <button type="button" class="notif-toggle" id="notif-toggle" aria-label="Notifikasi">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                            @if ($headerUnreadCount > 0)
                                <span class="notif-badge">{{ $headerUnreadCount > 99 ? '99+' : $headerUnreadCount }}</span>
                            @endif
                        </button>

                        <div class="notif-dropdown" id="notif-dropdown" hidden>
                            <div class="notif-head">
                                <strong>Notifikasi</strong>
                                @if ($headerUnreadCount > 0)
                                    <form method="post" action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        <button type="submit" class="notif-readall">Tandai semua dibaca</button>
                                    </form>
                                @endif
                            </div>
                            <div class="notif-list">
                                @forelse ($headerNotifications as $notification)
                                    <form method="post" action="{{ route('notifications.read', $notification) }}" class="notif-item {{ $notification->read_at ? '' : 'is-unread' }}">
                                        @csrf
                                        <button type="submit">
                                            <span class="notif-title">{{ $notification->title }}@unless ($notification->read_at)<span class="notif-dot"></span>@endunless</span>
                                            <span class="notif-message">{{ $notification->message }}</span>
                                            <span class="notif-time">{{ $notification->created_at->diffForHumans() }}</span>
                                        </button>
                                    </form>
                                @empty
                                    <div class="notif-empty">Tidak ada notifikasi.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

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

            <main class="page-content" id="page-content">
                {{-- Data flash untuk SweetAlert (sukses/gagal setelah reload). --}}
                <script type="application/json" id="flash-data">@json(['success' => session('success'), 'errors' => $errors->all()])</script>

                @if (session('success'))
                    <div class="alert alert-success mt-1" id="flash-success-banner" style="margin-bottom: 20px;">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" id="flash-error-banner" style="margin-bottom: 20px;">
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

    <script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
    <script>
    /*
     * Satu handler submit untuk seluruh form:
     *  - data-confirm  : tampilkan dialog konfirmasi SweetAlert dulu untuk
     *                    tombol aksi akhir/konsekuensial.
     *  - data-ajax     : kirim tanpa reload; konten halaman diperbarui otomatis
     *                    dan sukses ditampilkan sebagai toast SweetAlert.
     */
    (function(){
        const token=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const hasSwal=()=>typeof window.Swal!=='undefined';

        function clearAlerts(form){
            form.querySelectorAll('.ajax-alert').forEach(el=>el.remove());
        }

        function showAlert(form,type,message,list){
            const box=document.createElement('div');
            box.className='ajax-alert alert '+(type==='success'?'alert-success':'alert-danger');
            box.style.marginBottom='16px';
            let html='<strong>'+message+'</strong>';
            if(list&&list.length){
                html+='<ul style="margin:8px 0 0;padding-left:18px">'+list.map(m=>'<li>'+m+'</li>').join('')+'</ul>';
            }
            box.innerHTML=html;
            form.prepend(box);
            box.scrollIntoView({behavior:'smooth',block:'center'});
        }

        function flashSuccess(message){
            if(hasSwal()){
                window.Swal.fire({toast:true,position:'top-end',icon:'success',title:message,showConfirmButton:false,timer:2600,timerProgressBar:true});
                return;
            }
            const current=document.getElementById('page-content');
            if(!current)return;
            const box=document.createElement('div');
            box.className='alert alert-success';
            box.style.marginBottom='20px';
            box.textContent=message;
            current.prepend(box);
        }

        /* Notifikasi gagal via SweetAlert (modal), fallback ke banner form. */
        function flashError(message,list,form){
            if(hasSwal()){
                let html='';
                if(list&&list.length){
                    html='<ul style="text-align:left;margin:0;padding-left:18px">'+list.map(m=>'<li>'+m+'</li>').join('')+'</ul>';
                }
                window.Swal.fire({icon:'error',title:message,html:html,confirmButtonText:'Mengerti',confirmButtonColor:'#b42318'});
                return;
            }
            if(form)showAlert(form,'error',message,list);
        }

        async function refreshContent(){
            try{
                const res=await fetch(window.location.href,{headers:{'X-Requested-With':'XMLHttpRequest'}});
                const html=await res.text();
                const doc=new DOMParser().parseFromString(html,'text/html');
                const fresh=doc.getElementById('page-content');
                const current=document.getElementById('page-content');
                if(fresh&&current){
                    current.innerHTML=fresh.innerHTML;
                    current.scrollIntoView({behavior:'smooth',block:'start'});
                }else{
                    window.location.reload();
                }
            }catch(e){
                window.location.reload();
            }
        }

        /*
         * Dialog konfirmasi untuk tombol aksi akhir. Mengembalikan true bila
         * pengguna menekan tombol lanjut. Jatuh ke confirm() bawaan bila
         * SweetAlert gagal dimuat.
         */
        async function askConfirm(form){
            const message=form.getAttribute('data-confirm');
            const danger=form.getAttribute('data-confirm-type')==='danger';
            if(!hasSwal()){
                return window.confirm(message);
            }
            const result=await window.Swal.fire({
                title:form.getAttribute('data-confirm-title')||'Konfirmasi',
                text:message,
                icon:danger?'warning':'question',
                showCancelButton:true,
                confirmButtonText:form.getAttribute('data-confirm-yes')||'Ya, lanjut',
                cancelButtonText:'Batal',
                confirmButtonColor:danger?'#b42318':'#0878c9',
                cancelButtonColor:'#8595a5',
                reverseButtons:true,
            });
            return result.isConfirmed;
        }

        document.addEventListener('submit',async function(event){
            const form=event.target.closest('form');
            if(!form)return;

            const needsConfirm=form.hasAttribute('data-confirm')&&form.dataset.confirmed!=='1';
            const isAjax=form.hasAttribute('data-ajax');
            if(!needsConfirm&&!isAjax)return; // biarkan submit biasa

            // Tahap 1: konfirmasi (untuk tombol akhir).
            if(needsConfirm){
                event.preventDefault();
                if(await askConfirm(form)){
                    form.dataset.confirmed='1';
                    if(form.requestSubmit)form.requestSubmit();else form.submit();
                }
                return;
            }

            // Tahap 2: kirim biasa (sudah dikonfirmasi, bukan AJAX).
            if(!isAjax)return;

            // Tahap 2b: kirim via AJAX tanpa reload.
            event.preventDefault();
            delete form.dataset.confirmed;
            clearAlerts(form);

            const btn=form.querySelector('button[type="submit"], button:not([type="button"]):not([type="reset"])');
            const originalText=btn?btn.innerHTML:'';
            if(btn){btn.disabled=true;btn.innerHTML='Menyimpan...';}

            try{
                const res=await fetch(form.action,{
                    method:'POST',
                    body:new FormData(form),
                    headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':token}
                });

                if(res.status===422){
                    const data=await res.json().catch(()=>({}));
                    const msgs=[];
                    Object.values(data.errors||{}).forEach(arr=>arr.forEach(m=>msgs.push(m)));
                    flashError(data.message||'Periksa kembali isian berikut:',msgs,form);
                }else if(!res.ok){
                    const data=await res.json().catch(()=>({}));
                    flashError(data.message||'Terjadi kesalahan. Coba lagi.',null,form);
                }else{
                    const data=await res.json().catch(()=>({}));
                    await refreshContent();
                    flashSuccess(data.message||'Berhasil disimpan.');
                }
            }catch(e){
                flashError('Koneksi gagal. Coba lagi.',null,form);
            }finally{
                if(btn){btn.disabled=false;btn.innerHTML=originalText;}
            }
        });

        /*
         * Setelah reload (form non-AJAX), ubah flash server jadi SweetAlert:
         * sukses -> toast hijau, gagal/validasi -> modal merah. Banner server
         * tetap ada sebagai fallback bila SweetAlert tidak termuat.
         */
        function showFlashOnLoad(){
            if(!hasSwal())return; // banner server sudah tampil sebagai fallback
            let data={};
            try{data=JSON.parse(document.getElementById('flash-data')?.textContent||'{}');}catch(e){}
            if(data.success){
                document.getElementById('flash-success-banner')?.remove();
                flashSuccess(data.success);
            }
            if(data.errors&&data.errors.length){
                document.getElementById('flash-error-banner')?.remove();
                flashError('Periksa kembali isian berikut:',data.errors);
            }
        }
        if(document.readyState==='loading'){
            document.addEventListener('DOMContentLoaded',showFlashOnLoad);
        }else{
            showFlashOnLoad();
        }
    })();

    /* Dropdown notifikasi di header: buka/tutup + klik luar menutup. */
    (function(){
        const bell=document.getElementById('notif-bell');
        const toggle=document.getElementById('notif-toggle');
        const dropdown=document.getElementById('notif-dropdown');
        if(!bell||!toggle||!dropdown)return;
        toggle.addEventListener('click',function(e){
            e.stopPropagation();
            dropdown.hidden=!dropdown.hidden;
        });
        document.addEventListener('click',function(e){
            if(!dropdown.hidden&&!bell.contains(e.target))dropdown.hidden=true;
        });
        document.addEventListener('keydown',function(e){
            if(e.key==='Escape')dropdown.hidden=true;
        });
    })();

    /* Toggle mode gelap/terang: simpan pilihan di localStorage, default terang. */
    (function(){
        const toggle=document.getElementById('theme-toggle');
        if(!toggle)return;
        toggle.addEventListener('click',function(){
            const isDark=document.documentElement.getAttribute('data-theme')==='dark';
            if(isDark){
                document.documentElement.removeAttribute('data-theme');
                try{localStorage.setItem('theme','light');}catch(e){}
            }else{
                document.documentElement.setAttribute('data-theme','dark');
                try{localStorage.setItem('theme','dark');}catch(e){}
            }
        });
    })();
    </script>
</body>
</html>