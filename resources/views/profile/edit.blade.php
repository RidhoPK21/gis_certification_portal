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

        @if ($user->canManageSignature())
            <section class="content-card">
                <h2 style="margin: 0 0 4px; color: #082f54; font-size: 18px;">
                    Tanda Tangan Elektronik
                </h2>
                <p style="margin: 0 0 18px; color: #6a7c8d; font-size: 13px;">
                    Unggah tanda tangan Anda (format JPG/PNG, maks. 2 MB). Disimpan
                    permanen dan otomatis dipakai pada dokumen resmi yang Anda tanda tangani.
                    Unggah ulang kapan saja untuk memperbarui.
                </p>

                @if ($user->hasSignature())
                    <div style="margin-bottom: 16px;">
                        <div style="color: #6a7c8d; font-size: 12px; margin-bottom: 6px;">Tanda tangan tersimpan:</div>
                        <img
                            src="{{ route('profile.signature.preview') }}"
                            alt="Tanda tangan"
                            style="max-height: 90px; max-width: 100%; border: 1px solid #d9e2ec; border-radius: 8px; background: #fff; padding: 6px;"
                        >
                        <form method="POST" action="{{ route('profile.signature.remove') }}" style="margin-top: 10px;"
                              data-confirm="Hapus tanda tangan elektronik Anda?" data-confirm-title="Hapus Tanda Tangan"
                              data-confirm-type="danger" data-confirm-yes="Ya, hapus">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-light btn-sm" type="submit">Hapus Tanda Tangan</button>
                        </form>
                    </div>
                @endif

                <form method="POST" action="{{ route('profile.signature') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">{{ $user->hasSignature() ? 'Ganti Tanda Tangan' : 'Unggah Tanda Tangan' }}</label>
                        <input class="form-control" type="file" name="signature" accept="image/png,image/jpeg" required>
                        @error('signature')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <button class="login-button" type="submit">Simpan Tanda Tangan</button>
                </form>
            </section>
        @endif
    </div>
@endsection
