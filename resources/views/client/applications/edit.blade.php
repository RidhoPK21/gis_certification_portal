@extends('layouts.app')

@section('title', 'Isi Permohonan ' . $application->scheme->short_name)

@push('styles')
<style>
.gis-form-block {
    padding: 18px;
    margin-bottom: 20px;
    border: 1px solid var(--border-color, #e5e7eb);
    border-left: 4px solid var(--primary, #0f4c81);
    border-radius: 12px;
    background: var(--card-bg, #ffffff);
}
.gis-form-block h3 {
    margin: 0 0 4px;
}
/* Slot terkunci tetap terlihat supaya klien tahu apa yang menantinya. */
.doc-item-locked {
    opacity: 0.62;
}
.wizard-horizontal-nav {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 10px;
    margin-bottom: 18px;
    border-bottom: 2px solid var(--border-color, #e5e7eb);
    scrollbar-width: thin;
}
/* Bagian bersyarat yang tidak berlaku bagi ruang lingkup yang dipilih.
   Dipakai pada section maupun tombol langkahnya, jadi !important agar menang
   atas .form-section-step.active-step yang memaksa display:block. */
.section-hidden {
    display: none !important;
}

/* ---- Tabel isian (bagian C/D/E/F/G/H/I/J Form Aplikasi ISPO) ---- */
.table-scroll {
    overflow-x: auto;
    max-width: 100%;
}
.form-matrix {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.form-matrix th,
.form-matrix td {
    border: 1px solid var(--border-color, #e5e7eb);
    padding: 6px 8px;
    vertical-align: middle;
    text-align: left;
}
.form-matrix thead th {
    background: var(--surface-muted, #f3f4f6);
    font-weight: 600;
    white-space: nowrap;
}
.form-matrix .matrix-row-label {
    font-weight: 500;
    white-space: normal;
    min-width: 200px;
}
.form-matrix td .form-control,
.form-matrix td .form-select {
    min-width: 120px;
    padding: 5px 8px;
    font-size: 13px;
}
.form-matrix .row-number {
    text-align: center;
    color: var(--text-muted, #6b7280);
}
.repeatable-empty {
    padding: 10px;
    color: var(--text-muted, #6b7280);
    font-size: 13px;
}

/* ---- Blok tanda tangan pemohon (bagian K) ---- */
.signature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 14px;
}
.signature-card {
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 8px;
    padding: 12px;
}
.signature-card-title {
    font-weight: 600;
    margin-bottom: 8px;
}
.signature-card .form-label {
    margin-top: 8px;
}
.signature-preview img {
    max-width: 100%;
    max-height: 110px;
    border: 1px solid var(--border-color, #e5e7eb);
    border-radius: 6px;
    background: #fff;
    margin-bottom: 6px;
}
.wizard-step-btn {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 16px;
    border-radius: 999px;
    border: 1px solid var(--border-color, #e5e7eb);
    background: var(--card-bg, #ffffff);
    color: var(--muted-color, #6b7280);
    font-weight: 600;
    font-size: 0.875rem;
    white-space: nowrap;
    cursor: pointer;
    transition: all 0.2s ease;
}
.wizard-step-btn:hover {
    border-color: var(--primary, #0f4c81);
    color: var(--primary, #0f4c81);
    transform: translateY(-1px);
}
.wizard-step-btn.active {
    background: var(--primary, #0f4c81);
    color: #ffffff;
    border-color: var(--primary, #0f4c81);
    box-shadow: 0 4px 12px rgba(15, 76, 129, 0.25);
}
.wizard-step-btn.completed {
    background: #e8f5e9;
    color: #1b5e20;
    border-color: #a5d6a7;
}
.wizard-step-btn .step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(0,0,0,0.06);
    font-size: 0.75rem;
    font-weight: 700;
}
.wizard-step-btn.active .step-num {
    background: rgba(255,255,255,0.25);
    color: #ffffff;
}
.wizard-step-btn.completed .step-num {
    background: #2e7d32;
    color: #ffffff;
}
.form-section-step {
    display: none;
    animation: fadeInStep 0.25s ease;
}
.form-section-step.active-step {
    display: block;
}
@keyframes fadeInStep {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
@endpush

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $application->scheme->short_name }}</h1>
            <p>{{ $application->order_number ?: 'Draft #' . $application->id }} · {{ $application->company_name }}</p>
        </div>
        <div class="flex gap-1 wrap">
            <a class="btn btn-light" href="{{ route('client.applications.show', $application) }}">Lihat Ringkasan</a>
            @if ($application->canBeDeletedByClient())
                <form method="post" action="{{ route('client.applications.destroy', $application) }}"
                      data-confirm="Draft ini beserta seluruh isian dan dokumen yang sudah diunggah akan dihapus permanen."
                      data-confirm-title="Hapus Draft Permohonan"
                      data-confirm-type="danger"
                      data-confirm-yes="Ya, hapus">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-light text-danger" type="submit">Hapus Draft</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card mb-0">
        <div class="flex justify-between items-center gap-2 wrap">
            <div>
                <strong id="completion-label">Kelengkapan {{ $completion }}%</strong>
                <div class="small muted" id="autosave-status">Perubahan belum disimpan.</div>
            </div>
            <div style="width:min(420px,50%)" class="progress"><span id="completion-bar" style="width:{{ $completion }}%"></span></div>
        </div>
    </div>

    @if ($application->revisions->where('status', 'open')->count())
        <div class="alert alert-warning mt-2">
            <strong>Perbaikan diminta pada item berikut:</strong>
            <ul>
                @foreach ($application->revisions->where('status', 'open') as $revision)
                    <li>
                        <a href="#field-{{ $revision->target_code }}">{{ $revision->target_label }}</a>:
                        {{ $revision->revision_note }}
                        @if ($revision->due_date)(batas {{ $revision->due_date->format('d M Y') }})@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $docStep = $application->scheme->sections->count();
        $submitStep = $docStep + 1;
    @endphp
    <div class="wizard-horizontal-nav mt-3">
        @foreach ($application->scheme->sections as $i => $section)
            {{-- Bagian bersyarat (mis. G/H/I/J pada Form Aplikasi ISPO) ikut
                 disembunyikan beserta tombol langkahnya. --}}
            <button type="button" class="wizard-step-btn {{ $i === 0 ? 'active' : '' }} {{ $section->conditional_rules ? 'conditional-step' : '' }}"
                    data-step="{{ $i }}"
                    data-section-code="{{ $section->code }}"
                    @if ($section->conditional_rules) data-condition='@json($section->conditional_rules)' @endif>
                <span class="step-num">{{ $i + 1 }}</span>
                <span class="step-label">{{ $section->title }}</span>
            </button>
        @endforeach
        <button type="button" class="wizard-step-btn" data-step="{{ $docStep }}">
            <span class="step-num">{{ $docStep + 1 }}</span>
            <span class="step-label">Dokumen Wajib</span>
        </button>
        <button type="button" class="wizard-step-btn" data-step="{{ $submitStep }}">
            <span class="step-num">{{ $submitStep + 1 }}</span>
            <span class="step-label">Pernyataan &amp; Submit</span>
        </button>
    </div>

    <div>
        <form id="application-form" method="post" action="{{ route('client.applications.update', $application) }}">
            @csrf
            @method('PUT')
            @foreach ($application->scheme->sections as $section)
                <section class="card form-section mt-0 form-section-step {{ $loop->index === 0 ? 'active-step' : '' }} {{ $section->conditional_rules ? 'conditional-section' : '' }}"
                         id="section-{{ $section->code }}"
                         data-step-index="{{ $loop->index }}"
                         data-section-code="{{ $section->code }}"
                         @if ($section->conditional_rules) data-condition='@json($section->conditional_rules)' @endif
                         style="margin-bottom:16px;">
                    <h2>{{ $section->title }}</h2>
                    @if ($section->description)<p class="muted">{{ $section->description }}</p>@endif
                    <div class="field-grid">
                        @foreach ($section->fields->where('is_active', true) as $field)
                            @php
                                $value = old('fields.' . $field->code, $values[$field->code] ?? null);
                            @endphp
                            <div id="field-{{ $field->code }}"
                                 class="form-group {{ in_array($field->type, ['textarea', 'checkbox_group', 'table', 'repeatable', 'signature_list', 'file'], true) ? 'field-full' : '' }} dynamic-field"
                                 data-condition='@json($field->conditional_rules)'>
                                <label class="form-label" for="input-{{ $field->code }}">
                                    {{ $field->label }}
                                    @if ($field->is_required)<span class="required">*</span>@endif
                                    @if ($field->unit)<span class="muted">({{ $field->unit }})</span>@endif
                                </label>
                                @if (($productGroups ?? null) && $field->code === 'product_name')
                                    <select class="form-select sni-product-group" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]">
                                        <option value="">Pilih tipe/kategori...</option>
                                        @foreach ($productGroups as $group)
                                            <option value="{{ $group->name }}" @selected((string) $value === (string) $group->name)>{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                @elseif (($productGroups ?? null) && $field->code === 'product_category')
                                    <select class="form-select sni-product-category" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]" data-selected="{{ $value }}">
                                        <option value="">Pilih produk...</option>
                                        @foreach ($productGroups as $group)
                                            @foreach ($group->categories as $cat)
                                                <option value="{{ $cat->name }}" data-group="{{ $group->name }}" @selected((string) $value === (string) $cat->name)>{{ $cat->name }}</option>
                                            @endforeach
                                        @endforeach
                                    </select>
                                @elseif ($field->type === 'textarea')
                                    <textarea class="form-textarea" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]" placeholder="{{ $field->placeholder }}">{{ $value }}</textarea>
                                @elseif ($field->type === 'select')
                                    <select class="form-select" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]">
                                        <option value="">Pilih...</option>
                                        @foreach ($field->options as $option)
                                            <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field->type === 'radio')
                                    <div class="flex gap-2 wrap">
                                        @foreach ($field->options as $option)
                                            <label><input type="radio" name="fields[{{ $field->code }}]" value="{{ $option->value }}" @checked((string) $value === (string) $option->value)> {{ $option->label }}</label>
                                        @endforeach
                                    </div>
                                @elseif ($field->type === 'checkbox_group')
                                    <div class="grid-2">
                                        @foreach ($field->options as $option)
                                            <label><input type="checkbox" name="fields[{{ $field->code }}][]" value="{{ $option->value }}" @checked(in_array($option->value, (array) $value, true))> {{ $option->label }}</label>
                                        @endforeach
                                    </div>
                                @elseif ($field->type === 'boolean')
                                    <select class="form-select" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]">
                                        <option value="">Pilih...</option>
                                        <option value="yes" @selected($value === 'yes')>Ya</option>
                                        <option value="no" @selected($value === 'no')>Tidak</option>
                                    </select>
                                @elseif ($field->type === 'table')
                                    {{-- Tabel baris tetap (mis. H.1 Legalitas): pemohon hanya mengisi selnya. --}}
                                    @include('client.applications.partials.field-table', ['field' => $field, 'value' => $value])
                                @elseif ($field->type === 'repeatable')
                                    {{-- Tabel yang barisnya ditambah sendiri pemohon (mis. G.1 Daftar Kebun). --}}
                                    @include('client.applications.partials.field-repeatable', ['field' => $field, 'value' => $value])
                                @elseif ($field->type === 'signature_list')
                                    @include('client.applications.partials.field-signatures', [
                                        'field' => $field, 'value' => $value, 'application' => $application,
                                    ])
                                @elseif ($field->type === 'file')
                                    @php
                                        $fileData = is_array($value) ? $value : (is_string($value) && str_starts_with($value, '{') ? json_decode($value, true) : null);
                                        $fileName = $fileData['original_name'] ?? $fileData['name'] ?? $fileData['filename'] ?? (is_string($value) && !empty($value) && !str_starts_with($value, '{') ? $value : null);
                                        $filePath = $fileData['path'] ?? $fileData['file_path'] ?? null;
                                    @endphp
                                    <div class="file-field-wrapper" data-has-file="{{ $fileName ? 'true' : 'false' }}">
                                        <div class="small text-success mb-1 flex items-center gap-2" id="file-current-{{ $field->code }}" style="{{ $fileName ? '' : 'display:none' }}">
                                            <span>✓ File tersimpan: <strong>{{ $fileName }}</strong></span>
                                            @if ($filePath)
                                                <a href="{{ route('secure-files.application-field-file', ['application' => $application, 'code' => $field->code]) }}" target="_blank" class="btn btn-sm btn-light">Lihat File</a>
                                            @endif
                                        </div>
                                        <div class="small mb-1" id="file-status-{{ $field->code }}" style="display:none"></div>
                                        <input class="form-control field-file-input" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]"
                                               type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.rar"
                                               data-field-code="{{ $field->code }}"
                                               data-has-file="{{ $fileName ? 'true' : 'false' }}"
                                               data-upload-url="{{ route('client.applications.upload-field-file', $application) }}">
                                        <small class="text-muted d-block mt-1">Format diperbolehkan: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, ZIP, RAR (Maks. 20MB){{ $fileName ? ' · Pilih file baru jika ingin mengganti.' : '' }}</small>
                                    </div>
                                @else
                                    <input class="form-control" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]"
                                           type="{{ in_array($field->type, ['email', 'date', 'number', 'url'], true) ? $field->type : 'text' }}"
                                           value="{{ is_array($value) ? '' : $value }}" placeholder="{{ $field->placeholder }}"
                                           @if ($field->type === 'number') step="any" @endif>
                                @endif
                                @if ($field->help_text)<div class="form-help">{{ $field->help_text }}</div>@endif
                                @error('fields.' . $field->code)<div class="error-text">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-between items-center gap-2 mt-3 pt-2 border-t wrap">
                        <div>
                            @if ($loop->index > 0)
                                <button type="button" class="btn btn-light wizard-prev-btn">← Sebelumnya</button>
                            @endif
                        </div>
                        <div class="flex gap-1">
                            <button type="submit" class="btn btn-light">Simpan Draft</button>
                            <button type="button" class="btn btn-primary wizard-next-btn">Selanjutnya →</button>
                        </div>
                    </div>
                </section>
            @endforeach
        </form>

        <section class="card form-section form-section-step" id="documents" data-step-index="{{ $docStep }}" style="margin-bottom:16px;"
                 data-upload-url="{{ route('client.documents.store', $application) }}">
            <h2>Upload Dokumen</h2>
            <p class="muted">Pilih file dan dokumen langsung terunggah tanpa perlu memuat ulang halaman. Anda bisa memilih ulang untuk mengganti versi. File lama tidak dihapus saat revisi; sistem membuat versi baru dan menyimpan checksum SHA-256.</p>

            @php
                /*
                 * Formulir terbitan LS dipisah dari dokumen milik perusahaan dan
                 * diberi penomoran sendiri, mengikuti checklist resmi GIS.
                 */
                $grouped = $allDocuments->groupBy(
                    fn ($doc) => ($doc->document_group ?? 'company') === 'gis_form' ? 'gis_form' : 'company'
                );
                // Kode dokumen yang syaratnya sudah terpenuhi saat halaman dibuka.
                $visibleDocumentCodes = $applicableDocuments->pluck('code')->flip();
                $gisFormDocuments = $grouped->get('gis_form', collect());
                $companyDocuments = $grouped->get('company', collect());
            @endphp

            @if ($usesGisForms && $gisFormDocuments->isNotEmpty())
                <div class="gis-form-block">
                    <h3>Form Wajib GIS</h3>
                    <p class="muted small">
                        Formulir ini diterbitkan PT GIS. Minta templatenya lebih dulu; setelah tim GIS menyetujui,
                        template dapat diunduh, diisi, lalu diunggah kembali di sini.
                    </p>

                    @if ($gisFormUnlocked)
                        <div class="alert alert-success">
                            <strong>Template sudah dibagikan.</strong>
                            Unduh, isi, tanda tangani, lalu unggah kembali pada slot di bawah.
                            @if ($gisFormRequest?->response_note)
                                <div class="small" style="margin-top:6px">Catatan tim GIS: {{ $gisFormRequest->response_note }}</div>
                            @endif
                        </div>
                        <div class="table-wrap" style="margin-bottom:16px">
                            <table class="table">
                                <thead><tr><th>Kode</th><th>Formulir</th><th>Berkas</th><th></th></tr></thead>
                                <tbody>
                                    @forelse ($gisFormTemplates as $template)
                                        <tr>
                                            <td><strong>{{ $template->code }}</strong></td>
                                            <td>
                                                {{ $template->name }}
                                                @if ($template->description)
                                                    <br><span class="small muted">{{ $template->description }}</span>
                                                @endif
                                            </td>
                                            <td class="small muted">{{ $template->extension }} · {{ $template->size_label }}</td>
                                            <td>
                                                <a class="btn btn-primary btn-sm" href="{{ route('secure-files.gis-form-template', $template) }}">
                                                    Unduh Template
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4"><div class="empty">Tim GIS belum mengunggah berkas template. Hubungi Admin Permohonan.</div></td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif ($gisFormRequest?->isPending())
                        <div class="alert alert-info">
                            <strong>Permintaan terkirim {{ $gisFormRequest->created_at->format('d M Y H:i') }}.</strong>
                            Menunggu persetujuan tim GIS. Anda akan menerima notifikasi begitu template dibagikan.
                        </div>
                    @else
                        @if ($gisFormRequest?->isRejected())
                            <div class="alert alert-danger">
                                <strong>Permintaan sebelumnya ditolak.</strong>
                                {{ $gisFormRequest->response_note }}
                            </div>
                        @endif
                        <div class="alert alert-warning">
                            Slot unggahan di bawah masih terkunci. Ajukan permintaan template lebih dulu.
                        </div>
                        <form method="post" action="{{ route('client.gis-form-requests.store', $application) }}"
                              data-confirm="Permintaan template Formulir Wajib GIS akan dikirim ke tim GIS. Lanjutkan?"
                              data-confirm-title="Minta Template Formulir"
                              data-confirm-yes="Ya, kirim permintaan"
                              style="margin-bottom:16px">
                            @csrf
                            <div class="form-group">
                                <label class="form-label">Catatan untuk tim GIS (opsional)</label>
                                <input class="form-control" name="client_note" maxlength="1000" placeholder="Contoh: mohon dikirim untuk lingkup produksi di dua lokasi">
                            </div>
                            <button class="btn btn-primary">Minta Template Formulir GIS</button>
                        </form>
                    @endif

                    <div class="doc-list">
                        @foreach ($gisFormDocuments as $required)
                            @include('client.applications.partials.document-item', [
                                'required' => $required,
                                'document' => $application->documents->firstWhere('document_code', $required->code),
                                'number' => $loop->iteration,
                                'locked' => ! $gisFormUnlocked,
                                'visible' => $visibleDocumentCodes->has($required->code),
                            ])
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($companyDocuments->isNotEmpty())
                <div class="doc-block">
                @if ($usesGisForms && $gisFormDocuments->isNotEmpty())
                    <h3 style="margin-top:22px">Dokumen Wajib Perusahaan</h3>
                    <p class="muted small">Dokumen milik perusahaan yang wajib dilampirkan.</p>
                @endif
                {{--
                    Muncul bila seluruh slot pada blok ini masih bersyarat dan belum
                    ada yang terpenuhi — misalnya ISPO sebelum Ruang Lingkup dipilih.
                    Tanpa ini daftarnya tampak kosong tanpa penjelasan.
                --}}
                @php
                    $conditionalSourceLabels = $companyDocuments
                        ->pluck('conditional_rules')
                        ->filter()
                        ->map(fn ($r) => data_get($r, 'field') ?? data_get($r, 'any.0.field') ?? data_get($r, 'all.0.field'))
                        ->filter()
                        ->unique();
                    $sourceSection = $conditionalSourceLabels->isNotEmpty()
                        ? optional($application->scheme->sections
                            ->first(fn ($sec) => $sec->fields->contains('code', $conditionalSourceLabels->first())))->title
                        : null;
                @endphp
                <div class="alert alert-info doc-empty-hint{{ $visibleDocumentCodes->isEmpty() ? '' : ' hidden' }}">
                    Belum ada dokumen yang perlu diunggah. Daftar unggahan menyesuaikan isian Anda —
                    lengkapi dulu
                    <strong>{{ $sourceSection ?: 'langkah sebelumnya' }}</strong>,
                    lalu slot unggahannya muncul di sini.
                </div>
                <div class="doc-list">
                    @foreach ($companyDocuments as $required)
                        @include('client.applications.partials.document-item', [
                            'required' => $required,
                            'document' => $application->documents->firstWhere('document_code', $required->code),
                            'number' => $loop->iteration,
                            'locked' => false,
                            'visible' => $visibleDocumentCodes->has($required->code),
                        ])
                    @endforeach
                </div>
                </div>
            @endif
            <div class="flex justify-between items-center gap-2 mt-3 pt-2 border-t wrap">
                <button type="button" class="btn btn-light wizard-prev-btn">← Sebelumnya</button>
                <button type="button" class="btn btn-primary wizard-next-btn">Selanjutnya: Pernyataan →</button>
            </div>
        </section>

        <section class="card form-section form-section-step" id="submit" data-step-index="{{ $submitStep }}">
            <h2>Pernyataan &amp; Submit</h2>
            <div class="alert alert-info">Pastikan data dan dokumen benar. Setelah submit, form terkunci sampai Admin meminta revisi spesifik.</div>
            <form method="post" action="{{ route('client.applications.submit', $application) }}"
                  data-confirm="Setelah dikirim, form terkunci sampai Admin meminta revisi. Kirim permohonan ke tim GIS sekarang?"
                  data-confirm-title="Kirim Permohonan"
                  data-confirm-yes="Ya, kirim">
                @csrf
                <label class="flex gap-1"><input type="checkbox" required> Saya menyatakan data yang disampaikan benar dan saya berwenang mengajukan permohonan ini.</label>
                <div class="flex justify-between items-center gap-2 mt-3 pt-2 border-t wrap">
                    <button type="button" class="btn btn-light wizard-prev-btn">← Sebelumnya</button>
                    <button class="btn btn-primary">Submit Permohonan</button>
                </div>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
<script>
window.updateCompletion = function(serverPct) {
    let pct = serverPct;
    if (pct === undefined || pct === null) {
        const requiredFields = Array.from(document.querySelectorAll('.dynamic-field:not(.hidden)')).filter(el => el.querySelector('.required') !== null);
        let fieldDone = 0;
        requiredFields.forEach(field => {
            const input = field.querySelector('input:not([type="hidden"]), select, textarea');
            if (!input) return;
            let filled = false;
            if (input.type === 'checkbox' || input.type === 'radio') {
                filled = field.querySelectorAll('input:checked').length > 0;
            } else if (input.type === 'file') {
                const cur = field.querySelector('[id^="file-current-"]');
                const status = field.querySelector('[id^="file-status-"]');
                const wrapper = field.querySelector('.file-field-wrapper');
                const hasSavedFile = (input.dataset && (input.dataset.hasFile === 'true' || input.dataset.hasFile === '1')) ||
                                     (wrapper && wrapper.dataset && (wrapper.dataset.hasFile === 'true' || wrapper.dataset.hasFile === '1')) ||
                                     (field.dataset && (field.dataset.hasFile === 'true' || field.dataset.hasFile === '1')) ||
                                     (cur && cur.style.display !== 'none' && cur.textContent.trim() !== '') ||
                                     (status && status.style.display !== 'none' && status.textContent.includes('Berhasil diunggah'));
                const hasNewFile = (input.files && input.files.length > 0) || (input.value && input.value.trim() !== '');
                filled = hasSavedFile || hasNewFile;
            } else {
                filled = input.value && input.value.trim() !== '';
            }
            if (filled) fieldDone++;
        });

        const requiredDocs = Array.from(document.querySelectorAll('.doc-item')).filter(el => el.querySelector('.required') !== null);
        let docDone = 0;
        requiredDocs.forEach(item => {
            const currentDoc = item.querySelector('.doc-current');
            if (currentDoc && currentDoc.style.display !== 'none' && currentDoc.textContent.trim() !== '') {
                docDone++;
            }
        });

        const total = requiredFields.length + requiredDocs.length;
        pct = total === 0 ? 100 : Math.floor(((fieldDone + docDone) / total) * 100);
    }

    const label = document.getElementById('completion-label');
    const bar = document.getElementById('completion-bar');
    if (label) label.textContent = 'Kelengkapan ' + pct + '%';
    if (bar) bar.style.width = pct + '%';
};
</script>
<script>
(function(){const form=document.getElementById('application-form'),status=document.getElementById('autosave-status');if(!form)return;let timer;const values=()=>{const out={};new FormData(form).forEach((v,k)=>{const m=k.match(/^fields\[([^\]]+)\](?:\[\])?$/);if(!m)return;if(k.endsWith('[]')){out[m[1]]=out[m[1]]||[];out[m[1]].push(v)}else out[m[1]]=v});return out};const passes=(r,v)=>{if(!r||Object.keys(r).length===0)return true;if(r.all)return r.all.every(x=>passes(x,v));if(r.any)return r.any.some(x=>passes(x,v));const a=v[r.field],e=r.value;switch(r.operator||'equals'){case'equals':return String(a??'')===String(e??'');case'not_equals':return String(a??'')!==String(e??'');case'in':return (e||[]).includes(a);case'truthy':return !!a;case'falsy':return !a;case'contains':return Array.isArray(a)?a.includes(e):String(a||'').includes(String(e));default:return true}};const refresh=()=>{const v=values();document.querySelectorAll('.dynamic-field').forEach(el=>{let r={};try{r=JSON.parse(el.dataset.condition||'{}')}catch(e){}el.classList.toggle('hidden',!passes(r,v))});
/* Bagian bersyarat: section dan tombol langkahnya disembunyikan bersama supaya
   wizard tidak pernah membuka langkah yang tidak berlaku. */
document.querySelectorAll('.conditional-section').forEach(sec=>{let r={};try{r=JSON.parse(sec.dataset.condition||'{}')}catch(e){}const ok=passes(r,v);sec.classList.toggle('section-hidden',!ok);const btn=document.querySelector('.wizard-step-btn[data-section-code="'+sec.dataset.sectionCode+'"]');if(btn)btn.classList.toggle('section-hidden',!ok)});
/* Slot unggahan bersyarat. Semua slot sudah tercetak di HTML lalu disembunyikan,
   jadi daftarnya ikut berubah begitu isian penentunya diubah — tanpa reload.
   Nomor urut dihitung ulang supaya yang tampil selalu berurutan dari 1. */
document.querySelectorAll('.doc-list').forEach(list=>{let n=0;list.querySelectorAll('.doc-item').forEach(item=>{if(item.classList.contains('conditional-document')){let r={};try{r=JSON.parse(item.dataset.condition||'{}')}catch(e){}item.classList.toggle('hidden',!passes(r,v))}if(!item.classList.contains('hidden')){n++;const num=item.querySelector('.doc-number');if(num)num.textContent=n}});const block=list.closest('.doc-block')||list.parentElement;const empty=block?block.querySelector('.doc-empty-hint'):null;if(empty)empty.classList.toggle('hidden',n>0)});
if(typeof window.sectionsChanged==='function')window.sectionsChanged();
if(typeof window.updateCompletion==='function')window.updateCompletion();};const doSave=async(manual)=>{status.textContent='Menyimpan draft...';const fd=new FormData(form);fd.set('_method','PUT');try{const res=await fetch(form.action,{method:'POST',body:fd,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});if(!res.ok)throw new Error();const data=await res.json().catch(()=>({}));if(data.completion!==undefined&&typeof window.updateCompletion==='function')window.updateCompletion(data.completion);else if(typeof window.updateCompletion==='function')window.updateCompletion();const t=new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});status.textContent=(manual?'Draft tersimpan ':'Draft tersimpan otomatis ')+t;if(manual&&window.Swal)window.Swal.fire({toast:true,position:'top-end',icon:'success',title:'Draft tersimpan',showConfirmButton:false,timer:2200,timerProgressBar:true})}catch(e){status.textContent=manual?'Gagal menyimpan draft. Coba lagi.':'Autosave gagal. Gunakan tombol Simpan Draft.';if(manual&&window.Swal)window.Swal.fire({icon:'error',title:'Gagal menyimpan draft',confirmButtonColor:'#b42318'})}};const autosave=()=>{clearTimeout(timer);status.textContent='Perubahan belum disimpan...';timer=setTimeout(()=>doSave(false),1300)};form.addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);const b=e.submitter;if(b){const o=b.innerHTML;b.disabled=true;b.innerHTML='Menyimpan...';doSave(true).finally(()=>{b.disabled=false;b.innerHTML=o})}else{doSave(true)}});form.addEventListener('input',()=>{refresh();if(typeof window.updateCompletion==='function')window.updateCompletion();autosave()});form.addEventListener('change',()=>{refresh();if(typeof window.updateCompletion==='function')window.updateCompletion();autosave()});refresh();if(typeof window.updateCompletion==='function')window.updateCompletion();})();

/*
 * Live upload dokumen: pilih file langsung terunggah lewat AJAX,
 * tanpa reload halaman, dan input dikosongkan agar bisa memilih ulang.
 */
(function(){
    const section=document.getElementById('documents');
    if(!section)return;
    const url=section.dataset.uploadUrl;
    const token=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    section.addEventListener('change',async function(event){
        const input=event.target;
        if(!input.classList.contains('doc-upload-input'))return;
        const file=input.files&&input.files[0];
        if(!file)return;

        const code=input.dataset.docCode;
        const name=input.dataset.docName;
        const statusEl=document.getElementById('doc-status-'+code);
        const currentEl=document.getElementById('doc-current-'+code);

        input.disabled=true;
        statusEl.style.display='block';
        statusEl.className='small doc-status';
        statusEl.style.color='var(--muted)';
        statusEl.textContent='Mengunggah '+file.name+'...';

        const fd=new FormData();
        fd.append('document_code',code);
        fd.append('file',file);

        try{
            const res=await fetch(url,{
                method:'POST',
                body:fd,
                headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':token}
            });
            const data=await res.json().catch(()=>({}));

            if(!res.ok){
                const msg=data.errors?.file?.[0]||data.message||'Gagal mengunggah dokumen.';
                statusEl.style.color='var(--danger,#b42318)';
                statusEl.textContent='✕ '+msg;
            }else{
                currentEl.style.display='block';
                currentEl.textContent='✓ '+data.original_name+' · versi '+data.version+' · kajian: '+data.review_status;
                statusEl.style.color='#17663a';
                statusEl.textContent='Berhasil diunggah '+new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})+'. Anda bisa memilih file lagi untuk mengganti versi.';
                if (data.completion !== undefined && typeof window.updateCompletion === 'function') {
                    window.updateCompletion(data.completion);
                } else if (typeof window.updateCompletion === 'function') {
                    window.updateCompletion();
                }
            }
        }catch(e){
            statusEl.style.color='var(--danger,#b42318)';
            statusEl.textContent='✕ Koneksi gagal. Coba lagi.';
        }finally{
            input.disabled=false;
            input.value='';
        }
    });
})();

/*
 * Live upload file untuk form field dengan type="file": terunggah lewat AJAX
 * langsung saat dipilih tanpa perlu reload halaman.
 */
(function(){
    const form=document.getElementById('application-form');
    if(!form)return;
    const token=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    form.addEventListener('change',async function(event){
        const input=event.target;
        if(!input.classList.contains('field-file-input'))return;
        const file=input.files&&input.files[0];
        if(!file)return;

        const code=input.dataset.fieldCode;
        const url=input.dataset.uploadUrl;
        const statusEl=document.getElementById('file-status-'+code);
        const currentEl=document.getElementById('file-current-'+code);

        input.disabled=true;
        if(statusEl){
            statusEl.style.display='block';
            statusEl.className='small mb-1';
            statusEl.style.color='var(--muted)';
            statusEl.textContent='Mengunggah '+file.name+'...';
        }

        const fd=new FormData();
        fd.append('field_code',code);
        fd.append('file',file);

        try{
            const res=await fetch(url,{
                method:'POST',
                body:fd,
                headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':token}
            });
            const data=await res.json().catch(()=>({}));

            if(!res.ok){
                const msg=data.errors?.file?.[0]||data.message||'Gagal mengunggah file.';
                if(statusEl){
                    statusEl.style.color='var(--danger,#b42318)';
                    statusEl.textContent='✕ '+msg;
                }
            }else{
                if(currentEl){
                    currentEl.style.display='flex';
                    currentEl.innerHTML='<span>✓ File tersimpan: <strong>'+data.original_name+'</strong></span> '+
                        '<a href="'+data.url+'" target="_blank" class="btn btn-sm btn-light">Lihat File</a>';
                }
                if(statusEl){
                    statusEl.style.color='#17663a';
                    statusEl.textContent='Berhasil diunggah '+new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})+'. Anda bisa memilih file lagi untuk mengganti.';
                }
                input.dataset.hasFile = 'true';
                input.setAttribute('data-has-file', 'true');
                const wrapper = input.closest('.file-field-wrapper');
                if (wrapper) {
                    wrapper.dataset.hasFile = 'true';
                    wrapper.setAttribute('data-has-file', 'true');
                }
                const fieldGroup = input.closest('.dynamic-field');
                if (fieldGroup) {
                    fieldGroup.dataset.hasFile = 'true';
                    fieldGroup.setAttribute('data-has-file', 'true');
                }
                if(data.completion!==undefined&&typeof window.updateCompletion==='function'){
                    window.updateCompletion(data.completion);
                }else if(typeof window.updateCompletion==='function'){
                    window.updateCompletion();
                }
            }
        }catch(e){
            if(statusEl){
                statusEl.style.color='var(--danger,#b42318)';
                statusEl.textContent='✕ Koneksi gagal. Coba lagi.';
            }
        }finally{
            input.disabled=false;
            input.value='';
        }
    });
})();

/*
 * Dropdown bertingkat khusus skema SNI: pilih Produk (grup) -> pilihan
 * Kategori otomatis terfilter sesuai grup terpilih.
 */
(function(){
    const groupSel=document.querySelector('.sni-product-group');
    const catSel=document.querySelector('.sni-product-category');
    if(!groupSel||!catSel)return;
    const options=Array.from(catSel.querySelectorAll('option[data-group]'));
    const preselect=catSel.getAttribute('data-selected')||'';

    function refilter(keepValue){
        const group=groupSel.value;
        const current=keepValue?catSel.value:'';
        let hasCurrent=false;
        options.forEach(opt=>{
            const match=opt.getAttribute('data-group')===group;
            opt.hidden=!match;
            opt.disabled=!match;
            if(match&&opt.value===current)hasCurrent=true;
        });
        if(!hasCurrent)catSel.value='';
    }

    // Pemuatan awal: jika ada nilai kategori tersimpan, pertahankan.
    if(preselect){catSel.value=preselect;refilter(true);}
    else refilter(false);

    groupSel.addEventListener('change',()=>refilter(false));
})();

/* Hapus pesan error di bawah field ketika pengguna mulai mengisi/memilih. */
(function(){
    function clearError(e){
        const group = e.target.closest('.dynamic-field');
        if(group){
            const err = group.querySelector('.error-text');
            if(err) err.remove();
        }
    }
    document.addEventListener('input', clearError);
    document.addEventListener('change', clearError);
})();

/* Tabel berulang: tambah/hapus baris.
   Atribut name ditulis ulang setiap kali struktur berubah supaya indeksnya
   tetap berurutan — kalau tidak, menghapus baris tengah menyisakan lubang dan
   PHP menerimanya sebagai objek, bukan larik. */
(function () {
    function reindex(wrapper) {
        const code = wrapper.dataset.fieldCode;
        wrapper.querySelectorAll('.repeatable-row').forEach((tr, i) => {
            const num = tr.querySelector('.row-number');
            if (num) num.textContent = i + 1;
            tr.querySelectorAll('[data-col]').forEach(input => {
                input.name = 'fields[' + code + '][' + i + '][' + input.dataset.col + ']';
            });
        });
    }

    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.repeatable-add');
        if (addBtn) {
            const wrapper = addBtn.closest('.repeatable-field');
            const tpl = wrapper.querySelector('.repeatable-template');
            wrapper.querySelector('.repeatable-body').appendChild(tpl.content.cloneNode(true));
            reindex(wrapper);
            return;
        }

        const removeBtn = e.target.closest('.repeatable-remove');
        if (removeBtn) {
            const wrapper = removeBtn.closest('.repeatable-field');
            removeBtn.closest('.repeatable-row').remove();
            reindex(wrapper);
            wrapper.closest('form')?.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
})();

/* Unggah gambar tanda tangan pemohon (bagian K).
   Diunggah langsung seperti berkas field lain; yang disimpan pada form hanya
   path hasil unggahan, sehingga autosave tidak perlu mengirim ulang gambarnya. */
(function () {
    document.addEventListener('change', async function (e) {
        const input = e.target.closest('.signature-file-input');
        if (!input || !input.files || input.files.length === 0) return;

        const index = input.dataset.index;
        const status = document.getElementById('sig-status-' + index);
        const preview = document.getElementById('sig-preview-' + index);
        const pathInput = document.getElementById('sig-path-' + index);

        const fd = new FormData();
        fd.append('signature', input.files[0]);
        fd.append('field_code', input.dataset.fieldCode);
        fd.append('index', index);

        status.style.display = '';
        status.textContent = 'Mengunggah tanda tangan...';

        try {
            const res = await fetch(input.dataset.uploadUrl, {
                method: 'POST',
                body: fd,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Gagal mengunggah.');

            pathInput.value = data.path;
            preview.innerHTML = '<img src="' + data.url + '" alt="Tanda tangan">';
            preview.style.display = '';
            status.textContent = 'Tanda tangan tersimpan.';
            input.closest('form')?.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (err) {
            status.textContent = err.message || 'Gagal mengunggah tanda tangan.';
        }
    });
})();

/* Multi-step Wizard Controller & Validation */
(function(){
    const steps = Array.from(document.querySelectorAll('.form-section-step'));
    const navButtons = Array.from(document.querySelectorAll('.wizard-step-btn'));
    if (steps.length === 0) return;

    let currentStep = 0;

    /*
     * Langkah bersyarat (bagian G/H/I/J Form Aplikasi ISPO) disembunyikan lewat
     * kelas .section-hidden oleh evaluator kondisi. Navigasi harus melompatinya,
     * bukan berhenti pada langkah kosong.
     */
    function isHidden(index) {
        const step = steps[index];
        return !!step && step.classList.contains('section-hidden');
    }

    function nextVisible(index, direction) {
        let i = index;
        while (i >= 0 && i < steps.length && isHidden(i)) {
            i += direction;
        }
        return (i >= 0 && i < steps.length) ? i : null;
    }

    /* Nomor langkah mengikuti urutan yang benar-benar terlihat, bukan indeks
       aslinya — tanpa ini penomorannya melompat setiap ada bagian bersyarat
       yang disembunyikan (mis. 1,2,3,4,6,9). */
    function renumberSteps() {
        let n = 0;
        navButtons.forEach(btn => {
            if (btn.classList.contains('section-hidden')) return;
            n += 1;
            const num = btn.querySelector('.step-num');
            if (num) num.textContent = n;
        });
    }

    /* Dipanggil ulang setiap kondisi berubah: kalau langkah yang sedang dibuka
       jadi tidak berlaku, pindahkan ke langkah terdekat yang masih berlaku. */
    window.sectionsChanged = function () {
        renumberSteps();
        if (!isHidden(currentStep)) return;
        const target = nextVisible(currentStep, 1) ?? nextVisible(currentStep, -1);
        if (target !== null) showStep(target);
    };

    function showStep(index) {
        if (index < 0 || index >= steps.length) return;
        if (isHidden(index)) {
            const target = nextVisible(index, index >= currentStep ? 1 : -1);
            if (target === null) return;
            index = target;
        }
        currentStep = index;
        steps.forEach((step, i) => {
            step.classList.toggle('active-step', i === currentStep);
        });
        navButtons.forEach((btn, i) => {
            btn.classList.toggle('active', i === currentStep);
            btn.classList.toggle('completed', i < currentStep);
        });
        if (typeof window.updateCompletion === 'function') {
            window.updateCompletion();
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(index) {
        const stepEl = steps[index];
        if (!stepEl) return true;

        if (stepEl.id !== 'documents' && stepEl.id !== 'submit') {
            let firstEmpty = null;
            let emptyLabels = [];
            const fields = stepEl.querySelectorAll('.dynamic-field:not(.hidden)');
            fields.forEach(field => {
                const isRequired = field.querySelector('.required') !== null;
                if (!isRequired) return;

                const input = field.querySelector('input:not([type="hidden"]), select, textarea');
                if (!input) return;

                let isEmpty = false;
                if (input.type === 'checkbox' || input.type === 'radio') {
                    const checked = field.querySelectorAll('input:checked');
                    if (checked.length === 0) isEmpty = true;
                } else if (input.type === 'file') {
                    const cur = field.querySelector('[id^="file-current-"]');
                    const status = field.querySelector('[id^="file-status-"]');
                    const wrapper = field.querySelector('.file-field-wrapper');
                    const hasSavedFile = (input.dataset && (input.dataset.hasFile === 'true' || input.dataset.hasFile === '1')) ||
                                         (wrapper && wrapper.dataset && (wrapper.dataset.hasFile === 'true' || wrapper.dataset.hasFile === '1')) ||
                                         (field.dataset && (field.dataset.hasFile === 'true' || field.dataset.hasFile === '1')) ||
                                         (cur && cur.style.display !== 'none' && cur.textContent.trim() !== '') ||
                                         (status && status.style.display !== 'none' && status.textContent.includes('Berhasil diunggah'));
                    const hasNewFile = (input.files && input.files.length > 0) || (input.value && input.value.trim() !== '');
                    if (!hasSavedFile && !hasNewFile) {
                        isEmpty = true;
                    }
                } else {
                    if (!input.value || input.value.trim() === '') isEmpty = true;
                }

                if (isEmpty) {
                    if (!firstEmpty) firstEmpty = field;
                    const labelEl = field.querySelector('.form-label');
                    const labelText = labelEl ? labelEl.textContent.replace('*', '').replace(/\([^\)]+\)/g, '').trim() : 'Kolom';
                    emptyLabels.push(labelText);
                }
            });

            if (emptyLabels.length > 0) {
                if (typeof window.Swal !== 'undefined') {
                    window.Swal.fire({
                        icon: 'warning',
                        title: 'Periksa kembali isian berikut:',
                        html: '<ul style="text-align:left;margin:0;padding-left:18px">' + emptyLabels.map(m => '<li>' + m + '</li>').join('') + '</ul>',
                        confirmButtonText: 'Mengerti',
                        confirmButtonColor: '#b42318'
                    });
                } else if (typeof window.flashError === 'function' || typeof flashError === 'function') {
                    (window.flashError || flashError)('Periksa kembali isian berikut:', emptyLabels);
                } else {
                    alert('Harap lengkapi kolom yang wajib diisi:\n- ' + emptyLabels.join('\n- '));
                }
                if (firstEmpty) {
                    firstEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const inp = firstEmpty.querySelector('input, select, textarea');
                    if (inp) inp.focus();
                }
                return false;
            }
        }

        if (stepEl.id === 'documents') {
            let emptyDocs = [];
            let firstEmptyDoc = null;
            stepEl.querySelectorAll('.doc-item').forEach(item => {
                const isRequired = item.querySelector('.required') !== null;
                if (!isRequired) return;
                const currentDoc = item.querySelector('.doc-current');
                if (!currentDoc || currentDoc.style.display === 'none' || currentDoc.textContent.trim() === '') {
                    if (!firstEmptyDoc) firstEmptyDoc = item;
                    const heading = item.querySelector('h4');
                    const docName = heading ? heading.textContent.replace('*', '').trim() : 'Dokumen';
                    emptyDocs.push(docName);
                }
            });

            if (emptyDocs.length > 0) {
                if (typeof window.Swal !== 'undefined') {
                    window.Swal.fire({
                        icon: 'warning',
                        title: 'Dokumen wajib berikut belum diunggah:',
                        html: '<ul style="text-align:left;margin:0;padding-left:18px">' + emptyDocs.map(m => '<li>' + m + '</li>').join('') + '</ul>',
                        confirmButtonText: 'Mengerti',
                        confirmButtonColor: '#b42318'
                    });
                } else if (typeof window.flashError === 'function' || typeof flashError === 'function') {
                    (window.flashError || flashError)('Dokumen wajib berikut belum diunggah:', emptyDocs);
                } else {
                    alert('Harap unggah dokumen wajib berikut:\n- ' + emptyDocs.join('\n- '));
                }
                if (firstEmptyDoc) {
                    firstEmptyDoc.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
        }

        return true;
    }

    document.querySelectorAll('.wizard-next-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!validateStep(currentStep)) return;
            const target = nextVisible(currentStep + 1, 1);
            if (target !== null) showStep(target);
        });
    });

    document.querySelectorAll('.wizard-prev-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = nextVisible(currentStep - 1, -1);
            if (target !== null) showStep(target);
        });
    });

    navButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetStep = parseInt(btn.getAttribute('data-step'), 10);
            if (targetStep > currentStep) {
                if (!validateStep(currentStep)) return;
            }
            showStep(targetStep);
        });
    });

    function handleHash() {
        if (!window.location.hash) return;
        const targetId = window.location.hash.substring(1);
        const targetEl = document.getElementById(targetId);
        if (!targetEl) return;
        const parentStep = targetEl.closest('.form-section-step') || (targetEl.classList.contains('form-section-step') ? targetEl : null);
        if (parentStep && parentStep.dataset.stepIndex !== undefined) {
            showStep(parseInt(parentStep.dataset.stepIndex, 10));
            setTimeout(() => targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100);
        }
    }

    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href^="#"]');
        if (!link) return;
        const targetId = link.getAttribute('href').substring(1);
        const targetEl = document.getElementById(targetId);
        if (!targetEl) return;
        const parentStep = targetEl.closest('.form-section-step') || (targetEl.classList.contains('form-section-step') ? targetEl : null);
        if (parentStep && parentStep.dataset.stepIndex !== undefined) {
            e.preventDefault();
            showStep(parseInt(parentStep.dataset.stepIndex, 10));
            setTimeout(() => targetEl.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100);
        }
    });

    window.addEventListener('hashchange', handleHash);
    setTimeout(handleHash, 50);
    renumberSteps();
    if (typeof window.updateCompletion === 'function') {
        window.updateCompletion();
    }
})();
</script>
@endpush
