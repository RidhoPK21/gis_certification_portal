@extends('layouts.auth')

@section('title', 'Masuk')

@section('content')
    <section class="auth-card">
        <div class="auth-heading">
            <h2>Selamat datang</h2>

            <p>
                Masuk menggunakan akun SystemGIS Anda.
            </p>
        </div>

        @if (session('status'))
    <div
        style="
            margin-bottom: 20px;
            padding: 13px 15px;
            border: 1px solid #a9dfbd;
            border-radius: 11px;
            color: #17663a;
            background: #ecfbf2;
            font-size: 14px;
            line-height: 1.6;
        "
    >
        {{ session('status') }}
    </div>
@endif

        <form
            method="POST"
            action="{{ route('login') }}"
        >
            @csrf

            <div class="form-group">
                <label
                    class="form-label"
                    for="email"
                >
                    Alamat Email
                </label>

                <input
                    class="form-control"
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="nama@perusahaan.com"
                    autocomplete="email"
                    required
                    autofocus
                >

                @error('email')
                    <div class="form-error">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="form-group">
                <label
                    class="form-label"
                    for="password"
                >
                    Kata Sandi
                </label>

                <input
                    class="form-control"
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Masukkan kata sandi"
                    autocomplete="current-password"
                    required
                >

                @error('password')
                    <div class="form-error">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="form-options">
                <label class="remember-option">
                    <input
                        type="checkbox"
                        name="remember"
                        value="1"
                    >

                    <span>Ingat saya</span>
                </label>
            </div>

            <button
                class="login-button"
                type="submit"
            >
                Masuk ke SystemGIS
            </button>
        </form>

        <div class="login-help">
    Belum memiliki akun?

    <a
        href="{{ route('register') }}"
        style="
            color: #0878c9;
            font-weight: 700;
            text-decoration: none;
        "
    >
        Daftar sekarang
    </a>
</div>

<div
    class="login-help"
    style="margin-top: 10px;"
>
    Akun bermasalah?
    Hubungi administrator SystemGIS.
</div>
    </section>
@endsection