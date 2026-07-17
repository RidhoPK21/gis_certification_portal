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
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>