@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-header">
        <h1>
            Selamat datang, {{ auth()->user()->name }}
        </h1>

        <p>
            Anda berhasil masuk ke SystemGIS.
            Menu pada sidebar otomatis disesuaikan dengan
            role dan hak akses akun.
        </p>
    </div>

    <section class="content-card">
        <strong>
            Akses aktif:
        </strong>

        <p style="margin-bottom: 0; color: #6a7c8d;">
            {{ auth()->user()->primaryRole()?->name ?? 'Role belum ditetapkan' }}
        </p>
    </section>
@endsection