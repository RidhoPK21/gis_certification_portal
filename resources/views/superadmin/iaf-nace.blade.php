@extends('layouts.app')

@section('title', 'Kode IAF & NACE')

@section('content')
    <div class="page-head">
        <div>
            <h1>Kode IAF &amp; NACE</h1>
            <p>
                Ruang lingkup akreditasi mengikuti KAN K-07.01 Rev.2 Lampiran 1. Daftar ini mengisi dropdown
                bertingkat IAF &rarr; NACE pada form permohonan seluruh skema.
            </p>
        </div>
    </div>

    <div class="alert alert-info">
        <strong>Menonaktifkan</strong> menyembunyikan kode dari form klien, sementara permohonan lama tetap utuh.
        <strong>Menghapus</strong> hanya bisa untuk kode yang belum pernah dipilih permohonan mana pun.
    </div>

    <div class="grid-2">
        <section class="card">
            <h2>Tambah Kode IAF</h2>
            <form method="post" action="{{ route('superadmin.iaf-nace.iaf.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Kode <span class="required">*</span></label>
                    <input class="form-control" name="code" value="{{ old('code') }}" placeholder="mis. 40 atau 7c" required>
                    @error('code')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama (Inggris) <span class="required">*</span></label>
                    <input class="form-control" name="name_en" value="{{ old('name_en') }}" required>
                    @error('name_en')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama (Indonesia) <span class="required">*</span></label>
                    <input class="form-control" name="name_id" value="{{ old('name_id') }}" required>
                    <small class="text-muted d-block mt-1">Teks inilah yang tampil sebagai keterangan bagi klien dan peninjau.</small>
                    @error('name_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary">Tambah Kode IAF</button>
            </form>
        </section>

        <section class="card">
            <h2>Tambah Kode NACE</h2>
            <form method="post" action="{{ route('superadmin.iaf-nace.nace.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Berada di bawah IAF <span class="required">*</span></label>
                    <select class="form-select" name="iaf_code_id" required>
                        <option value="">Pilih kode IAF...</option>
                        @foreach ($allIafCodes as $iaf)
                            <option value="{{ $iaf->id }}" @selected(old('iaf_code_id') == $iaf->id)>{{ $iaf->code }} — {{ $iaf->name_id }}</option>
                        @endforeach
                    </select>
                    @error('iaf_code_id')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Kode <span class="required">*</span></label>
                    <input class="form-control" name="code" value="{{ old('code') }}" placeholder="mis. 23.6" required>
                    @error('code')<div class="error-text">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Nama (Inggris) <span class="required">*</span></label>
                    <input class="form-control" name="name_en" value="{{ old('name_en') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama (Indonesia) <span class="required">*</span></label>
                    <textarea class="form-textarea" name="name_id" rows="2" required>{{ old('name_id') }}</textarea>
                    <small class="text-muted d-block mt-1">Boleh panjang — sebagian entri KAN memuat kalimat pengecualian.</small>
                </div>
                <button class="btn btn-primary">Tambah Kode NACE</button>
            </form>
        </section>
    </div>

    @forelse ($iafCodes as $iaf)
        <details class="card mt-2">
            <summary>
                <strong>IAF {{ $iaf->code }}</strong> — {{ $iaf->name_id }}
                <span class="badge badge-neutral">{{ $iaf->naceCodes->count() }} kode NACE</span>
                @unless ($iaf->is_active)
                    <span class="badge badge-warning">Nonaktif</span>
                @endunless
            </summary>

            <form class="mt-2" method="post" action="{{ route('superadmin.iaf-nace.iaf.update', $iaf) }}">
                @csrf
                @method('PUT')
                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Kode</label>
                        <input class="form-control" name="code" value="{{ $iaf->code }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama (Inggris)</label>
                        <input class="form-control" name="name_en" value="{{ $iaf->name_en }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama (Indonesia)</label>
                        <input class="form-control" name="name_id" value="{{ $iaf->name_id }}" required>
                    </div>
                </div>
                <label class="flex gap-1 small" style="margin-bottom:10px">
                    <input type="checkbox" name="is_active" value="1" @checked($iaf->is_active)>
                    <span>Aktif — tampil pada form klien</span>
                </label>
                <button class="btn btn-primary btn-sm">Simpan Kode IAF</button>
            </form>

            <form method="post" action="{{ route('superadmin.iaf-nace.iaf.destroy', $iaf) }}"
                  data-confirm="Hapus IAF {{ $iaf->code }} beserta seluruh kode NACE di bawahnya? Hanya bisa bila belum pernah dipilih permohonan."
                  data-confirm-title="Hapus Kode IAF" data-confirm-type="danger" data-confirm-yes="Ya, hapus">
                @csrf
                @method('DELETE')
                <button class="btn btn-light btn-sm">Hapus Kode IAF</button>
            </form>

            <h3 class="mt-3">Kode NACE</h3>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th style="width:110px">Kode</th><th>Nama (Indonesia)</th><th style="width:90px">Aktif</th><th style="width:150px">Aksi</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($iaf->naceCodes as $nace)
                            {{-- Form diletakkan di dalam <td>, bukan sebagai anak <tr>:
                                 bentuk yang terakhir bukan HTML yang sah dan hanya
                                 kebetulan berjalan karena browser mengangkatnya keluar tabel. --}}
                            <tr>
                                <td>
                                    <form id="nace-{{ $nace->id }}" method="post" action="{{ route('superadmin.iaf-nace.nace.update', $nace) }}">
                                        @csrf
                                        @method('PUT')
                                        <input class="form-control" name="code" value="{{ $nace->code }}" required>
                                    </form>
                                </td>
                                <td>
                                    <input class="form-control" form="nace-{{ $nace->id }}" name="name_id" value="{{ $nace->name_id }}" required>
                                    <input type="hidden" form="nace-{{ $nace->id }}" name="name_en" value="{{ $nace->name_en }}">
                                </td>
                                <td>
                                    <input type="checkbox" form="nace-{{ $nace->id }}" name="is_active" value="1" @checked($nace->is_active)>
                                </td>
                                <td>
                                    <div class="flex gap-1 wrap">
                                        <button class="btn btn-primary btn-sm" form="nace-{{ $nace->id }}">Simpan</button>
                                        <form method="post" action="{{ route('superadmin.iaf-nace.nace.destroy', $nace) }}"
                                              data-confirm="Hapus kode NACE {{ $nace->code }}?"
                                              data-confirm-title="Hapus Kode NACE" data-confirm-type="danger" data-confirm-yes="Ya, hapus">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-light btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Belum ada kode NACE pada IAF ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </details>
    @empty
        <section class="card mt-2">
            <div class="empty">Belum ada kode IAF. Jalankan seeder acuan KAN atau tambahkan secara manual.</div>
        </section>
    @endforelse

    {{ $iafCodes->links() }}
@endsection
