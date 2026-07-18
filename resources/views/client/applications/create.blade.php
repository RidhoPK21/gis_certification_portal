@extends('layouts.app')

@section('title', 'Mulai ' . $scheme->short_name)

@section('content')
    <div class="page-head">
        <div>
            <span class="scheme-code">{{ $scheme->code }}</span>
            <h1>Mulai {{ $scheme->short_name }}</h1>
            <p>{{ $scheme->description }}</p>
        </div>
        <a class="btn btn-light" href="{{ route('client.applications.schemes') }}">← Ganti Skema</a>
    </div>

    <div class="card" style="max-width:850px">
        <h2>Identitas Permohonan</h2>
        <p class="muted">Data ini menjadi identitas awal order. Detail teknis diisi pada wizard berikutnya.</p>
        <form method="post" action="{{ route('client.applications.store', $scheme) }}">
            @csrf
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Nama Perusahaan/Organisasi <span class="required">*</span></label>
                    <input class="form-control" name="company_name" value="{{ old('company_name', auth()->user()->company_name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Pemohon/PIC <span class="required">*</span></label>
                    <input class="form-control" name="applicant_name" value="{{ old('applicant_name', auth()->user()->name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Kontak <span class="required">*</span></label>
                    <input class="form-control" type="email" name="contact_email" value="{{ old('contact_email', auth()->user()->email) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Telepon/HP <span class="required">*</span></label>
                    <input class="form-control" name="contact_phone" value="{{ old('contact_phone', auth()->user()->phone) }}" required>
                </div>
            </div>
            <button class="btn btn-primary">Buat Draft &amp; Lanjut Isi Form</button>
        </form>
    </div>
@endsection
