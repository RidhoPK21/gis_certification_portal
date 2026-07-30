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
            <div class="flex justify-between items-center mb-2">
                <h2 class="mb-0">Permohonan Terbaru</h2>
                <div class="flex items-center gap-1">
                    <form method="get" class="mb-0">
                        <select class="form-select btn-sm" name="scheme_id" onchange="this.form.submit()" style="padding:4px 24px 4px 10px;font-size:12px">
                            <option value="">Semua Skema</option>
                            @foreach ($schemes as $scheme)
                                <option value="{{ $scheme->id }}" @selected((int) request('scheme_id') === $scheme->id)>{{ $scheme->short_name }}</option>
                            @endforeach
                        </select>
                    </form>
                    <a class="btn btn-light btn-sm" href="{{ route('client.applications.index') }}" style="text-decoration:none;">Lihat Semua →</a>
                </div>
            </div>
            @forelse ($applications as $app)
                <div style="padding:14px 0 14px 10px;border-bottom:1px solid var(--line);border-left:3px solid {{ $app->scheme->accent_color }};">
                    <div class="flex justify-between gap-1 items-start">
                        <div>
                            <strong>{{ $app->order_number ?: 'Draft #' . $app->id }}</strong>
                            <div class="flex items-center gap-1 mt-1">
                                <x-scheme-badge :scheme="$app->scheme" />
                                <span class="small muted">· {{ $app->company_name }}</span>
                            </div>
                        </div>
                        <span class="badge badge-{{ \App\Enums\ApplicationStatus::tryFrom($app->status)?->tone() ?? 'neutral' }}">
                            @statuslabel($app->status)
                        </span>
                    </div>
                    <div class="mt-1">
                        <a class="btn btn-light btn-sm" href="{{ route('client.applications.show', $app) }}" style="text-decoration:none;">Buka Detail →</a>
                    </div>
                </div>
            @empty
                <div class="empty">Belum ada permohonan. Pilih skema untuk memulai.</div>
            @endforelse
        </section>

        <section class="card">
            <div class="flex justify-between items-center mb-2">
                <h2 class="mb-0">Perlu Tindakan</h2>
                @if ($stats['revision'] > 0)
                    <a class="btn btn-light btn-sm" href="{{ route('client.applications.index') }}" style="text-decoration:none;">Lihat Semua Tindakan →</a>
                @endif
            </div>
            @forelse ($panelItems as $item)
                @if ($item->is_summary)
                    <div style="padding:14px 0 14px 10px;border-bottom:1px solid var(--line);border-left:3px solid var(--border);">
                        <div class="flex justify-between gap-1 items-start">
                            <div>
                                <strong>{{ $item->title }}</strong>
                                <div class="small muted mt-1">{{ $item->description }}</div>
                            </div>
                            <a class="btn btn-light btn-sm" href="{{ $item->button_url }}" style="text-decoration:none;white-space:nowrap;">{{ $item->button_text }} →</a>
                        </div>
                    </div>
                @else
                    <div style="padding:14px 0 14px 10px;border-bottom:1px solid var(--line);border-left:3px solid {{ $item->scheme->accent_color }};">
                        <div class="flex justify-between gap-1 items-start">
                            <div>
                                <strong>{{ $item->title }}</strong>
                                <div class="flex items-center gap-1 mt-1">
                                    <x-scheme-badge :scheme="$item->scheme" />
                                    <span class="badge badge-{{ \App\Enums\ApplicationStatus::tryFrom($item->status)?->tone() ?? 'neutral' }}">
                                        @statuslabel($item->status)
                                    </span>
                                </div>
                                <div class="small muted mt-1">{{ $item->description }}</div>
                            </div>
                            <a class="btn btn-light btn-sm" href="{{ $item->button_url }}" style="text-decoration:none;white-space:nowrap;">{{ $item->button_text }} →</a>
                        </div>
                    </div>
                @endif
            @empty
                <div class="empty">
                    <div>Tidak ada tindakan yang perlu Anda selesaikan saat ini.</div>
                    <div class="small muted mt-1">Perkembangan terbaru tetap dapat dipantau melalui notifikasi di bagian atas.</div>
                </div>
            @endforelse
        </section>
    </div>
@endsection
