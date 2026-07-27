@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-header">
        <h1>
            Selamat datang, {{ $user->name }}
        </h1>

        <p>
            Anda masuk sebagai
            <strong>{{ $primaryRole?->name ?? 'Tanpa Role' }}</strong>.
            Menu pada sidebar dan pintasan di bawah otomatis
            disesuaikan dengan hak akses akun Anda.
        </p>
    </div>

    @if (session('status'))
        <section
            class="alert alert-success"
            style="margin-bottom: 20px;"
        >
            {{ session('status') }}
        </section>
    @endif

    @if ($shortcuts->isEmpty())
        <section class="content-card">
            <strong>Belum ada modul yang bisa diakses.</strong>

            <p style="margin: 10px 0 0; color: var(--muted);">
                Role Anda belum memiliki menu aktif. Hubungi Superadmin
                untuk penetapan hak akses.
            </p>
        </section>
    @else
        <div
            style="
                display: grid;
                gap: 16px;
                grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            "
        >
            @foreach ($shortcuts as $item)
                <a
                    class="content-card"
                    href="{{ route($item['route']) }}"
                    style="
                        display: block;
                        text-decoration: none;
                        color: inherit;
                        transition: box-shadow .15s ease;
                    "
                >
                    <div
                        style="
                            color: var(--muted);
                            font-size: 11px;
                            font-weight: 800;
                            letter-spacing: .08em;
                            text-transform: uppercase;
                        "
                    >
                        {{ $item['section'] }}
                    </div>

                    <div
                        style="
                            margin-top: 6px;
                            color: var(--navy);
                            font-size: 17px;
                            font-weight: 700;
                        "
                    >
                        {{ $item['label'] }}
                    </div>

                    <div class="card-cta" style="margin-top: 10px; font-weight: 700;">
                        Buka &rarr;
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
