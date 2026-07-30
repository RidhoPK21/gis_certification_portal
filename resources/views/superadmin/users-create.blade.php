@extends('layouts.app')

@section('title', 'Tambah Akun Staf')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tambah Akun Staf</h1>
            <p>Sistem mengirim kode aktivasi ke email staf. Kata sandi ditentukan sendiri oleh staf tersebut saat aktivasi.</p>
        </div>
        <div>
            <a class="btn btn-light" href="{{ route('superadmin.users.index') }}">Kembali</a>
        </div>
    </div>

    <section class="card" style="max-width:640px">
        <form method="post" action="{{ route('superadmin.users.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                <input class="form-control" name="name" value="{{ old('name') }}" required>
                @error('name')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Alamat Email <span class="required">*</span></label>
                <input class="form-control" type="email" name="email" value="{{ old('email') }}" placeholder="nama@perusahaan.com" required>
                <div class="small muted">Gunakan email aktif milik staf — kode aktivasi dikirim ke alamat ini.</div>
                @error('email')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Jabatan</label>
                <input class="form-control" name="job_title" value="{{ old('job_title') }}">
                @error('job_title')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Role <span class="required">*</span></label>
                <div class="flex wrap gap-1">
                    @foreach ($roles as $role)
                        <label class="small">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', [])))>
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
                @error('roles')<div class="error-text">{{ $message }}</div>@enderror
            </div>

            <button class="btn btn-primary">Kirim Undangan Aktivasi</button>
        </form>
    </section>
@endsection
