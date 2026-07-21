@extends('layouts.guest')

@section('title', 'Cek Status Permohonan Sertifikasi')

@section('body')
    <style>
        .track-header {
            background: #ffffff;
            border-bottom: 1px solid #dce5ee;
        }
        .track-nav {
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; padding: 16px 0; flex-wrap: wrap;
        }
        .track-brand { display: flex; align-items: center; gap: 12px; color: #062f57; font-weight: 700; }
        .track-main { padding: 32px 0 48px; }
        .track-card {
            width: min(560px, 100%); margin: 0 auto; padding: 28px;
            border: 1px solid var(--border); border-radius: 16px; background: #ffffff;
            box-shadow: 0 12px 32px rgba(15, 45, 75, 0.08);
        }
        .track-result { width: min(760px, 100%); margin: 24px auto 0; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; background: #e6f2fb; color: #0b5a92; font-size: 13px; font-weight: 700; }
        .tl-item { display: flex; gap: 12px; padding-bottom: 14px; }
        .tl-dot { width: 12px; height: 12px; margin-top: 4px; border-radius: 999px; background: #cbd8e3; flex: 0 0 auto; }
        .tl-item.done .tl-dot { background: #1c7a45; }
        .tl-item.current .tl-dot { background: #0b70b8; box-shadow: 0 0 0 4px rgba(11,112,184,.15); }
        .tl-item h4 { margin: 0; font-size: 14px; color: var(--navy); }
        .tl-item p { margin: 2px 0 0; color: var(--muted); font-size: 13px; }
        .alert-success { border: 1px solid #b6e0c2; background: #f2fbf5; color: #1c7a45; padding: 12px 14px; border-radius: 10px; }
        .btn-light { border: 1px solid #cbd8e3; background: #fff; color: var(--navy); }
    </style>

    <header class="track-header">
        <div class="container track-nav">
            <a class="track-brand" href="{{ route('public.home') }}" style="text-decoration:none">
                <span class="brand-mark">GIS</span>
                <span>PT Global Inspeksi Sertifikasi<br><small class="muted">Cek Status Permohonan</small></span>
            </a>
            <a class="btn btn-primary" href="{{ route('login') }}">Masuk Portal</a>
        </div>
    </header>

    <main class="track-main">
        <div class="track-card">
            <h2>Cek Status Permohonan</h2>
            <p class="muted">Masukkan nomor order. Halaman publik hanya menampilkan tahapan umum tanpa dokumen atau data sensitif.</p>
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first('order_number') }}</div>
            @endif
            <form method="post" action="{{ route('public.track') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="order_number">Nomor Order</label>
                    <input class="form-control" id="order_number" name="order_number" value="{{ old('order_number') }}" placeholder="Contoh: 013/LSSM-GIS/V/2026" required autocomplete="off">
                </div>
                <button class="btn btn-primary btn-block">Cek Status</button>
            </form>
        </div>

        @isset($result)
            <div class="track-result">
                <div class="track-card" style="width:100%">
                    <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:16px">
                        <div>
                            <h2 style="margin:0">Status {{ $result['order_number'] }}</h2>
                            <p class="muted" style="margin:4px 0 0">{{ $result['scheme'] }} · Dikirim {{ $result['submitted_at'] ?: '-' }}</p>
                        </div>
                        <span class="badge">{{ $result['status_label'] }}</span>
                    </div>
                    @foreach ($result['timeline'] as $item)
                        <div class="tl-item {{ $item['state'] }}">
                            <span class="tl-dot"></span>
                            <div>
                                <h4>{{ $item['label'] }}</h4>
                                <p>{{ $item['state'] === 'done' ? 'Tahap telah selesai.' : ($item['state'] === 'current' ? 'Sedang diproses oleh tim terkait.' : 'Menunggu tahap sebelumnya selesai.') }}</p>
                            </div>
                        </div>
                    @endforeach
                    @if ($result['certificate_available'])
                        <div class="alert-success mt-2"><strong>Sertifikat telah tersedia.</strong> Pengunduhan tetap melalui login klien atau link aman dari Tim Teknis.</div>
                    @endif
                </div>
            </div>
        @endisset
    </main>
@endsection
