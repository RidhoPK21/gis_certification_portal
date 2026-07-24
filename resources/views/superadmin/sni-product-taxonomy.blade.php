@extends('layouts.app')

@section('title', 'Produk & Kategori SNI')

@section('content')
    <div class="page-head">
        <div>
            <h1>Produk &amp; Kategori SNI</h1>
            <p>Kelola pilihan dropdown bertingkat Produk &rarr; Kategori pada form permohonan SNI/LSPro.</p>
        </div>
    </div>

    <div class="grid-2">
        <section class="card">
            <h2>Tambah Produk (Grup)</h2>
            <form method="post" action="{{ route('superadmin.sni-taxonomy.groups.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Nama Produk <span class="required">*</span></label>
                    <input class="form-control" name="name" value="{{ old('name') }}" required>
                    @error('name')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Jenis</label>
                    <select class="form-select" name="mandatory_type">
                        <option value="">— (tidak ditentukan)</option>
                        <option value="wajib">SNI Wajib</option>
                        <option value="sukarela">SNI Sukarela</option>
                    </select>
                </div>
                <button class="btn btn-primary">Tambah Produk</button>
            </form>
        </section>

        <section class="card">
            <h2>Tambah Kategori</h2>
            <form method="post" action="{{ route('superadmin.sni-taxonomy.categories.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Produk (Grup) <span class="required">*</span></label>
                    <select class="form-select" name="sni_product_group_id" required>
                        <option value="">Pilih produk...</option>
                        @foreach ($allGroups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Kategori <span class="required">*</span></label>
                    <input class="form-control" name="name" value="{{ old('name') }}" required>
                    @error('name')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary">Tambah Kategori</button>
            </form>
        </section>
    </div>

    @forelse ($groups as $group)
        <details class="card mt-2">
            <summary style="cursor:pointer;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <strong>{{ $group->name }}</strong>
                <span class="badge badge-neutral">{{ $group->categories->count() }} kategori</span>
                @if ($group->mandatory_type)
                    <span class="badge badge-{{ $group->mandatory_type === 'wajib' ? 'warning' : 'info' }}">SNI {{ ucfirst($group->mandatory_type) }}</span>
                @endif
                @unless ($group->is_active)<span class="badge badge-neutral">Nonaktif</span>@endunless
            </summary>

            <form method="post" action="{{ route('superadmin.sni-taxonomy.groups.update', $group) }}" class="flex gap-1 wrap items-center mt-2">
                @csrf
                @method('PUT')
                <input class="form-control" name="name" value="{{ $group->name }}" required style="max-width:320px">
                <select class="form-select" name="mandatory_type" style="max-width:180px">
                    <option value="" @selected(!$group->mandatory_type)>—</option>
                    <option value="wajib" @selected($group->mandatory_type === 'wajib')>SNI Wajib</option>
                    <option value="sukarela" @selected($group->mandatory_type === 'sukarela')>SNI Sukarela</option>
                </select>
                <label><input type="checkbox" name="is_active" value="1" @checked($group->is_active)> Aktif</label>
                <button class="btn btn-light btn-sm">Simpan Produk</button>
            </form>

            <div class="table-wrap mt-1">
                <table class="table">
                    <thead>
                        <tr><th>Kategori</th><th>Status</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($group->categories as $category)
                            <tr>
                                <form method="post" action="{{ route('superadmin.sni-taxonomy.categories.update', $category) }}">
                                    @csrf
                                    @method('PUT')
                                    <td><input class="form-control" name="name" value="{{ $category->name }}" required></td>
                                    <td><label><input type="checkbox" name="is_active" value="1" @checked($category->is_active)> Aktif</label></td>
                                    <td><button class="btn btn-light btn-sm">Simpan</button></td>
                                </form>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Belum ada kategori.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    @empty
        <section class="card mt-2"><div class="empty">Belum ada produk. Tambahkan di atas.</div></section>
    @endforelse

    {{ $groups->links() }}
@endsection
