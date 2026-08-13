@extends('layouts.app')

@section('title', 'Surat Tugas — ' . $application->order_number)

@section('content')
    <div class="page-head">
        <div>
            <h1>Surat Tugas</h1>
            <p>{{ $application->order_number }} · {{ $application->company_name }} · <x-scheme-badge :scheme="$application->scheme" /></p>
        </div>
        <div>
            <a class="btn btn-light" href="{{ route('technical.assignments.index') }}">Kembali</a>
        </div>
    </div>

    {{-- Tim boleh berbeda dari pilihan saat tinjauan, dan boleh berbeda antar tahap. --}}
    @include('internal.partials.auditor-assignment', [
        'intro' => 'Ganti tim di sini bila auditornya berbeda dari pilihan saat tinjauan. Tabel auditor pada Surat Tugas yang belum terbit dapat dimuat ulang dari daftar ini; surat yang sudah terbit tidak ikut berubah.',
    ])

    <section class="card mt-2" id="panelis">
        <h2>Panelis &amp; PDF Tinjauan</h2>
        <div class="grid-2">
            <form method="post" action="{{ route('technical.reviews.panelists', $application) }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Panelis yang ditugaskan</label>
                    @php($selected = (array) ($technicalReview?->panelist_ids ?? []))
                    @forelse ($panelistCandidates as $candidate)
                        <label class="flex gap-1 small" style="margin-bottom:6px">
                            <input type="checkbox" name="panelist_ids[]" value="{{ $candidate->id }}"
                                   @checked(in_array($candidate->id, $selected))>
                            <span>{{ $candidate->name }}</span>
                        </label>
                    @empty
                        <div class="alert alert-warning small">Belum ada akun Tim Teknis atau Superadmin yang dapat dipilih sebagai panelis.</div>
                    @endforelse
                </div>
                <button class="btn btn-primary" @disabled(! $technicalReview)>Simpan Panelis</button>
                @unless ($technicalReview)
                    <div class="small muted mt-1">Order ini belum punya tinjauan teknis tersimpan.</div>
                @endunless
            </form>
            <div>
                <h3>PDF Tinjauan Permohonan</h3>
                <p class="small muted">Generate ulang setelah mengganti auditor atau panelis agar dokumen tinjauan mencerminkan tim yang bertugas.</p>
                <form method="post" action="{{ route('technical.assignments.regenerate-review', $application) }}"
                      data-confirm="Generate ulang PDF tinjauan permohonan? Versi baru akan dibuat, versi lama tetap tersimpan."
                      data-confirm-title="Generate Ulang PDF Tinjauan" data-confirm-yes="Ya, generate">
                    @csrf
                    <button class="btn btn-blue">Generate Ulang PDF Tinjauan</button>
                </form>
                <div class="flex gap-1 wrap mt-2">
                    @forelse ($application->generatedPdfs->where('document_type', 'application_review')->sortByDesc('document_version') as $pdf)
                        <a class="btn btn-light btn-sm" href="{{ route('internal.generated-pdf.download', $pdf) }}">Tinjauan v{{ $pdf->document_version }}</a>
                    @empty
                        <span class="small muted">Belum ada PDF tinjauan.</span>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    @foreach ($stages as $stageCode => $stageLabel)
        @php($draft = $drafts[$stageCode])
        @php($letter = $draft['letter'])
        <section class="card mt-2" id="surat-{{ $stageCode }}">
            <div class="page-head">
                <div>
                    <h2>Surat Tugas — {{ $stageLabel }}</h2>
                    <p>
                        @if ($letter && $letter->generated_pdf_id)
                            Terbit versi {{ $letter->pdf_version }} pada {{ optional($letter->updated_at)->format('d M Y H:i') }}.
                        @elseif ($letter)
                            Tersimpan sebagai draft, belum diterbitkan.
                        @else
                            Belum disiapkan.
                        @endif
                    </p>
                </div>
                @if ($letter && $letter->generated_pdf_id)
                    <div>
                        <a class="btn btn-light" href="{{ route('internal.generated-pdf.download', $letter->generated_pdf_id) }}">Buka PDF v{{ $letter->pdf_version }}</a>
                    </div>
                @endif
            </div>

            <form method="post" action="{{ route('technical.assignments.save', [$application, $stageCode]) }}" id="form-{{ $stageCode }}">
                @csrf

                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Nomor Surat</label>
                        <input class="form-control" name="letter_number" value="{{ old('letter_number', $draft['number']) }}" required>
                        <small class="text-muted d-block mt-1">Disarankan otomatis; boleh diubah sebelum diterbitkan.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tempat</label>
                        <input class="form-control" name="letter_place" value="{{ old('letter_place', $draft['place']) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Surat</label>
                        <input class="form-control" type="date" name="letter_date"
                               value="{{ old('letter_date', optional($letter?->letter_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tanggal Penugasan — mulai</label>
                        <input class="form-control" type="date" name="assignment_start_date"
                               value="{{ old('assignment_start_date', optional($letter?->assignment_start_date)->format('Y-m-d')) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Penugasan — selesai</label>
                        <input class="form-control" type="date" name="assignment_end_date"
                               value="{{ old('assignment_end_date', optional($letter?->assignment_end_date)->format('Y-m-d')) }}">
                    </div>
                </div>

                <h3>Tabel Auditor</h3>
                <p class="small muted">
                    Terisi otomatis dari penugasan tim. Hapus centang untuk mengeluarkan seseorang dari surat ini,
                    dan ubah kolom Posisi bila perlu (contoh: “Lead Auditor/PPC”).
                </p>
                @php($rows = $draft['auditors'])
                @if ($rows === [])
                    <div class="alert alert-warning small">
                        Belum ada auditor untuk tahap ini. Tambahkan pada bagian
                        <a href="#penugasan-auditor"><strong>Penugasan Tim Auditor</strong></a> di atas, lalu muat ulang halaman.
                    </div>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Ikut</th><th>Nama</th><th>Peran</th><th>Posisi pada surat</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $i => $row)
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="auditors[{{ $i }}][include]" value="1" checked>
                                            <input type="hidden" name="auditors[{{ $i }}][auditor_id]" value="{{ $row['auditor_id'] }}">
                                            <input type="hidden" name="auditors[{{ $i }}][name]" value="{{ $row['name'] }}">
                                            <input type="hidden" name="auditors[{{ $i }}][role_code]" value="{{ $row['role_code'] }}">
                                        </td>
                                        <td>{{ $row['name'] }}</td>
                                        <td><span class="badge badge-neutral">{{ $row['role_code'] }}</span></td>
                                        <td><input class="form-control" name="auditors[{{ $i }}][position_label]" value="{{ $row['position_label'] }}"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($letter && $draft['current_auditors'] != $draft['auditors'])
                        <div class="alert alert-info small mt-1">
                            Penugasan tim sudah berubah sejak surat ini disimpan. Tabel di atas masih memakai isi surat;
                            simpan ulang setelah menyesuaikan bila ingin memakai tim terkini.
                        </div>
                    @endif
                @endif

                <h3 class="mt-3">Isi Surat</h3>
                @php($fieldGroups = ['Data Klien' => $template['primary_fields'], 'Rincian Kegiatan' => $template['detail_fields']])
                @foreach ($fieldGroups as $groupLabel => $fields)
                    <h4 class="small muted" style="margin:12px 0 4px">{{ $groupLabel }}</h4>
                    <div class="grid-2">
                        @foreach ($fields as $key => $label)
                            @continue($key === 'assignment_date')
                            <div class="form-group">
                                <label class="form-label">
                                    {{ $label }}
                                    @if (in_array($key, $template['manual_fields'], true))
                                        <span class="badge badge-warning">isi manual</span>
                                    @endif
                                </label>
                                <input class="form-control" name="fields[{{ $key }}]"
                                       value="{{ old('fields.'.$key, $draft['overrides'][$key] ?? '') }}">
                            </div>
                        @endforeach
                    </div>
                @endforeach
                <p class="small muted">Baris “Tanggal Penugasan” pada surat diisi otomatis dari rentang tanggal di atas.</p>

                <h3 class="mt-3">Penanda Tangan</h3>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Nama</label>
                        <input class="form-control" name="signer_name" value="{{ old('signer_name', $letter?->signer_name ?? auth()->user()->name) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jabatan</label>
                        <input class="form-control" name="signer_position"
                               value="{{ old('signer_position', $letter?->signer_position ?? $template['default_signer_position']) }}">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan internal</label>
                    <input class="form-control" name="notes" value="{{ old('notes', $letter?->notes) }}" placeholder="Tidak tercetak pada surat">
                </div>

                <div class="flex gap-1 wrap mt-2">
                    <button class="btn btn-light" type="submit">Simpan Draft</button>
                    <button class="btn btn-success" type="submit"
                            formaction="{{ route('technical.assignments.generate', [$application, $stageCode]) }}">
                        {{ $letter && $letter->generated_pdf_id ? 'Terbitkan Ulang' : 'Terbitkan Surat Tugas' }}
                    </button>
                </div>
            </form>

            {{-- Form unggah terpisah: berkasnya disimpan langsung ke surat, tidak
                 ikut alur simpan/terbitkan di atas. --}}
            <h3 class="mt-3">Tanda Tangan Berstempel</h3>
            <p class="small muted">
                Hanya dibubuhkan pada Surat Tugas. PDF Tinjauan Permohonan tetap memakai tanda tangan elektronik
                dari profil akun Anda.
            </p>

            @if ($letter?->signature_path)
                <div class="flex gap-1 wrap items-center">
                    <a class="btn btn-light btn-sm" target="_blank"
                       href="{{ route('secure-files.assignment-letter-signature', $letter) }}">
                        Lihat: {{ $letter->signature_original_name ?: 'tanda tangan berstempel' }}
                    </a>
                    <span class="small muted">
                        diunggah {{ optional($letter->signature_uploaded_at)->format('d M Y H:i') }}
                    </span>
                </div>
            @else
                <div class="alert alert-warning small">
                    Belum ada tanda tangan berstempel. Surat tetap bisa diterbitkan, tetapi slot tanda tangannya kosong.
                </div>
            @endif

            <div class="grid-2 mt-1">
                <form method="post" enctype="multipart/form-data"
                      action="{{ route('technical.assignments.signature', [$application, $stageCode]) }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Unggah gambar tanda tangan berstempel</label>
                        <input class="form-control" type="file" name="signature" accept="image/png,image/jpeg" required>
                        <small class="text-muted d-block mt-1">JPG atau PNG, maksimal {{ config('assignment_letter.signature_max_mb') }} MB.</small>
                    </div>
                    <button class="btn btn-primary">Simpan Tanda Tangan</button>
                </form>

                @if ($reusableSignature && (! $letter || $reusableSignature->id !== $letter->id))
                    <form method="post" action="{{ route('technical.assignments.signature', [$application, $stageCode]) }}"
                          data-confirm="Salin tanda tangan berstempel dari {{ $reusableSignature->stageLabel() }} ke surat ini?"
                          data-confirm-title="Pakai Tanda Tangan Sebelumnya" data-confirm-yes="Ya, salin">
                        @csrf
                        <input type="hidden" name="reuse_from_previous" value="1">
                        <div class="form-group">
                            <label class="form-label">Pakai ulang</label>
                            <p class="small muted">
                                Salin tanda tangan berstempel yang sudah diunggah pada
                                <strong>{{ $reusableSignature->stageLabel() }}</strong>. Berkasnya disalin, jadi
                                mengganti berkas di tahap ini tidak mengubah surat tahap lain.
                            </p>
                        </div>
                        <button class="btn btn-light">Pakai Tanda Tangan dari Tahap Sebelumnya</button>
                    </form>
                @endif
            </div>

            @if ($letter && $letter->pdf_version > 1)
                <div class="flex gap-1 wrap mt-2">
                    <span class="small muted">Riwayat versi:</span>
                    @foreach ($application->generatedPdfs->where('document_type', 'assignment_letter')->sortByDesc('document_version') as $pdf)
                        <a class="btn btn-light btn-sm" href="{{ route('internal.generated-pdf.download', $pdf) }}">v{{ $pdf->document_version }}</a>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach
@endsection
