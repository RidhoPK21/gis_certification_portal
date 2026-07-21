@extends('layouts.app')

@section('title', 'User & Role')

@section('content')
    <div class="page-head">
        <div>
            <h1>User &amp; Role</h1>
            <p>Satu user dapat memiliki beberapa role. Hak akses efektif berasal dari role dan permission.</p>
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
                        <td><button class="btn btn-light btn-sm">Simpan</button></form></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
