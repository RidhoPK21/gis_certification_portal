@extends('layouts.auth')

@section('title', 'Aktivasi Akun Staf')

@section('content')
    <section class="auth-card">
        <div class="auth-heading">
            <h2>Aktivasi akun staf</h2>

            <p>
                Masukkan kode aktivasi dari email undangan Anda,
                lalu tentukan kata sandi Anda sendiri.
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
            action="{{ route('account.activate.submit') }}"
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
                    class="form-control @error('email') is-invalid @enderror"
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    placeholder="nama@perusahaan.com"
                    autocomplete="email"
                    required
                >

                @error('email')
                    <div class="form-error">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            @include('auth.partials.otp-code-input')

            <div class="form-group">
                <label
                    class="form-label"
                    for="password"
                >
                    Kata Sandi Baru
                </label>

                <input
                    class="form-control @error('password') is-invalid @enderror"
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Minimal 12 karakter, kombinasi huruf & angka"
                    autocomplete="new-password"
                    minlength="12"
                    required
                >

                <div class="form-help" style="margin-top: 6px; color: #687b8e; font-size: 12px;">
                    Minimal 12 karakter dan harus mengandung huruf serta angka.
                </div>

                @error('password')
                    <div class="form-error">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="form-group">
                <label
                    class="form-label"
                    for="password_confirmation"
                >
                    Konfirmasi Kata Sandi
                </label>

                <input
                    class="form-control"
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    placeholder="Ulangi kata sandi"
                    autocomplete="new-password"
                    required
                >
            </div>

            <button
                class="login-button"
                type="submit"
            >
                Aktifkan Akun
            </button>
        </form>

        <form
            method="POST"
            action="{{ route('account.activate.resend') }}"
            style="margin-top: 16px;"
        >
            @csrf

            <input
                type="hidden"
                name="email"
                value="{{ old('email', $email) }}"
            >

            <button
                type="submit"
                style="
                    width: 100%;
                    min-height: 46px;
                    border: 1px solid #cbd9e5;
                    border-radius: 12px;
                    color: #0878c9;
                    background: #ffffff;
                    font-weight: 700;
                    cursor: pointer;
                "
            >
                Kirim ulang kode aktivasi
            </button>
        </form>

        @include('auth.partials.spam-notice')

        <div
            class="login-help"
            style="margin-top: 16px;"
        >
            Kode berlaku {{ config('systemgis.otp_ttl_minutes') }} menit.
            Akun staf hanya dapat dibuat oleh Superadmin SystemGIS.
        </div>
    </section>
@endsection
