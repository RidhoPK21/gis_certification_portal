@extends('layouts.app')

@section('title', 'Pilih Skema Sertifikasi')

@section('content')
    <div class="page-head">
        <div>
            <h1>Pilih Skema Sertifikasi</h1>
            <p>Setiap skema memiliki form dan dokumen sendiri. Anda hanya akan melihat kebutuhan yang relevan.</p>
        </div>
    </div>

    <div class="scheme-grid">
        @foreach ($schemes as $scheme)
            <article class="scheme-card">
                <span class="scheme-code">{{ $scheme->code }}</span>
                <h3>{{ $scheme->short_name }}</h3>
                <div class="small muted">{{ $scheme->standard }}</div>
                <p>{{ $scheme->description }}</p>
                <a class="btn btn-primary btn-block" href="{{ route('client.applications.create', $scheme) }}">
                    Mulai Permohonan
                </a>
            </article>
        @endforeach
    </div>
@endsection
