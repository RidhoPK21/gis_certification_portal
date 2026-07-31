@extends('layouts.app')

@section('title', 'User & Role')

@section('content')
    <div class="page-head">
        <div>
            <h1>User &amp; Role</h1>
            <p>Pilih <strong>Kelola</strong> pada sebuah akun untuk mengatur role, status, kata sandi, dan penghapusan.</p>
        </div>
        <div>
            <a class="btn btn-primary" href="{{ route('superadmin.users.create') }}">Tambah Akun</a>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Perusahaan/Jabatan</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            <div class="small muted">{{ $user->email }}{{ $user->phone ? ' · ' . $user->phone : '' }}</div>
                        </td>
                        <td>
                            {{ $user->company_name ?: '-' }}
                            <div class="small muted">{{ $user->job_title ?: '-' }}</div>
                        </td>
                        <td>
                            <div class="flex wrap gap-1">
                                @forelse ($user->roles as $role)
                                    <span class="badge badge-neutral">{{ $role->name }}</span>
                                @empty
                                    <span class="small muted">Tanpa role</span>
                                @endforelse
                            </div>
                        </td>
                        <td>
                            @if (! $user->email_verified_at)
                                <span class="badge badge-warning">Menunggu aktivasi</span>
                            @elseif ($user->is_active)
                                <span class="badge badge-success">Aktif</span>
                            @else
                                <span class="badge badge-danger">Nonaktif</span>
                            @endif
                        </td>
                        <td>
                            <a class="btn btn-primary btn-sm" href="{{ route('superadmin.users.edit', $user) }}">Kelola</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Belum ada akun.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
@endsection
