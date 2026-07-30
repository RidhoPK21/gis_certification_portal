@extends('layouts.auth')

@section('title', 'Verifikasi Email')

@section('content')
    <section class="auth-card">
        <div class="auth-heading">
            <h2>Verifikasi email Anda</h2>

            <p>
                Kami mengirim kode 6 angka ke
                <strong>{{ $maskedEmail }}</strong>.
                Masukkan kode tersebut untuk mengaktifkan akun Anda.
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
            action="{{ route('register.verify.submit') }}"
        >
            @csrf

            @include('auth.partials.otp-code-input')

            <button
                class="login-button"
                type="submit"
            >
                Verifikasi Email
            </button>
        </form>

        <form
            method="POST"
            action="{{ route('register.verify.resend') }}"
            style="margin-top: 16px;"
        >
            @csrf

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
                Kirim ulang kode
            </button>
        </form>

        <div
            class="login-help"
            style="margin-top: 20px;"
        >
            Kode berlaku {{ config('systemgis.otp_ttl_minutes') }} menit.
            Cek juga folder spam bila email belum masuk.
        </div>
    </section>
@endsection
