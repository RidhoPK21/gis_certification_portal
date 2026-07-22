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
            id="login-form"
            novalidate
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

    <script>
        (function () {
            const form = document.getElementById('login-form');

            if (! form) {
                return;
            }

            const email = form.querySelector('#email');
            const password = form.querySelector('#password');
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            /*
             * Menampilkan atau menghapus pesan merah tepat
             * di bawah kolom input terkait.
             */
            function setError(input, message) {
                input.classList.toggle('is-invalid', Boolean(message));

                let slot = input
                    .closest('.form-group')
                    .querySelector('.js-error');

                if (! slot) {
                    slot = document.createElement('div');
                    slot.className = 'form-error js-error';
                    input.closest('.form-group').appendChild(slot);
                }

                slot.textContent = message || '';
                slot.style.display = message ? 'block' : 'none';
            }

            function validateEmail() {
                const value = email.value.trim();

                if (value === '') {
                    setError(email, 'Alamat email wajib diisi.');
                    return false;
                }

                if (! emailPattern.test(value)) {
                    setError(email, 'Format alamat email tidak valid.');
                    return false;
                }

                setError(email, '');
                return true;
            }

            function validatePassword() {
                if (password.value === '') {
                    setError(password, 'Kata sandi wajib diisi.');
                    return false;
                }

                setError(password, '');
                return true;
            }

            email.addEventListener('blur', validateEmail);
            password.addEventListener('blur', validatePassword);

            /*
             * Setelah kolom ditandai merah, perbaiki penilaian
             * secara langsung saat pengguna mengetik ulang.
             */
            email.addEventListener('input', function () {
                if (email.classList.contains('is-invalid')) {
                    validateEmail();
                }
            });

            password.addEventListener('input', function () {
                if (password.classList.contains('is-invalid')) {
                    validatePassword();
                }
            });

            form.addEventListener('submit', function (event) {
                const emailValid = validateEmail();
                const passwordValid = validatePassword();

                if (! emailValid || ! passwordValid) {
                    event.preventDefault();
                    (emailValid ? password : email).focus();
                }
            });
        })();
    </script>

    @if (session('registered'))
        @push('scripts')
        <script>
            (function () {
                if (typeof window.Swal === 'undefined') return; // kotak status hijau tetap tampil sebagai fallback
                window.Swal.fire({
                    icon: 'success',
                    title: 'Akun berhasil dibuat',
                    text: @json(session('status')),
                    confirmButtonText: 'Masuk sekarang',
                    confirmButtonColor: '#0878c9',
                });
            })();
        </script>
        @endpush
    @endif
@endsection