@extends('layouts.guest')

@section('title', 'Akses Sertifikat Final')

@section('body')
    <div class="guest-wrap">
        <div class="guest-card">
            <span class="brand-mark">GIS</span>
            <h2 class="mt-2">Unduh Sertifikat Final</h2>
            <p class="muted">Masukkan password yang diberikan Tim Teknis GIS. Setiap percobaan akses dicatat.</p>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <dl class="detail-list">
                <dt>Order</dt><dd>{{ $application->order_number }}</dd>
                <dt>Perusahaan</dt><dd>{{ $application->company_name }}</dd>
                <dt>Berlaku link</dt><dd>Sampai {{ $link->expires_at->format('d M Y H:i') }}</dd>
            </dl>

            <form method="post" action="{{ route('certificate.final.download', $token) }}" class="mt-2">
                @csrf
                <div class="form-group">
                    <label class="form-label">Password Sertifikat</label>
                    <input class="form-control" type="password" name="password" required autofocus>
                </div>
                <button class="btn btn-primary btn-block">Verifikasi &amp; Download</button>
            </form>
        </div>
    </div>
@endsection
