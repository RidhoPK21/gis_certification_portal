@extends('layouts.app')

@section('title', 'Kelola Akun — ' . $user->name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $user->name }}</h1>
            <p>{{ $user->email }}{{ $user->phone ? ' · ' . $user->phone : '' }}</p>
        </div>
        <div>
            <a class="btn btn-light" href="{{ route('superadmin.users.index') }}">Kembali</a>
        </div>
    </div>

    <section class="card">
        <h2>Ringkasan Akun</h2>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                    <tr>
                        <th style="width:220px">Status</th>
                        <td>
                            @if (! $user->email_verified_at)
                                <span class="badge badge-warning">Menunggu aktivasi</span>
                            @elseif ($user->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Nonaktif</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th>Perusahaan / Jabatan</th>
                        <td>{{ $user->company_name ?: '-' }} · {{ $user->job_title ?: '-' }}</td>
                    </tr>
                    <tr>
                        <th>Email diverifikasi</th>
                        <td>{{ $user->email_verified_at?->format('d M Y H:i') ?: 'Belum' }}</td>
                    </tr>
                    <tr>
                        <th>Login terakhir</th>
                        <td>{{ $user->last_login_at?->format('d M Y H:i') ?: 'Belum pernah' }}</td>
                    </tr>
                    <tr>
                        <th>Dibuat</th>
                        <td>{{ $user->created_at?->format('d M Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>Jumlah permohonan</th>
                        <td>{{ $applicationCount }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card mt-2">
        <h2>Role &amp; Status</h2>
        <form method="post" action="{{ route('superadmin.users.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="form-label">Role <span class="required">*</span></label>
                <div class="flex wrap gap-1">
                    @foreach ($roles as $role)
                        <label class="small">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains($role))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('roles')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="small">
                    <input type="checkbox" name="is_active" value="1" @checked($user->is_active)>
                    Akun aktif (bisa login)
                </label>
            </div>

            <button class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </section>

    @unless ($user->email_verified_at)
        <section class="card mt-2">
            <h2>Undangan Aktivasi</h2>
            <p class="muted">Akun ini belum diaktifkan. Kirim ulang bila kode aktivasinya sudah kedaluwarsa.</p>
            <form method="post" action="{{ route('superadmin.users.resend-invite', $user) }}">
                @csrf
                <button class="btn btn-light">Kirim Ulang Undangan</button>
            </form>
        </section>
    @endunless

    <section class="card mt-2">
        <h2>Kata Sandi</h2>

        <div class="grid-2">
            <div>
                <h3>Kirim kode ke email</h3>
                <p class="muted">Cara yang disarankan. Pengguna menentukan kata sandinya sendiri, sehingga Anda tidak perlu mengetahuinya.</p>
                <form method="post" action="{{ route('superadmin.users.password-reset', $user) }}"
                      data-confirm="Kirim kode reset kata sandi ke {{ $user->email }}?"
                      data-confirm-title="Reset Kata Sandi" data-confirm-yes="Ya, kirim">
                    @csrf
                    <button class="btn btn-light">Kirim Kode Reset</button>
                </form>
            </div>

            <div>
                <h3>Tetapkan kata sandi manual</h3>
                <p class="muted">Hanya bila pengguna tidak dapat mengakses emailnya. Sampaikan langsung dan minta segera diganti lewat menu Profil.</p>
                <form method="post" action="{{ route('superadmin.users.password', $user) }}"
                      data-confirm="Tetapkan kata sandi baru untuk {{ $user->email }}?"
                      data-confirm-title="Set Kata Sandi" data-confirm-yes="Ya, tetapkan">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <label class="form-label">Kata Sandi Baru</label>
                        <input class="form-control" type="password" name="password" minlength="12" required>
                        @error('password')<div class="error-text">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Konfirmasi Kata Sandi</label>
                        <input class="form-control" type="password" name="password_confirmation" minlength="12" required>
                        <div class="small muted">Minimal 12 karakter, mengandung huruf dan angka.</div>
                    </div>
                    <button class="btn btn-warning">Simpan Kata Sandi</button>
                </form>
            </div>
        </div>
    </section>

    @if ($user->id !== auth()->id())
        <section class="card mt-2" style="border-color:#f0c2bd">
            <h2 style="color:var(--danger,#b42318)">Hapus Akun</h2>

            @if ($applicationCount > 0)
                <p class="muted">
                    Akun ini memiliki <strong>{{ $applicationCount }}</strong> permohonan sehingga tidak dapat dihapus —
                    menghapusnya akan ikut menghapus seluruh permohonan, dokumen, invoice, dan sertifikat terkait.
                    Bila akun tidak dipakai lagi, cukup nonaktifkan pada bagian Role &amp; Status di atas.
                </p>
            @else
                <p class="muted">Tindakan ini permanen dan tidak dapat dibatalkan.</p>
                <form method="post" action="{{ route('superadmin.users.destroy', $user) }}"
                      data-confirm="Hapus akun {{ $user->name }} ({{ $user->email }}) secara permanen? Tindakan ini tidak dapat dibatalkan."
                      data-confirm-title="Hapus Akun" data-confirm-type="danger" data-confirm-yes="Ya, hapus">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger">Hapus Akun Ini</button>
                </form>
            @endif
        </section>
    @endif
@endsection
