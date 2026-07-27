@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-header">
        <h1>{{ $title }}</h1>

        <p>{{ $description }}</p>
    </div>

    <section class="content-card">
        <strong>
            Modul sedang disiapkan.
        </strong>

        <p style="margin: 10px 0 0; color: var(--muted);">
            Hak akses dan route halaman ini sudah aktif.
            Fitur bisnis akan dibangun sesuai urutan workflow.
        </p>
    </section>
@endsection