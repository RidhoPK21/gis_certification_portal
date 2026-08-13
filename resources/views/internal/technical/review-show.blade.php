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

    {{-- Konteks penuh permohonan: Tim Teknis memutuskan setuju/tolak, jadi
         melihat data dan berkas yang sama persis dengan Admin Permohonan. --}}
    @include('internal.partials.client-submission')
    @include('internal.partials.client-documents')

    @if ($isIspo ?? false)
        @include('internal.partials.ispo-technical-review')
    @elseif ($isSni ?? false)
        @include('internal.partials.sni-technical-review')
    @else
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
                        <div class="alert alert-warning small">
                            Belum ada auditor yang ditugaskan. Tentukan tim pada bagian
                            <a href="#penugasan-auditor"><strong>Penugasan Tim Auditor</strong></a> di bawah;
                            tim tersebut tercetak di formulir ini dan mengisi Surat Tugas nanti.
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

    </section>
    @endif

    {{-- Di luar percabangan: penugasan dan penyelesaian berlaku untuk semua
         skema, termasuk ISPO yang memakai formulir FrO.7204. --}}
    @include('internal.partials.auditor-assignment')

    @php($hasLeadAuditor = $application->auditAssignments->where('status', 'assigned')->where('assignment_role', 'LA')->isNotEmpty())
    @php($openRevisions = $application->revisions->whereIn('status', ['open', 'submitted'])->count())
    @php($canApprove = $review && $hasLeadAuditor && $openRevisions === 0)

    <section class="card mt-2" id="keputusan">
        <h2>Keputusan Permohonan</h2>
        <p class="muted">
            Keputusan akhir ada pada Tim Teknis. Menyetujui akan membuat PDF tinjauan dan meneruskan order ke Finance.
        </p>

        @unless ($canApprove)
            <div class="alert alert-warning small">
                <strong>Belum dapat menyetujui:</strong>
                <ul style="margin:6px 0 0;padding-left:18px">
                    @unless ($review)
                        <li>Tinjauan teknis belum disimpan.</li>
                    @endunless
                    @unless ($hasLeadAuditor)
                        <li>Belum ada <strong>Lead Auditor</strong> pada <a href="#penugasan-auditor">Penugasan Tim Auditor</a>.</li>
                    @endunless
                    @if ($openRevisions > 0)
                        <li>Masih ada {{ $openRevisions }} item revisi terbuka.</li>
                    @endif
                </ul>
            </div>
        @endunless

        <div class="grid-2">
            <form method="post" action="{{ route('technical.reviews.approve', $application) }}"
                  data-confirm="Setujui permohonan dan teruskan ke Finance? PDF tinjauan akan digenerate."
                  data-confirm-title="Setujui Permohonan" data-confirm-yes="Ya, setujui">
                @csrf
                <h3>Setujui</h3>
                <div class="form-group">
                    <label class="form-label">Tanggal Keputusan</label>
                    <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea class="form-textarea" name="notes"></textarea>
                </div>
                <button class="btn btn-success" @disabled(! $canApprove)>Setujui &amp; Teruskan ke Finance</button>
            </form>

            <form method="post" action="{{ route('technical.reviews.reject', $application) }}"
                  data-confirm="Tolak permohonan ini? Tindakan ini menghentikan proses sertifikasi."
                  data-confirm-title="Tolak Permohonan" data-confirm-type="danger" data-confirm-yes="Ya, tolak">
                @csrf
                <h3>Tolak</h3>
                <div class="form-group">
                    <label class="form-label">Tanggal Keputusan</label>
                    <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alasan Penolakan <span class="required">*</span></label>
                    <textarea class="form-textarea" name="reason" required></textarea>
                </div>
                <button class="btn btn-danger">Tolak Permohonan</button>
            </form>
        </div>

        <hr style="border:0;border-top:1px solid var(--line);margin:24px 0">
        <form method="post" action="{{ route('technical.reviews.return-admin', $application) }}"
              data-confirm="Kembalikan permohonan ke Admin tanpa mengambil keputusan? Pastikan penilaian sudah disimpan."
              data-confirm-title="Kembalikan ke Admin" data-confirm-yes="Ya, kembalikan">
            @csrf
            <button class="btn btn-light" @disabled(! $review)>Kembalikan ke Admin</button>
            <div class="small muted mt-1">
                Dipakai bila kelengkapan administrasi perlu dibereskan Admin lebih dahulu. Permohonan akan
                dikirim ulang ke Anda setelah Admin meneruskannya kembali.
            </div>
        </form>
    </section>

    @include('internal.partials.revision-request-form', [
        'action' => route('technical.reviews.revision', $application),
        'resolveRoute' => 'technical.reviews.revisions.resolve',
        'sectionId' => 'revisi-teknis',
        'intro' => 'Perbaikan klien akan kembali melalui Admin Permohonan sebelum diteruskan lagi ke Anda.',
    ])
@endsection
