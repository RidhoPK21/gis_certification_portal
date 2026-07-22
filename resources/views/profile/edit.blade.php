@extends('layouts.app')

@section('title', 'Profil')

@section('content')
    <div class="page-header">
        <h1>Profil Saya</h1>

        <p>
            Kelola data akun dan kata sandi Anda.
        </p>
    </div>

    @if (session('success'))
        <section
            class="content-card"
            style="margin-bottom: 20px; border-color: #b6e0c2; background: #f2fbf5;"
        >
            {{ session('success') }}
        </section>
    @endif

    <div
        style="
            display: grid;
            gap: 20px;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            align-items: start;
        "
    >
        <section class="content-card">
            <h2 style="margin: 0 0 4px; color: #082f54; font-size: 18px;">
                Data Akun
            </h2>
            <p style="margin: 0 0 18px; color: #6a7c8d; font-size: 13px;">
                Informasi kontak dan perusahaan Anda.
            </p>

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                @include('profile.partials.field', [
                    'name' => 'name',
                    'label' => 'Nama Lengkap',
                    'value' => old('name', $user->name),
                    'type' => 'text',
                    'required' => true,
                ])

                @include('profile.partials.field', [
                    'name' => 'email',
                    'label' => 'Alamat Email',
                    'value' => old('email', $user->email),
                    'type' => 'email',
                    'required' => true,
                ])

                @include('profile.partials.field', [
                    'name' => 'phone',
                    'label' => 'Nomor Telepon',
                    'value' => old('phone', $user->phone),
                    'type' => 'text',
                    'required' => false,
                ])

                @include('profile.partials.field', [
                    'name' => 'company_name',
                    'label' => 'Nama Perusahaan',
                    'value' => old('company_name', $user->company_name),
                    'type' => 'text',
                    'required' => false,
                ])

                @include('profile.partials.field', [
                    'name' => 'job_title',
                    'label' => 'Jabatan',
                    'value' => old('job_title', $user->job_title),
                    'type' => 'text',
                    'required' => false,
                ])

                <button class="login-button" type="submit">
                    Simpan Perubahan
                </button>
            </form>
        </section>

        <section class="content-card">
            <h2 style="margin: 0 0 4px; color: #082f54; font-size: 18px;">
                Ubah Kata Sandi
            </h2>
            <p style="margin: 0 0 18px; color: #6a7c8d; font-size: 13px;">
                Gunakan kata sandi yang kuat dan tidak dipakai di layanan lain.
                Minimal 12 karakter serta mengandung huruf dan angka.
            </p>

            <form method="POST" action="{{ route('profile.password') }}">
                @csrf
                @method('PUT')

                @include('profile.partials.field', [
                    'name' => 'current_password',
                    'label' => 'Kata Sandi Saat Ini',
                    'value' => '',
                    'type' => 'password',
                    'required' => true,
                ])

                @include('profile.partials.field', [
                    'name' => 'password',
                    'label' => 'Kata Sandi Baru',
                    'value' => '',
                    'type' => 'password',
                    'required' => true,
                ])

                @include('profile.partials.field', [
                    'name' => 'password_confirmation',
                    'label' => 'Konfirmasi Kata Sandi Baru',
                    'value' => '',
                    'type' => 'password',
                    'required' => true,
                ])

                <button class="login-button" type="submit">
                    Ganti Kata Sandi
                </button>
            </form>
        </section>
    </div>
@endsection
