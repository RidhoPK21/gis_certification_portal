@extends('layouts.app')

@section('title', 'Review ' . $application->order_number)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $application->order_number ?: 'Draft #' . $application->id }}</h1>
            <p>{{ $application->company_name }} · {{ $application->scheme->name }}</p>
        </div>
        <span class="badge badge-{{ \App\Enums\ApplicationStatus::tryFrom($application->status)?->tone() ?? 'neutral' }}">
            @statuslabel($application->status)
        </span>
    </div>

    <div class="tabs">
        <a class="tab active" href="#ringkasan">Ringkasan</a>
        <a class="tab" href="#data-form">Data Form</a>
        <a class="tab" href="#dokumen">Dokumen</a>
        <a class="tab" href="#tinjauan">Tinjauan</a>
        <a class="tab" href="#revisi">Revisi</a>
        <a class="tab" href="#timeline">Audit Trail</a>
    </div>

    <section class="grid-2" id="ringkasan">
        <div class="card">
            <h2>Ringkasan Order</h2>
            <dl class="detail-list">
                <dt>Klien</dt><dd>{{ $application->client->name }}<br>{{ $application->contact_email }}</dd>
                <dt>Perusahaan</dt><dd>{{ $application->company_name }}</dd>
                <dt>Skema</dt><dd>{{ $application->scheme->short_name }}</dd>
                <dt>Versi Form</dt><dd>{{ $application->form_version }}</dd>
                <dt>Tanggal Submit</dt><dd>{{ optional($application->submitted_at)->format('d M Y H:i') ?: '-' }}</dd>
            </dl>
        </div>
        <div class="card">
            <h2>Nomor &amp; Tanggal Order</h2>
            <form method="post" action="{{ route('internal.applications.order', $application) }}">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label class="form-label">Nomor Order</label>
                    <input class="form-control" name="order_number" value="{{ $application->order_number }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Order</label>
                    <input class="form-control" type="date" name="order_date" value="{{ optional($application->order_date)->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Alasan perubahan</label>
                    <textarea class="form-textarea" name="reason" required></textarea>
                </div>
                <button class="btn btn-light">Simpan dengan Audit Trail</button>
            </form>
        </div>
    </section>

    @include('internal.partials.client-submission')

    @if ($isIspo ?? false)
        @include('internal.partials.ispo-admin-review')
    @else
    <section class="card mt-2" id="dokumen">
        <h2>Kajian Dokumen Administrasi</h2>
        {{-- Sengaja bentuk blok, bukan inline: berkas ini memakai bentuk blok di
             bawah, dan bentuk inline di atasnya akan berpasangan dengan penutup
             blok tersebut sehingga isi di antaranya ikut tertelan Blade. --}}
        @php
            $formCode = config('review.form_meta.'.$application->scheme->code.'.code')
                ?? config('review.form_meta_default.code');
        @endphp
        <p class="muted small">
            Mengikuti formulir <strong>{{ $formCode }}</strong>. Pilihan yang tidak dipilih akan tercetak dicoret pada PDF
            tinjauan. Dokumen teknis tidak dikaji di sini — bagian itu dinilai Tim Teknis pada tahap tinjauan teknis.
        </p>
        <form method="post" action="{{ route('internal.applications.review', $application) }}">
            @csrf
            <input type="hidden" name="review_type" value="administration">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>No</th><th>Dokumen</th><th>File</th><th>Hasil Kajian*)</th><th>Keterangan*)</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($adminDocuments as $i => $required)
                            @php
                                $doc = $application->documents->firstWhere('document_code', $required->code);
                                $item = $adminReviewItems->get($required->code);
                                $remark = old("items.$i.remark_option", $item?->remark_option);
                            @endphp
                            <tr>
                                <td>{{ $i + 1 }}</td>
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
                                <td>
                                    <select class="form-select" name="items[{{ $i }}][status]">
                                        <option value="pending" @selected(($item?->review_status ?? $doc?->review_status ?? 'pending') === 'pending')>Belum dikaji</option>
                                        @foreach (config('review.result_options') as $value => $label)
                                            <option value="{{ $value }}" @selected(($item?->review_status ?? $doc?->review_status) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    @if ($required->free_remark)
                                        <input class="form-control" name="items[{{ $i }}][notes]"
                                               value="{{ old("items.$i.notes", $item?->notes) }}" placeholder="Keterangan">
                                    @else
                                        <select class="form-select js-remark-select" name="items[{{ $i }}][remark_option]" data-target="remark-date-{{ $i }}">
                                            <option value="">— belum diisi —</option>
                                            @foreach (config('review.remark_options') as $value => $label)
                                                <option value="{{ $value }}" @selected($remark === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input class="form-control mt-1" type="date" id="remark-date-{{ $i }}"
                                               name="items[{{ $i }}][remark_date]"
                                               value="{{ old("items.$i.remark_date", optional($item?->remark_date)->format('Y-m-d')) }}"
                                               style="{{ $remark === 'tgl_berlaku' ? '' : 'display:none' }}">
                                        <input class="form-control mt-1" name="items[{{ $i }}][notes]"
                                               value="{{ old("items.$i.notes", $doc?->review_note) }}" placeholder="Catatan tambahan (opsional)">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="small muted">*) coret yang tidak perlu — pilihan yang tidak dipilih dicoret otomatis saat PDF dibuat.</p>
            <div class="grid-3 mt-2">
                <div class="form-group">
                    <label class="form-label">Tanggal Tinjauan</label>
                    <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Peninjau</label>
                    <input class="form-control" value="{{ auth()->user()->name }}" readonly>
                    <p class="small muted" style="margin-top:6px">Diambil dari akun Anda dan tercetak pada kolom tanda tangan.</p>
                </div>
                <div class="form-group">
                    <label class="form-label">Jumlah Site (bila Multi site)</label>
                    <input class="form-control" type="number" min="1" max="9999" name="site_count"
                           value="{{ old('site_count', $adminReview?->site_count) }}" placeholder="Kosongkan bila Single Site">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan Belum Memenuhi</label>
                <input class="form-control" name="notes" value="{{ old('notes', $adminReview?->notes) }}" placeholder="Isi bila ada dokumen yang belum memenuhi">
            </div>
            <button class="btn btn-primary">Simpan Kajian Administrasi</button>
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

    @if (! ($isIspo ?? false) && $technicalDocuments->isNotEmpty())
        <section class="card mt-2" id="dokumen-teknis">
            <h2>Dokumen Teknis</h2>
            <p class="muted small">Hanya tampilan. Penilaian dilakukan Tim Teknis, tetapi berkas dan hasilnya dapat Anda periksa sebelum menyetujui atau meminta revisi.</p>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>No</th><th>Dokumen</th><th>File</th><th>Hasil Kajian</th><th>Keterangan</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($technicalDocuments as $n => $required)
                            @php($doc = $application->documents->firstWhere('document_code', $required->code))
                            <tr>
                                <td>{{ $n + 1 }}</td>
                                <td>{{ $required->name }}</td>
                                <td>
                                    @if ($doc?->currentVersion)
                                        <a class="btn btn-light btn-sm" href="{{ route('secure-files.application-document', $doc) }}">{{ $doc->currentVersion->original_name }}</a>
                                        <div class="small muted">v{{ $doc->currentVersion->version }}</div>
                                    @else
                                        <span class="text-danger">Tidak ada</span>
                                    @endif
                                </td>
                                <td>
                                    @switch($doc?->review_status)
                                        @case('sufficient') <span class="badge badge-success">Cukup</span> @break
                                        @case('meets') <span class="badge badge-success">Memenuhi</span> @break
                                        @case('insufficient') <span class="badge badge-warning">Belum cukup</span> @break
                                        @case('not_meets') <span class="badge badge-danger">Tidak memenuhi</span> @break
                                        @default <span class="muted small">Belum dikaji</span>
                                    @endswitch
                                </td>
                                <td class="small">{{ $doc?->review_note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @php($techReview = $application->reviews->where('review_type', 'technical')->sortByDesc('round')->first())
    <section class="card mt-2" id="tinjauan">
        <h2>Tinjauan Teknis &amp; PDF Otomatis</h2>
        <p class="muted">
            Bagian teknis diisi oleh Tim Teknis. Tugas Admin adalah mengkaji kelengkapan administrasi lalu
            meneruskan permohonan; <strong>keputusan Setujui/Tolak diambil Tim Teknis</strong>.
        </p>

        @if ($techReview && $techReview->completed_at)
            <div class="alert alert-info">
                Tinjauan teknis dikembalikan oleh <strong>{{ $techReview->signed_name }}</strong> pada {{ $techReview->completed_at->format('d M Y') }}.
                Lengkapi yang diperlukan lalu kirim ulang ke Tim Teknis untuk keputusan akhir.
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Aspek Teknis</th><th>Hasil</th><th>Keterangan</th></tr></thead>
                    <tbody>
                        @forelse ($techReview->items as $item)
                            <tr>
                                <td>{{ $item->item_label }}</td>
                                <td>{{ \App\Enums\ApplicationStatus::labelFor($item->review_status) }}</td>
                                <td>{{ $item->notes ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">Tidak ada rincian item.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($application->status === 'technical_review')
            <div class="alert alert-warning">Sedang ditinjau oleh Tim Teknis. Keputusan Setujui/Tolak diambil di sana.</div>
        @else
            <p class="muted">Belum ditinjau Tim Teknis. Simpan kajian administrasi lalu klik "Teruskan ke Tinjauan Teknis".</p>
        @endif

        @if ($application->status === 'admin_review')
            <form method="post" action="{{ route('internal.applications.forward-technical', $application) }}"
                  data-confirm="Teruskan permohonan ke Tim Teknis untuk tinjauan teknis?"
                  data-confirm-title="Teruskan ke Tim Teknis" data-confirm-yes="Ya, teruskan">
                @csrf
                <button class="btn btn-blue mt-2">{{ $techReview && $techReview->completed_at ? 'Kirim Ulang ke Tim Teknis' : 'Teruskan ke Tinjauan Teknis' }}</button>
            </form>
        @endif

        <hr style="border:0;border-top:1px solid var(--line);margin:24px 0">
        <div class="flex gap-1 wrap">
            <form method="post" action="{{ route('internal.applications.generate-pdf', $application) }}">
                @csrf
                <button class="btn btn-blue">Generate PDF Tinjauan</button>
            </form>
            @foreach ($application->generatedPdfs as $pdf)
                <a class="btn btn-light" href="{{ route('internal.generated-pdf.download', $pdf) }}">PDF v{{ $pdf->document_version }}</a>
            @endforeach
        </div>
    </section>

    @include("internal.partials.revision-request-form", [
        "action" => route("internal.applications.revision", $application),
        "resolveRoute" => "internal.applications.revisions.resolve",
        "sectionId" => "revisi",
    ])

    <section class="card mt-2" id="timeline">
        <h2>Timeline &amp; Audit Trail Order</h2>
        <div class="timeline">
            @foreach ($application->statusHistory as $history)
                <div class="timeline-item done">
                    <div class="timeline-line"><span class="timeline-dot"></span></div>
                    <div class="timeline-content">
                        <h4>{{ $history->action }} → @statuslabel($history->to_status)</h4>
                        <p>{{ $history->notes }}</p>
                        <span class="small muted">Tanggal aksi {{ $history->action_date->format('d M Y H:i') }} · tercatat sistem {{ $history->system_recorded_at->format('d M Y H:i') }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endsection
