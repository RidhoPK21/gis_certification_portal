@extends('layouts.auth')

@section('title', 'Daftar Akun Klien')

@section('content')
    <section class="auth-card">
        <div class="auth-heading">
            <h2>Daftar akun Klien</h2>

            <p>
                Buat akun untuk mengajukan dan memantau
                proses sertifikasi perusahaan Anda.
            </p>
        </div>

        <form
            method="POST"
            action="{{ route('register') }}"
        >
            @csrf

            <div class="form-group">
                <label
                    class="form-label"
                    for="name"
                >
                    Nama Lengkap
                </label>

                <input
                    class="form-control"
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    placeholder="Nama lengkap penanggung jawab"
                    autocomplete="name"
                    required
                    autofocus
                >

                @error('name')
                    <div class="form-error">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div class="form-group">
                <label
                    class="form-label"
                    for="company_name"
                >
                    Nama Perusahaan/Instansi
                </label>

                <input
                    class="form-control"
                    id="company_name"
                    type="text"
                    name="company_name"
                    value="{{ old('company_name') }}"
                    placeholder="PT/CV/Instansi Anda"
                    autocomplete="organization"
                    required
                >

                @error('company_name')
                    <div class="form-error">
                        {{ $message }}
                    </div>
                @enderror
            </div>

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
                >

                <div class="form-help" style="margin-top: 6px; color: #687b8e; font-size: 12px;">
                    Gunakan email aktif — kode verifikasi akan dikirim ke alamat ini.
                </div>

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

            @include('auth.partials.turnstile')

            <button
                class="login-button"
                type="submit"
            >
                Daftar sebagai Klien
            </button>
        </form>

        <div
            class="login-help"
            style="margin-top: 24px;"
        >
            Sudah memiliki akun?

            <a
                href="{{ route('login') }}"
                style="
                    color: #0878c9;
                    font-weight: 700;
                    text-decoration: none;
                "
            >
                Masuk sekarang
            </a>
        </div>

        <div
            style="
                margin-top: 16px;
                padding: 12px 14px;
                border: 1px solid #d6e2ec;
                border-radius: 10px;
                color: #687b8e;
                background: #f8fbfd;
                font-size: 12px;
                line-height: 1.6;
            "
        >
            Pendaftaran ini hanya untuk Klien.
            Akun internal GIS dibuat oleh Superadmin.
        </div>
    </section>

    @if ($errors->any())
        @push('scripts')
        <script>
            (function(){
                const errors=@json($errors->all());
                if(!errors.length)return;
                if(typeof window.Swal==='undefined')return; // pesan per-kolom tetap tampil sebagai fallback
                window.Swal.fire({
                    icon:'error',
                    title:'Akun gagal dibuat',
                    html:'<ul style="text-align:left;margin:0;padding-left:18px">'+errors.map(m=>'<li>'+m+'</li>').join('')+'</ul>',
                    confirmButtonText:'Perbaiki',
                    confirmButtonColor:'#b42318',
                });
            })();
        </script>
        @endpush
    @endif
@endsection