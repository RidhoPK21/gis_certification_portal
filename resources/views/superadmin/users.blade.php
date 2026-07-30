@extends('layouts.app')

@section('title', 'User & Role')

@section('content')
    <div class="page-head">
        <div>
            <h1>User &amp; Role</h1>
            <p>Satu user dapat memiliki beberapa role. Hak akses efektif berasal dari role dan permission.</p>
        </div>
        <div>
            <a class="btn btn-primary" href="{{ route('superadmin.users.create') }}">Tambah Akun</a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>User</th><th>Perusahaan/Jabatan</th><th>Role &amp; Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            @unless ($user->email_verified_at)
                                <span class="badge badge-warning">Menunggu aktivasi</span>
                            @endunless
                            <div class="small muted">{{ $user->email }} · {{ $user->phone }}</div>
                        </td>
                        <td>
                            {{ $user->company_name ?: '-' }}
                            <div class="small muted">{{ $user->job_title ?: '-' }}</div>
                        </td>
                        <td>
                            <form method="post" action="{{ route('superadmin.users.update', $user) }}">
                                @csrf
                                @method('PUT')
                                <div class="flex wrap gap-1">
                                    @foreach ($roles as $role)
                                        <label class="small"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains($role))> {{ $role->name }}</label>
                                    @endforeach
                                </div>
                                <label class="small"><input type="checkbox" name="is_active" value="1" @checked($user->is_active)> Aktif</label>
                        </td>
                        <td>
                            <button class="btn btn-light btn-sm">Simpan</button></form>

                            @unless ($user->email_verified_at)
                                <form method="post" action="{{ route('superadmin.users.resend-invite', $user) }}" style="margin-top:6px">
                                    @csrf
                                    <button class="btn btn-light btn-sm">Kirim ulang undangan</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
