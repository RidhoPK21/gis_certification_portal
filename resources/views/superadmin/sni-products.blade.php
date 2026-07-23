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
                    <select class="form-select" name="certification_system" required>
                        @foreach (config('gis.certification_systems') as $system)
                            <option value="{{ $system }}" @selected(old('certification_system', 'System 5') === $system)>{{ $system }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-primary">Tambah Produk</button>
            </form>
        </section>

        <section class="card" id="sni-import" data-import-url="{{ route('superadmin.sni-products.import') }}">
            <h2>Import CSV/XLSX</h2>
            <p class="muted">Header: <code>kode_produk,nama_produk,kategori,nomor_sni,sistem_sertifikasi,status_aktif,dokumen_tambahan,catatan</code>. Pilih file dan import langsung berjalan tanpa memuat ulang halaman.</p>
            <div class="form-group">
                <input class="form-control" id="sni-import-input" type="file" accept=".csv,.xlsx">
            </div>
            <div class="small" id="sni-import-status" style="display:none"></div>
        </section>
    </div>

    <section class="card mt-2" id="sni-product-list">
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
                                <td>
                                    @php($systems = collect(config('gis.certification_systems'))->push($product->certification_system)->filter()->unique())
                                    <select class="form-select" name="certification_system" required>
                                        @foreach ($systems as $system)
                                            <option value="{{ $system }}" @selected($product->certification_system === $system)>{{ $system }}</option>
                                        @endforeach
                                    </select>
                                </td>
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

    @push('scripts')
    <script>
    /*
     * Live import produk SNI: pilih file langsung diproses lewat AJAX,
     * tanpa reload. Ringkasan hasil tampil inline dan daftar produk
     * diperbarui otomatis.
     */
    (function(){
        const section=document.getElementById('sni-import');
        const input=document.getElementById('sni-import-input');
        const statusEl=document.getElementById('sni-import-status');
        if(!section||!input)return;
        const url=section.dataset.importUrl;
        const token=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        input.addEventListener('change',async function(){
            const file=input.files&&input.files[0];
            if(!file)return;

            input.disabled=true;
            statusEl.style.display='block';
            statusEl.style.color='var(--muted)';
            statusEl.textContent='Mengimpor '+file.name+'...';

            const fd=new FormData();
            fd.append('file',file);

            try{
                const res=await fetch(url,{
                    method:'POST',
                    body:fd,
                    headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':token}
                });
                const data=await res.json().catch(()=>({}));

                if(!res.ok){
                    const msg=data.errors?.file?.[0]||data.message||'Gagal mengimpor file.';
                    statusEl.style.color='var(--danger,#b42318)';
                    statusEl.textContent='✕ '+msg;
                }else{
                    statusEl.style.color='#17663a';
                    statusEl.textContent='✓ '+(data.message||'Import selesai.');
                    await refreshList();
                }
            }catch(e){
                statusEl.style.color='var(--danger,#b42318)';
                statusEl.textContent='✕ Koneksi gagal. Coba lagi.';
            }finally{
                input.disabled=false;
                input.value='';
            }
        });

        /*
         * Ambil ulang halaman ini dan tukar hanya blok daftar produk,
         * sehingga produk baru muncul tanpa memuat ulang seluruh halaman.
         */
        async function refreshList(){
            try{
                const res=await fetch(window.location.href,{headers:{'X-Requested-With':'XMLHttpRequest'}});
                const html=await res.text();
                const doc=new DOMParser().parseFromString(html,'text/html');
                const fresh=doc.getElementById('sni-product-list');
                const current=document.getElementById('sni-product-list');
                if(fresh&&current)current.replaceWith(fresh);
            }catch(e){/* biarkan; ringkasan sudah tampil */}
        }
    })();
    </script>
    @endpush
@endsection
