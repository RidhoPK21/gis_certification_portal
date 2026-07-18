@extends('layouts.app')

@section('title', 'Dashboard Klien')

@section('content')
    <div class="page-head">
        <div>
            <h1>Halo, {{ auth()->user()->name }}</h1>
            <p>Lihat prioritas Anda dan lanjutkan proses dari satu halaman.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('client.applications.schemes') }}">＋ Ajukan Sertifikasi</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card"><small>Total Permohonan</small><strong>{{ $stats['total'] }}</strong></div>
        <div class="stat-card"><small>Menunggu Admin</small><strong>{{ $stats['waiting'] }}</strong></div>
        <div class="stat-card"><small>Perlu Tindakan</small><strong>{{ $stats['revision'] }}</strong></div>
        <div class="stat-card"><small>Sertifikat Tersedia</small><strong>{{ $stats['final'] }}</strong></div>
    </div>

    <div class="grid-2">
        <section class="card">
            <div class="flex justify-between items-center">
                <h2 class="mb-0">Permohonan Terbaru</h2>
                <a href="{{ route('client.applications.index') }}">Lihat semua</a>
            </div>
            @forelse ($applications as $app)
                <div style="padding:14px 0;border-bottom:1px solid var(--line)">
                    <div class="flex justify-between gap-1">
                        <div>
                            <strong>{{ $app->order_number ?: 'Draft #' . $app->id }}</strong>
                            <div class="small muted">{{ $app->scheme->short_name }} · {{ $app->company_name }}</div>
                        </div>
                        <span class="badge badge-{{ \App\Enums\ApplicationStatus::tryFrom($app->status)?->tone() ?? 'neutral' }}">
                            @statuslabel($app->status)
                        </span>
                    </div>
                    <a class="small" href="{{ route('client.applications.show', $app) }}">Buka detail →</a>
                </div>
            @empty
                <div class="empty">Belum ada permohonan. Pilih skema untuk memulai.</div>
            @endforelse
        </section>
        <section class="card">
            <h2 class="mb-0">Notifikasi</h2>
            @forelse ($notifications as $notification)
                <form method="post" action="{{ route('notifications.read', $notification) }}" style="padding:14px 0;border-bottom:1px solid var(--line)">
                    @csrf
                    <button style="all:unset;cursor:pointer;display:block;width:100%">
                        <strong>{{ $notification->title }}</strong>
                        <div class="small muted">{{ $notification->message }}</div>
                    </button>
                </form>
            @empty
                <div class="empty">Tidak ada notifikasi baru.</div>
            @endforelse
        </section>
    </div>
@endsection
