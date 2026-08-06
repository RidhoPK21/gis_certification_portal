@extends('layouts.app')

@section('title', 'Formulir Wajib GIS')

@section('content')
    <div class="page-head">
        <div>
            <h1>Formulir Wajib GIS</h1>
            <p>Template formulir terbitan LS yang dibagikan ke klien setelah permintaannya disetujui.</p>
        </div>
        <a class="btn btn-light" href="{{ route('internal.gis-form-requests.index') }}">Permintaan Masuk</a>
    </div>

    <section class="card">
        <h2>Pilih Skema</h2>
        <p class="muted">Template dikelola per skema. Skema yang belum punya item bergrup Formulir Wajib GIS pada checklistnya belum memakai alur permintaan template.</p>
        <div class="flex gap-1 wrap mt-2">
            @foreach ($schemes as $item)
                <a class="btn btn-sm {{ $item->id === $scheme->id ? 'btn-primary' : 'btn-light' }}"
                   href="{{ route('superadmin.gis-forms.index', ['scheme' => $item->slug]) }}">
                    {{ $item->short_name }}
                </a>
            @endforeach
        </div>
    </section>

    @unless ($usesGisForms)
        <div class="alert alert-warning mt-2">
            Checklist skema <strong>{{ $scheme->short_name }}</strong> belum memiliki item bergrup Formulir Wajib GIS,
            jadi klien belum melihat tombol permintaan template. Tandai dulu item checklistnya melalui data skema.
        </div>
    @endunless

    <div class="grid-2 mt-2">
        <section class="card">
            <h2>Unggah / Perbarui Template</h2>
            <p class="muted">Mengunggah dengan kode yang sama akan menaikkan versinya dan mengganti berkas lama.</p>
            <form method="post" action="{{ route('superadmin.gis-forms.store', $scheme) }}" enctype="multipart/form-data">
                @csrf
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Kode Formulir</label>
                        <input class="form-control" name="code" value="{{ old('code') }}" placeholder="Contoh: FrM.9100" required>
                        @error('code')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Urutan</label>
                        <input class="form-control" type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="999">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Formulir</label>
                    <input class="form-control" name="name" value="{{ old('name') }}" required>
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Keterangan</label>
                    <textarea class="form-control" name="description" rows="2">{{ old('description') }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Item Checklist Terkait</label>
                    <select class="form-select" name="scheme_required_document_id">
                        <option value="">— tidak dikaitkan —</option>
                        @foreach ($documents as $document)
                            <option value="{{ $document->id }}" @selected(old('scheme_required_document_id') == $document->id)>
                                {{ $document->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="small muted" style="margin-top:6px">
                        Menentukan slot unggahan mana yang dipakai klien untuk mengembalikan formulir ini.
                    </p>
                </div>
                <div class="form-group">
                    <label class="form-label">Berkas Template</label>
                    <input class="form-control" type="file" name="file" required>
                    @error('file')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <button class="btn btn-primary">Simpan Template</button>
            </form>
        </section>

        <section class="card">
            <h2>Template {{ $scheme->short_name }}</h2>
            <p class="muted">{{ $templates->count() }} template terdaftar.</p>
            <div class="table-wrap mt-2">
                <table class="table">
                    <thead>
                        <tr><th>Kode</th><th>Nama</th><th>Berkas</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($templates as $template)
                            <tr>
                                <td><strong>{{ $template->code }}</strong><br><span class="small muted">v{{ $template->version }}</span></td>
                                <td>
                                    {{ $template->name }}
                                    @if ($template->requiredDocument)
                                        <br><span class="small muted">→ {{ $template->requiredDocument->name }}</span>
                                    @endif
                                </td>
                                <td class="small">
                                    <a href="{{ route('secure-files.gis-form-template', $template) }}">{{ $template->original_name }}</a>
                                    <br><span class="muted">{{ $template->size_label }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $template->is_active ? 'success' : 'neutral' }}">
                                        {{ $template->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="flex gap-1 wrap">
                                        <form method="post" action="{{ route('superadmin.gis-forms.toggle', $template) }}">
                                            @csrf
                                            <button class="btn btn-light btn-sm">{{ $template->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                        <form method="post" action="{{ route('superadmin.gis-forms.destroy', $template) }}"
                                              data-confirm="Template {{ $template->code }} akan dihapus beserta berkasnya. Lanjutkan?"
                                              data-confirm-title="Hapus Template"
                                              data-confirm-yes="Ya, hapus">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty">Belum ada template untuk skema ini.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
