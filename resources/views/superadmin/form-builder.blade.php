@extends('layouts.app')

@section('title', 'Form Builder ' . $scheme->short_name)

@section('content')
    <div class="page-head">
        <div>
            <h1>Form Builder · {{ $scheme->short_name }}</h1>
            <p>
                Versi aktif {{ $scheme->form_version }}. Setiap perubahan otomatis
                membuat snapshot versi baru; permohonan lama tetap memakai snapshot
                saat draft dibuat.
            </p>
        </div>
        <a class="btn btn-light" href="{{ route('superadmin.schemes.edit', $scheme) }}">← Kembali</a>
    </div>

    <div class="grid-2">
        <section class="card">
            <h2>Tambah Section</h2>
            <form method="post" action="{{ route('superadmin.form-builder.sections.store', $scheme) }}">
                @csrf
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Kode</label>
                        <input class="form-control" name="code" placeholder="data_teknis" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Urutan</label>
                        <input class="form-control" type="number" name="sort_order" value="{{ $scheme->sections->max('sort_order') + 10 }}" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Judul</label>
                    <input class="form-control" name="title" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-textarea" name="description"></textarea>
                </div>
                <button class="btn btn-primary">Tambah Section</button>
            </form>
        </section>

        <section class="card">
            <h2>Tambah Field</h2>
            <form method="post" action="{{ route('superadmin.form-builder.fields.store', $scheme) }}">
                @csrf
                @include('superadmin.partials.field-form', ['field' => null])
                <div class="mt-1">
                    <button class="btn btn-primary">Tambah Field &amp; Publish</button>
                </div>
            </form>
        </section>
    </div>

    <section class="card mt-2">
        <h2>Field Aktif</h2>
        @foreach ($scheme->sections as $section)
            <details>
                <summary>
                    <strong>{{ $section->sort_order }} · {{ $section->title }}</strong>
                    <span class="muted">{{ $section->fields->count() }} field</span>
                </summary>
                @foreach ($section->fields as $field)
                    <details class="mt-1">
                        <summary>
                            <span class="badge badge-{{ $field->is_active ? 'success' : 'neutral' }}">
                                {{ $field->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <code>{{ $field->code }}</code> · {{ $field->label }}
                        </summary>
                        <form class="mt-1" method="post" action="{{ route('superadmin.form-builder.fields.update', [$scheme, $field]) }}">
                            @csrf
                            @method('PUT')
                            @include('superadmin.partials.field-form', ['field' => $field])
                            <div class="mt-1">
                                <button class="btn btn-primary btn-sm">Simpan &amp; Publish</button>
                            </div>
                        </form>
                        <form class="mt-1" method="post" action="{{ route('superadmin.form-builder.fields.toggle', [$scheme, $field]) }}">
                            @csrf
                            <button class="btn btn-light btn-sm">
                                {{ $field->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                        </form>
                    </details>
                @endforeach
            </details>
        @endforeach
    </section>

    <div class="grid-2 mt-2">
        <section class="card">
            <h2>Tambah Dokumen</h2>
            <form method="post" action="{{ route('superadmin.form-builder.documents.store', $scheme) }}">
                @csrf
                @include('superadmin.partials.document-form', ['document' => null])
                <div class="mt-1">
                    <button class="btn btn-primary">Tambah Dokumen &amp; Publish</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Aturan Dokumen</h2>
            @foreach ($scheme->requiredDocuments as $document)
                <details>
                    <summary>
                        <span class="badge badge-{{ $document->is_active ? 'success' : 'neutral' }}">
                            {{ $document->requirement }}
                        </span>
                        <code>{{ $document->code }}</code> · {{ $document->name }}
                    </summary>
                    <form class="mt-1" method="post" action="{{ route('superadmin.form-builder.documents.update', [$scheme, $document]) }}">
                        @csrf
                        @method('PUT')
                        @include('superadmin.partials.document-form', ['document' => $document])
                        <div class="mt-1">
                            <button class="btn btn-primary btn-sm">Simpan &amp; Publish</button>
                        </div>
                    </form>
                    <form class="mt-1" method="post" action="{{ route('superadmin.form-builder.documents.toggle', [$scheme, $document]) }}">
                        @csrf
                        <button class="btn btn-light btn-sm">
                            {{ $document->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </form>
                </details>
            @endforeach
        </section>
    </div>
@endsection
