@extends('layouts.app')

@section('title', 'Tinjauan Teknis — ' . $application->order_number)

@section('content')
    <div class="page-head">
        <div>
            <h1>Tinjauan Teknis</h1>
            <p>{{ $application->order_number }} · {{ $application->company_name }} · <x-scheme-badge :scheme="$application->scheme" /></p>
        </div>
        <div>
            <a class="btn btn-light" href="{{ route('technical.reviews.index') }}">Kembali</a>
        </div>
    </div>

    @unless (auth()->user()->hasSignature())
        <div class="alert alert-warning">
            Anda belum mengunggah tanda tangan elektronik. Slot tanda tangan teknis pada PDF akan kosong.
            <a href="{{ route('profile.edit') }}">Unggah tanda tangan di Profil</a> terlebih dahulu.
        </div>
    @endunless

    @php($itemsByCode = $review ? $review->items->keyBy('item_code') : collect())

    <section class="card" id="tinjauan-teknis">
        <h2>Penilaian Aspek Teknis</h2>
        @php($formCode = config('review.form_meta.'.$application->scheme->code.'.code') ?? config('review.form_meta_default.code'))
        <p class="muted small">
            Mengikuti formulir <strong>{{ $formCode }}</strong> bagian II. Pilihan yang tidak dipilih akan tercetak
            dicoret pada PDF tinjauan permohonan.
        </p>
        <form method="post" action="{{ route('technical.reviews.save', $application) }}">
            @csrf

            <h3>Kesimpulan Teknis</h3>
            {{-- Nama lembaga pada label menyesuaikan skema (LSSM, LSSMLTI, dst.).
                 Sengaja memakai bentuk inline: berkas ini sudah dipenuhi bentuk
                 inline, dan mencampurnya dengan bentuk blok membuat Blade
                 menelan seluruh isi di antara keduanya. --}}
            @php($formBody = config('review.form_meta.'.$application->scheme->code.'.body') ?? config('review.form_meta_default.body'))
            <div class="grid-2">
                @foreach (config('review.technical_conclusion') as $key => $meta)
                    <div class="form-group">
                        <label class="form-label" for="pilihan-{{ $key }}">{{ str_replace(':body', $formBody, $meta['label']) }} *)</label>
                        <select class="form-select" id="pilihan-{{ $key }}" name="{{ $key }}">
                            <option value="">— belum dipilih —</option>
                            @foreach ($meta['options'] as $value => $label)
                                <option value="{{ $value }}" @selected(old($key, $review?->{$key}) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Tim Auditor yang ditugaskan (LA, A, TA)</label>
                    @php($assignments = $application->auditAssignments->where('status', 'assigned'))
                    @if ($assignments->isEmpty())
                        <div class="alert alert-info small">
                            Belum ada auditor yang ditugaskan. Penugasan dilakukan Admin Permohonan pada bagian
                            <strong>Penugasan Auditor</strong> di halaman review, dan otomatis tercetak di formulir ini.
                        </div>
                    @else
                        <ol class="small" style="margin:0;padding-left:20px">
                            @foreach ($assignments as $assignment)
                                <li>{{ $assignment->auditor?->name ?? '-' }} <span class="muted">({{ $assignment->assignment_role }})</span></li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                @if ($application->scheme->review_template === 'lsml')
                    <div class="form-group">
                        <label class="form-label">Kompetensi spesifik auditor untuk {{ $application->scheme->standard }} yang diperlukan</label>
                        @php($selectedCompetences = old('auditor_competence_codes', $review?->auditor_competence_codes ?? []))
                        @foreach (config('review.environmental_competences') as $code => $label)
                            <label class="flex gap-1 small" style="margin-bottom:6px">
                                <input type="checkbox" name="auditor_competence_codes[]" value="{{ $code }}"
                                       @checked(in_array($code, (array) $selectedCompetences, true))>
                                <span>{{ $label }} <span class="muted">({{ $code }})</span></span>
                            </label>
                        @endforeach
                        <p class="small muted" style="margin-top:6px">Aspek yang dicentang tercetak bertanda centang pada tabel FrM.9101.</p>
                    </div>
                @endif

                <div class="form-group">
                    <label class="form-label">Panelis yang ditugaskan</label>
                    @php($selectedPanelists = old('panelist_ids', $review?->panelist_ids ?? []))
                    @forelse ($panelistCandidates as $candidate)
                        <label class="flex gap-1 small" style="margin-bottom:6px">
                            <input type="checkbox" name="panelist_ids[]" value="{{ $candidate->id }}"
                                   @checked(in_array($candidate->id, (array) $selectedPanelists))>
                            <span>{{ $candidate->name }}</span>
                        </label>
                    @empty
                        <div class="alert alert-warning small">Belum ada akun Tim Teknis atau Superadmin yang dapat dipilih sebagai panelis.</div>
                    @endforelse
                </div>
            </div>

            {{-- Satu form menulis ke dua blok items[]: aspek (checklist) lalu dokumen.
                 Indeks harus memakai counter berjalan, bukan $loop->index, agar
                 kedua blok tidak saling menimpa. --}}
            @php($i = 0)

            @foreach ($technicalFields as $code => $meta)
                @php($val = $application->value($code))
                @php($existing = $itemsByCode->get($code))
                <input type="hidden" name="items[{{ $i }}][type]" value="checklist">
                <input type="hidden" name="items[{{ $i }}][code]" value="{{ $code }}">
                <input type="hidden" name="items[{{ $i }}][label]" value="{{ $meta['label'] }}">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="aspek-{{ $code }}">{{ $meta['label'] }}</label>
                        @php($current = old('aspects.'.$code, is_array($val) ? implode(', ', $val) : $val))
                        @if ($meta['input'] === 'textarea')
                            <textarea class="form-textarea" id="aspek-{{ $code }}" name="aspects[{{ $code }}]" rows="2">{{ $current }}</textarea>
                        @elseif ($meta['input'] === 'number')
                            <input class="form-control" id="aspek-{{ $code }}" type="number" step="0.5" min="0" name="aspects[{{ $code }}]" value="{{ $current }}">
                        @else
                            <input class="form-control" id="aspek-{{ $code }}" type="text" name="aspects[{{ $code }}]" value="{{ $current }}">
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hasil kajian</label>
                        <select class="form-select" name="items[{{ $i }}][status]">
                            <option value="pending" @selected(($existing?->review_status ?? 'pending') === 'pending')>Belum dikaji</option>
                            <option value="sufficient" @selected($existing?->review_status === 'sufficient')>Cukup/Sesuai</option>
                            <option value="insufficient" @selected($existing?->review_status === 'insufficient')>Belum cukup/Tidak sesuai</option>
                        </select>
                        <input class="form-control mt-1" name="items[{{ $i }}][notes]" value="{{ $existing?->notes }}" placeholder="Keterangan">
                    </div>
                </div>
                @php($i++)
            @endforeach

            <h3 class="mt-3">Kajian Dokumen Teknis</h3>
            @if ($technicalDocuments->isEmpty())
                <div class="empty">Skema ini tidak memiliki dokumen teknis.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>No</th><th>Dokumen</th><th>File</th><th>Hasil Kajian*)</th><th>Keterangan*)</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($technicalDocuments as $n => $required)
                                @php($doc = $application->documents->firstWhere('document_code', $required->code))
                                <tr>
                                    <td>{{ $n + 1 }}</td>
                                    <td>
                                        {{ $required->name }}
                                        <input type="hidden" name="items[{{ $i }}][type]" value="document">
                                        <input type="hidden" name="items[{{ $i }}][code]" value="{{ $required->code }}">
                                        <input type="hidden" name="items[{{ $i }}][label]" value="{{ $required->name }}">
                                        <input type="hidden" name="items[{{ $i }}][presence]" value="{{ $doc?->currentVersion ? 'Ada' : 'Tidak Ada' }}">
                                    </td>
                                    <td>
                                        @if (! $required->expects_document)
                                            {{-- Baris penilaian peninjau, bukan berkas unggahan klien. --}}
                                            <span class="small muted">—</span>
                                        @elseif ($doc?->currentVersion)
                                            <a class="btn btn-light btn-sm" href="{{ route('secure-files.application-document', $doc) }}">{{ $doc->currentVersion->original_name }}</a>
                                            <div class="small muted">v{{ $doc->currentVersion->version }}</div>
                                        @else
                                            <span class="text-danger">Tidak ada</span>
                                        @endif
                                    </td>
                                    @php($docItem = $itemsByCode->get($required->code))
                                    <td>
                                        <select class="form-select" name="items[{{ $i }}][status]">
                                            <option value="pending" @selected(($docItem?->review_status ?? $doc?->review_status ?? 'pending') === 'pending')>Belum dikaji</option>
                                            @foreach (config('review.result_options') as $value => $label)
                                                <option value="{{ $value }}" @selected(($docItem?->review_status ?? $doc?->review_status) === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @php($docRemark = old("items.$i.remark_option", $docItem?->remark_option))
                                        @if ($required->free_remark)
                                            <input class="form-control" name="items[{{ $i }}][notes]"
                                                   value="{{ old("items.$i.notes", $docItem?->notes) }}" placeholder="Keterangan">
                                        @else
                                        <select class="form-select js-remark-select" name="items[{{ $i }}][remark_option]" data-target="tech-remark-date-{{ $i }}">
                                            <option value="">— belum diisi —</option>
                                            @foreach (config('review.remark_options') as $value => $label)
                                                <option value="{{ $value }}" @selected($docRemark === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control mt-1" type="date" id="tech-remark-date-{{ $i }}"
                                               name="items[{{ $i }}][remark_date]"
                                               value="{{ old("items.$i.remark_date", optional($docItem?->remark_date)->format('Y-m-d')) }}"
                                               style="{{ $docRemark === 'tgl_berlaku' ? '' : 'display:none' }}">
                                        <input class="form-control mt-1" name="items[{{ $i }}][notes]"
                                               value="{{ old("items.$i.notes", $doc?->review_note) }}" placeholder="Catatan tambahan (opsional)">
                                        @endif
                                    </td>
                                </tr>
                                @php($i++)
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="grid-3 mt-2">
                <div class="form-group">
                    <label class="form-label">Tanggal</label>
                    <input class="form-control" type="date" name="action_date" value="{{ optional($review?->action_date)->format('Y-m-d') ?? now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Peninjau Teknis</label>
                    <input class="form-control" value="{{ auth()->user()->name }}" readonly>
                    <p class="small muted" style="margin-top:6px">Diambil dari akun Anda dan tercetak pada kolom tanda tangan.</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan Belum Memenuhi</label>
                    <input class="form-control" name="notes" value="{{ $review?->notes }}">
                </div>
            </div>
            <p class="small muted">*) coret yang tidak perlu — pilihan yang tidak dipilih dicoret otomatis saat PDF dibuat.</p>
            <button class="btn btn-primary">Simpan Tinjauan Teknis</button>
        </form>

        @push('scripts')
        <script>
        /* Kolom tanggal hanya relevan saat Keterangan memilih "Tgl Berlaku". */
        document.addEventListener('change', function (event) {
            const select = event.target.closest('.js-remark-select');
            if (!select) return;
            const field = document.getElementById(select.dataset.target);
            if (!field) return;
            const active = select.value === 'tgl_berlaku';
            field.style.display = active ? '' : 'none';
            if (!active) field.value = '';
        });
        </script>
        @endpush

        <hr style="border:0;border-top:1px solid var(--line);margin:24px 0">

        <form method="post" action="{{ route('technical.reviews.complete', $application) }}"
              data-confirm="Kirim hasil tinjauan teknis ke Admin untuk keputusan akhir? Pastikan penilaian sudah disimpan."
              data-confirm-title="Selesai & Kirim ke Admin" data-confirm-yes="Ya, kirim">
            @csrf
            <button class="btn btn-success" @disabled(! $review)>Selesai &amp; Kirim ke Admin</button>
            @unless ($review)
                <div class="small muted mt-1">Simpan tinjauan teknis lebih dahulu sebelum mengirim ke Admin.</div>
            @endunless
        </form>
    </section>
@endsection
