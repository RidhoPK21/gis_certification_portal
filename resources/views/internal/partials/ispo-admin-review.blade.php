{{--
    Bagian 1-3 FrO.7204 — tanggung jawab Admin Permohonan, yang menandatangani
    sebagai Peninjau / Application Reviewer.
--}}
@php
    $ispoData = $adminReview?->ispo_data ?? [];
    $groups = $ispoGroups;
    $saved = $ispoSaved;
@endphp

<section class="card mt-2" id="dokumen">
    <h2>Tinjauan Permohonan ISPO — Bagian 1 s.d. 3</h2>
    <p class="muted small">
        Mengikuti formulir <strong>{{ config('review.ispo.form.code') }}</strong>. Bagian 4 s.d. 8
        (kesesuaian data, kaji ulang kemampuan LS, mandays, penugasan, dan keputusan) diisi Tim Teknis.
        Anda menandatangani sebagai <strong>Peninjau / Application Reviewer</strong>.
    </p>

    <form method="post" action="{{ route('internal.applications.review', $application) }}">
        @csrf
        <input type="hidden" name="review_type" value="administration">

        <h3 class="ispo-group-title">1. Identitas Permohonan</h3>
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label" for="ispo-received">Tanggal Dokumen Diterima</label>
                <input class="form-control" type="date" id="ispo-received" name="ispo[documents_received_at]"
                       value="{{ old('ispo.documents_received_at', $ispoData['documents_received_at'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label" for="ispo-completeness">Verifikasi Kelengkapan Awal</label>
                <select class="form-select" id="ispo-completeness" name="ispo[initial_completeness]">
                    <option value="">— belum diisi —</option>
                    <option value="lengkap" @selected(old('ispo.initial_completeness', $ispoData['initial_completeness'] ?? '') === 'lengkap')>Lengkap</option>
                    <option value="perlu_dilengkapi" @selected(old('ispo.initial_completeness', $ispoData['initial_completeness'] ?? '') === 'perlu_dilengkapi')>Perlu dilengkapi</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="ispo-admin-notes">Catatan Administratif</label>
                <input class="form-control" id="ispo-admin-notes" name="ispo[administrative_notes]"
                       value="{{ old('ispo.administrative_notes', $ispoData['administrative_notes'] ?? '') }}">
            </div>
        </div>

        <h3 class="ispo-group-title">2. Ruang Lingkup dan Jenis Permohonan</h3>
        <p class="small muted">
            Diambil dari Form Aplikasi yang diisi klien — hanya kelompok checklist untuk ruang lingkup
            berikut yang ditinjau: <strong>{{ $ispoScopeLabel }}</strong>
        </p>

        <h3 class="ispo-group-title" style="margin-top:14px">3. Checklist Dokumen Permohonan</h3>
        <p class="small muted">
            Beri tanda pada kolom Cukup atau Belum Cukup. Baris yang tidak berlaku bagi permohonan
            ditandai Belum Cukup beserta alasannya pada Keterangan, yang diketik bebas.
        </p>

        @include('internal.partials.ispo-review-rows', ['groups' => $groups, 'saved' => $saved, 'offset' => 0])

        <div class="grid-3 mt-2">
            <div class="form-group">
                <label class="form-label" for="ispo-action-date">Tanggal Tinjauan</label>
                <input class="form-control" type="date" id="ispo-action-date" name="action_date"
                       value="{{ old('action_date', optional($adminReview?->action_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Peninjau / Application Reviewer</label>
                <input class="form-control" value="{{ auth()->user()->name }}" readonly>
                <p class="small muted" style="margin-top:6px">Diambil dari akun Anda dan tercetak pada kotak tanda tangan Peninjau.</p>
            </div>
            <div class="form-group">
                <label class="form-label" for="ispo-notes">Catatan Belum Memenuhi</label>
                <input class="form-control" id="ispo-notes" name="notes" value="{{ old('notes', $adminReview?->notes) }}">
            </div>
        </div>

        <input type="hidden" name="signed_name" value="{{ auth()->user()->name }}">
        <button class="btn btn-primary mt-2" type="submit">Simpan Tinjauan Bagian 1–3</button>
    </form>
</section>
