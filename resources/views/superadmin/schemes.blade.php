@extends('layouts.app')

@section('title', 'Skema & Form Dinamis')

@section('content')
    <div class="page-head">
        <div>
            <h1>Skema &amp; Form Dinamis</h1>
            <p>
                Konfigurasi ini menjadi master. Permohonan lama tetap menyimpan
                snapshot versi form saat draft dibuat.
            </p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Urutan</th>
                    <th>Skema</th>
                    <th>Standar</th>
                    <th>Section</th>
                    <th>Dokumen</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($schemes as $scheme)
                    <tr>
                        <td>{{ $scheme->sort_order }}</td>
                        <td>
                            <strong>{{ $scheme->short_name }}</strong>
                            <div class="small muted">
                                {{ $scheme->code }} · Form v{{ $scheme->form_version }}
                            </div>
                        </td>
                        <td>{{ $scheme->standard ?: '-' }}</td>
                        <td>{{ $scheme->sections_count }}</td>
                        <td>{{ $scheme->required_documents_count }}</td>
                        <td>
                            <span class="badge badge-{{ $scheme->is_active ? 'success' : 'neutral' }}">
                                {{ $scheme->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-light btn-sm" href="{{ route('superadmin.schemes.edit', $scheme) }}">
                                Konfigurasi
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
