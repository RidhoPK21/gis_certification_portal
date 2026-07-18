@extends('layouts.app')

@section('title', 'Konfigurasi ' . $scheme->short_name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $scheme->short_name }}</h1>
            <p>{{ $scheme->code }} · {{ $scheme->standard }}</p>
        </div>
        <div class="flex gap-1">
            <a class="btn btn-primary" href="{{ route('superadmin.form-builder.edit', $scheme) }}">
                Buka Form Builder
            </a>
            <a class="btn btn-light" href="{{ route('superadmin.schemes.index') }}">
                ← Semua Skema
            </a>
        </div>
    </div>

    <section class="card">
        <h2>Informasi Skema</h2>
        <form method="post" action="{{ route('superadmin.schemes.update', $scheme) }}">
            @csrf
            @method('PUT')
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input class="form-control" name="name" value="{{ $scheme->name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Pendek</label>
                    <input class="form-control" name="short_name" value="{{ $scheme->short_name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Standar</label>
                    <input class="form-control" name="standard" value="{{ $scheme->standard }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Prefix Nomor Order</label>
                    <input class="form-control" name="order_prefix" value="{{ $scheme->order_prefix }}" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Deskripsi</label>
                <textarea class="form-textarea" name="description">{{ $scheme->description }}</textarea>
            </div>
            <label>
                <input type="checkbox" name="is_active" value="1" @checked($scheme->is_active)>
                Skema aktif dan dapat dipilih klien
            </label>
            <div class="mt-2">
                <button class="btn btn-primary">Simpan Konfigurasi</button>
            </div>
        </form>
    </section>

    <section class="card mt-2">
        <h2>Struktur Form</h2>
        @foreach ($scheme->sections as $section)
            <details @if ($loop->first) open @endif>
                <summary>
                    <strong>{{ $loop->iteration }}. {{ $section->title }}</strong>
                    <span class="muted">({{ $section->fields->count() }} field)</span>
                </summary>
                <div class="table-wrap mt-1">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Urutan</th>
                                <th>Kode</th>
                                <th>Label</th>
                                <th>Tipe</th>
                                <th>Wajib</th>
                                <th>Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section->fields as $field)
                                <tr>
                                    <td>{{ $field->sort_order }}</td>
                                    <td><code>{{ $field->code }}</code></td>
                                    <td>{{ $field->label }}</td>
                                    <td>{{ $field->type }}</td>
                                    <td>{{ $field->is_required ? 'Ya' : 'Tidak' }}</td>
                                    <td class="small">
                                        {{ $field->conditional_rules ? json_encode($field->conditional_rules, JSON_UNESCAPED_UNICODE) : '-' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endforeach
    </section>

    <section class="card mt-2">
        <h2>Dokumen Wajib / Conditional</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Urutan</th>
                        <th>Kode</th>
                        <th>Dokumen</th>
                        <th>Kebutuhan</th>
                        <th>Grup Kajian</th>
                        <th>Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scheme->requiredDocuments as $doc)
                        <tr>
                            <td>{{ $doc->sort_order }}</td>
                            <td><code>{{ $doc->code }}</code></td>
                            <td>{{ $doc->name }}</td>
                            <td>{{ $doc->requirement }}</td>
                            <td>{{ $doc->review_group }}</td>
                            <td class="small">
                                {{ $doc->conditional_rules ? json_encode($doc->conditional_rules, JSON_UNESCAPED_UNICODE) : '-' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="alert alert-info mt-2">
        Perubahan struktur dilakukan melalui <strong>Versioned Form Builder</strong>.
        Setiap publish membuat snapshot baru; draft yang sudah dibuat tetap memakai versi sebelumnya.
    </div>
@endsection
