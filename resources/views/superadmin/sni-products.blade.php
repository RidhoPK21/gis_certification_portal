@extends('layouts.app')

@section('title', 'Master Produk SNI')

@section('content')
    <div class="page-head">
        <div>
            <h1>Master Produk SNI</h1>
            <p>Dropdown produk, nomor SNI, kategori, dan sistem sertifikasi untuk form SNI/LSPro.</p>
        </div>
    </div>

    <div class="grid-2">
        <section class="card">
            <h2>Tambah Produk</h2>
            <form method="post" action="{{ route('superadmin.sni-products.store') }}">
                @csrf
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Kode Produk</label>
                        <input class="form-control" name="product_code" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama Produk</label>
                        <input class="form-control" name="product_name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <input class="form-control" name="category">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nomor SNI</label>
                        <input class="form-control" name="sni_number">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Sistem Sertifikasi</label>
                    <input class="form-control" name="certification_system" value="System 5" required>
                </div>
                <button class="btn btn-primary">Tambah Produk</button>
            </form>
        </section>

        <section class="card">
            <h2>Import CSV/XLSX</h2>
            <p class="muted">Header: <code>kode_produk,nama_produk,kategori,nomor_sni,sistem_sertifikasi,status_aktif,dokumen_tambahan,catatan</code>.</p>
            <form method="post" action="{{ route('superadmin.sni-products.import') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <input class="form-control" type="file" name="file" accept=".csv,.xlsx" required>
                </div>
                <button class="btn btn-success">Import &amp; Sinkronkan</button>
            </form>
        </section>
    </div>

    <section class="card mt-2">
        <div class="page-head">
            <div>
                <h2>Daftar Produk</h2>
                <p>{{ $products->total() }} produk</p>
            </div>
            <form method="get" class="flex gap-1">
                <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Cari produk/kode/SNI">
                <button class="btn btn-light">Cari</button>
            </form>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Kode</th><th>Produk</th><th>Kategori</th><th>Nomor SNI</th><th>Sistem</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <form method="post" action="{{ route('superadmin.sni-products.update', $product) }}">
                                @csrf
                                @method('PUT')
                                <td><code>{{ $product->product_code }}</code></td>
                                <td><input class="form-control" name="product_name" value="{{ $product->product_name }}" required></td>
                                <td><input class="form-control" name="category" value="{{ $product->category }}"></td>
                                <td><input class="form-control" name="sni_number" value="{{ $product->sni_number }}"></td>
                                <td><input class="form-control" name="certification_system" value="{{ $product->certification_system }}" required></td>
                                <td><label><input type="checkbox" name="is_active" value="1" @checked($product->is_active)> Aktif</label></td>
                                <td><button class="btn btn-light btn-sm">Simpan</button></td>
                            </form>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty">Belum ada produk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    </section>
@endsection
