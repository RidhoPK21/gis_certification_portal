@extends('layouts.app')

@section('title', 'Audit ' . $application->order_number)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $application->order_number }}</h1>
            <p>{{ $application->company_name }} · {{ $application->scheme->short_name }}</p>
        </div>
        <span class="badge badge-info">@statuslabel($application->status)</span>
    </div>

    <section class="card">
        <h2>Stage 1 / Stage 2 / Audit Lapangan</h2>
        <form method="post" action="{{ route('audit.stage', $application) }}" enctype="multipart/form-data" data-ajax>
            @csrf
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Tahap</label>
                    <select class="form-select" name="stage_code">
                        <option value="stage_1">Stage 1</option>
                        <option value="stage_2">Stage 2</option>
                        <option value="qms">QMS / Audit Lapangan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="uploaded">Dokumen diunggah</option>
                        <option value="approved">Disetujui</option>
                        <option value="revision">Perlu revisi</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal Audit</label>
                    <input class="form-control" type="date" name="audit_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Mandays</label>
                    <input class="form-control" type="number" step="0.5" name="mandays">
                </div>
                <div class="form-group">
                    <label class="form-label">Tim Auditor</label>
                    <input class="form-control" name="auditor_team" placeholder="LA: ..., A: ..., TA: ..." required>
                </div>
                <div class="form-group">
                    <label class="form-label">File Laporan</label>
                    <input class="form-control" type="file" name="report[]" multiple>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Ringkasan Hasil</label>
                <textarea class="form-textarea" name="summary"></textarea>
            </div>
            <button class="btn btn-primary">Simpan Tahap Audit</button>
        </form>

        <details class="mt-2">
            <summary><strong>Skip tahap opsional</strong></summary>
            <form class="mt-2" method="post" action="{{ route('audit.stage.skip', $application) }}">
                @csrf
                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Tahap</label>
                        <select class="form-select" name="stage_code">
                            <option value="stage_1">Stage 1</option>
                            <option value="stage_2">Stage 2</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Aksi</label>
                        <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alasan Wajib</label>
                        <input class="form-control" name="reason" required>
                    </div>
                </div>
                <button class="btn btn-warning">Skip dengan Alasan</button>
            </form>
        </details>

        <h3>Riwayat Tahap</h3>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Tahap</th><th>Status</th><th>Tanggal</th><th>Mandays</th><th>Tim</th><th>File</th></tr>
                </thead>
                <tbody>
                    @forelse ($application->auditStages as $stage)
                        <tr>
                            <td>{{ strtoupper(str_replace('_', ' ', $stage->stage_code)) }}</td>
                            <td>{{ $stage->status }}</td>
                            <td>{{ optional($stage->audit_date)->format('d M Y') ?: '-' }}</td>
                            <td>{{ $stage->mandays ?: '-' }}</td>
                            <td>{{ $stage->auditor_team ?: '-' }}</td>
                            <td>
                                @forelse ($stage->files as $file)
                                    <a class="btn btn-light btn-sm" href="{{ route('secure-files.audit', $file) }}">{{ $file->original_name }}</a>
                                @empty
                                    -
                                @endforelse
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Belum ada tahap audit.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card mt-2">
        <h2>Buat Temuan</h2>
        <form method="post" action="{{ route('audit.findings.store', $application) }}">
            @csrf
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Nomor Temuan</label>
                    <input class="form-control" name="finding_number" placeholder="NC-01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Jenis</label>
                    <select class="form-select" name="finding_type">
                        <option value="major">Major</option>
                        <option value="minor">Minor</option>
                        <option value="observation">Observasi</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Klausul</label>
                    <input class="form-control" name="clause_reference">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Deskripsi Temuan</label>
                <textarea class="form-textarea" name="description" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Batas Perbaikan</label>
                <input class="form-control" type="date" name="due_date" required>
            </div>
            <button class="btn btn-warning">Terbitkan Temuan</button>
        </form>
    </section>

    <section class="card mt-2">
        <h2>Review Tindakan Koreksi</h2>
        @forelse ($application->findings as $finding)
            <div style="padding:18px 0;border-bottom:1px solid var(--line)">
                <div class="flex justify-between">
                    <strong>{{ $finding->finding_number }} · {{ $finding->finding_type }}</strong>
                    <span class="badge badge-{{ $finding->status === 'closed' ? 'success' : 'warning' }}">{{ $finding->status }}</span>
                </div>
                <p>{{ $finding->description }}</p>
                @foreach ($finding->correctiveActions as $ca)
                    <div class="alert alert-info">
                        <strong>Jawaban revisi {{ $ca->revision }}</strong>
                        <p><b>Akar penyebab:</b> {{ $ca->root_cause }}</p>
                        <p><b>Koreksi:</b> {{ $ca->correction }}</p>
                        <p><b>Tindakan korektif:</b> {{ $ca->corrective_action }}</p>
                        @if ($ca->files->isNotEmpty())
                            <p><b>Bukti:</b>
                                @foreach ($ca->files as $evidence)
                                    <a class="btn btn-light btn-sm" href="{{ route('secure-files.corrective-action', $evidence) }}">{{ $evidence->original_name }}</a>
                                @endforeach
                            </p>
                        @endif
                        <form method="post" action="{{ route('audit.corrective-actions.review', $ca) }}">
                            @csrf
                            <div class="grid-3">
                                <div class="form-group">
                                    <label class="form-label">Keputusan</label>
                                    <select class="form-select" name="status">
                                        <option value="sufficient">Cukup</option>
                                        <option value="accepted">Diterima</option>
                                        <option value="insufficient">Belum Cukup</option>
                                        <option value="revision">Revisi</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Tanggal</label>
                                    <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Catatan</label>
                                    <input class="form-control" name="notes" required>
                                </div>
                            </div>
                            <button class="btn btn-primary btn-sm">Simpan Review</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="empty">Belum ada temuan.</div>
        @endforelse
    </section>

    @if ($application->status === 'qms_audit')
        <section class="card mt-2">
            <h2>Selesaikan Audit Tanpa Temuan Terbuka</h2>
            <p class="muted">Gunakan tindakan ini hanya setelah laporan audit lengkap dan tidak ada temuan yang masih terbuka. Order akan diteruskan ke Tim Teknis.</p>
            <form method="post" action="{{ route('audit.complete', $application) }}"
                  data-confirm="Selesaikan audit dan teruskan order ke Tim Teknis?"
                  data-confirm-title="Selesai Audit"
                  data-confirm-yes="Ya, selesaikan">
                @csrf
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tanggal Keputusan</label>
                        <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Kesimpulan Audit</label>
                        <textarea class="form-textarea" name="notes" required placeholder="Nyatakan bahwa seluruh bukti audit telah ditinjau dan tidak ada temuan terbuka."></textarea>
                    </div>
                </div>
                <button class="btn btn-success">Selesai Audit &amp; Kirim ke Tim Teknis</button>
            </form>
        </section>
    @endif
@endsection
