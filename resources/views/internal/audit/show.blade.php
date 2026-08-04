@extends('layouts.app')

@section('title', 'Audit ' . $application->order_number)

@section('content')
    @php
        $userScopes = auth()->user()->hasRole('superadmin')
            ? ['all', 'stage_1', 'stage_2', 'qms', 'corrective_action']
            : $application->auditAssignments->where('auditor_id', auth()->id())->where('status', 'assigned')->pluck('stage_code')->unique()->toArray();
        $hasScope = function($s) use ($userScopes) {
            return in_array('all', $userScopes, true) || in_array($s, $userScopes, true);
        };

        $assignedTeam = $application->auditAssignments->where('status', 'assigned');
        $currentUserAssignment = $assignedTeam->where('auditor_id', auth()->id())->first();
        $auditorRoleLabel = $currentUserAssignment ? $currentUserAssignment->assignment_role : (auth()->user()->hasRole('superadmin') ? 'Superadmin' : '-');
        $scopeLabel = $currentUserAssignment ? strtoupper(str_replace('_', ' ', $currentUserAssignment->stage_code)) : (auth()->user()->hasRole('superadmin') ? 'SEMUA TAHAP' : '-');

        $totalFindings = $application->findings->count();
        $waitingClientReplies = 0;
        $waitingAuditorReviews = 0;
        $closedActions = 0;

        foreach ($application->findings as $f) {
            if ($f->status === 'closed') {
                $closedActions++;
            } elseif ($f->correctiveActions->isEmpty() || $f->correctiveActions->last()->status === 'draft') {
                $waitingClientReplies++;
            } elseif (in_array($f->correctiveActions->last()->status, ['submitted', 'revision_submitted'], true)) {
                $waitingAuditorReviews++;
            } elseif ($f->status === 'revision' || $f->correctiveActions->last()->status === 'revision') {
                $waitingClientReplies++;
            }
        }
    @endphp

    <div class="page-head">
        <div>
            <h1>{{ $application->order_number }}</h1>
            <p>{{ $application->company_name }} · {{ $application->scheme->short_name }}</p>
        </div>
        <span class="badge badge-info">@statuslabel($application->status)</span>
    </div>

    <div class="tabs" id="audit-tabs">
        <a class="tab active" href="#ringkasan" data-tab="ringkasan">Ringkasan</a>
        <a class="tab" href="#stage_1" data-tab="stage_1">Stage 1</a>
        <a class="tab" href="#stage_2" data-tab="stage_2">Stage 2</a>
        <a class="tab" href="#qms" data-tab="qms">QMS/Lapangan</a>
        <a class="tab" href="#corrective_action" data-tab="corrective_action">Corrective Action</a>
    </div>

    <div class="tab-pane" id="ringkasan">
        <section class="card">
            <h2>Ringkasan Penugasan &amp; Status Audit</h2>
            <div class="grid-3 mt-2">
                <div>
                    <label class="form-label muted">Nomor Order / Klien</label>
                    <p><strong>{{ $application->order_number }}</strong><br>{{ $application->company_name }}</p>
                </div>
                <div>
                    <label class="form-label muted">Skema Sertifikasi</label>
                    <p><strong>{{ $application->scheme->name }}</strong><br>({{ $application->scheme->short_name }})</p>
                </div>
                <div>
                    <label class="form-label muted">Status Proses Saat Ini</label>
                    <p><span class="badge badge-info">@statuslabel($application->status)</span></p>
                </div>
                <div>
                    <label class="form-label muted">Peran Auditor (Anda)</label>
                    <p><strong>{{ $auditorRoleLabel }}</strong></p>
                </div>
                <div>
                    <label class="form-label muted">Lingkup Penugasan (Anda)</label>
                    <p><strong>{{ $scopeLabel }}</strong></p>
                </div>
            </div>

            <h3 class="mt-3">Statistik Temuan &amp; Corrective Action</h3>
            <div class="grid-4 mt-2">
                <div class="card" style="padding: 12px; background: var(--bg-alt); border: 1px solid var(--line);">
                    <div class="small muted">Jumlah Temuan</div>
                    <div style="font-size: 1.5rem; font-weight: 700;">{{ $totalFindings }}</div>
                </div>
                <div class="card" style="padding: 12px; background: var(--bg-alt); border: 1px solid var(--line);">
                    <div class="small muted">Menunggu Jawaban Klien</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #b42318;">{{ $waitingClientReplies }}</div>
                </div>
                <div class="card" style="padding: 12px; background: var(--bg-alt); border: 1px solid var(--line);">
                    <div class="small muted">Menunggu Review Auditor</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #b7791f;">{{ $waitingAuditorReviews }}</div>
                </div>
                <div class="card" style="padding: 12px; background: var(--bg-alt); border: 1px solid var(--line);">
                    <div class="small muted">Sudah Ditutup</div>
                    <div style="font-size: 1.5rem; font-weight: 700; color: #027a48;">{{ $closedActions }}</div>
                </div>
            </div>

            <h3 class="mt-3">Tim Auditor yang Ditugaskan</h3>
            <div class="table-wrap mt-2">
                <table class="table">
                    <thead>
                        <tr><th>Nama Auditor</th><th>Peran</th><th>Lingkup Penugasan</th><th>Tanggal Ditugaskan</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($assignedTeam as $asg)
                            <tr>
                                <td>{{ $asg->auditor?->name ?: '-' }}</td>
                                <td>{{ $asg->assignment_role }}</td>
                                <td>{{ strtoupper(str_replace('_', ' ', $asg->stage_code)) }}</td>
                                <td>{{ optional($asg->assigned_date)->format('d M Y') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty">Belum ada tim auditor yang ditugaskan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="tab-pane" id="stage_1" style="display: none;">
        @if ($hasScope('stage_1'))
            <section class="card">
                <h2>Pencatatan Audit Stage 1</h2>
                <form method="post" action="{{ route('audit.stage', $application) }}" enctype="multipart/form-data" data-ajax>
                    @csrf
                    <input type="hidden" name="stage_code" value="stage_1">
                    <div class="grid-3">
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
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">File Laporan Stage 1</label>
                            <input class="form-control" type="file" name="report[]" multiple>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ringkasan Hasil</label>
                        <textarea class="form-textarea" name="summary"></textarea>
                    </div>
                    <button class="btn btn-primary">Simpan Tahap Audit</button>
                </form>

                @php
                    // Sengaja bentuk blok, bukan bentuk inline berargumen:
                    // file ini memakai blok PHP di bawah, dan Blade akan
                    // memasangkan pembuka inline dengan penutup blok terdekat
                    // berikutnya sehingga isi di antaranya ikut tertelan.
                    $skipInfo = $stageSkip['stage_1'];
                    $alreadySkipped = $application->auditStages->firstWhere('stage_code', 'stage_1')?->status === 'skipped';
                @endphp
                <details class="mt-2">
                    <summary><strong>Skip tahap opsional (Stage 1)</strong></summary>
                    @unless ($skipInfo['allowed'])
                        <div class="alert alert-warning mt-2">{{ $skipInfo['reason'] }}</div>
                    @endunless
                    @if ($alreadySkipped)
                        <div class="alert alert-info mt-2">Tahap ini sudah dilewati.</div>
                    @endif
                    <form class="mt-2" method="post" action="{{ route('audit.stage.skip', $application) }}">
                        @csrf
                        <input type="hidden" name="stage_code" value="stage_1">
                        {{-- fieldset disabled mematikan input sekaligus tombol, dan
                             input disabled tidak ikut terkirim ke server. --}}
                        <fieldset @disabled(! $skipInfo['allowed'] || $alreadySkipped) style="border:0;padding:0;margin:0">
                            <div class="grid-2">
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
                        </fieldset>
                    </form>
                </details>
            </section>
        @else
            <section class="card">
                <div class="alert alert-secondary" style="background: var(--bg-alt); border: 1px solid var(--line); color: var(--muted); padding: 16px; border-radius: 6px;">
                    Bagian ini tidak termasuk dalam lingkup penugasan Anda.
                </div>
            </section>
        @endif

        <section class="card mt-2">
            <h3>Riwayat Audit Stage 1</h3>
            @php
                $stage1History = $application->auditStages->where('stage_code', 'stage_1');
            @endphp
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Tahap</th><th>Status</th><th>Tanggal</th><th>Mandays</th><th>Tim</th><th>File</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($stage1History as $stage)
                            <tr>
                                <td>STAGE 1</td>
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
                            <tr><td colspan="6" class="empty">Tahap ini belum dimulai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="tab-pane" id="stage_2" style="display: none;">
        @if ($hasScope('stage_2'))
            <section class="card">
                <h2>Pencatatan Audit Stage 2</h2>
                <form method="post" action="{{ route('audit.stage', $application) }}" enctype="multipart/form-data" data-ajax>
                    @csrf
                    <input type="hidden" name="stage_code" value="stage_2">
                    <div class="grid-3">
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
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">File Laporan Stage 2</label>
                            <input class="form-control" type="file" name="report[]" multiple>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ringkasan Hasil</label>
                        <textarea class="form-textarea" name="summary"></textarea>
                    </div>
                    <button class="btn btn-primary">Simpan Tahap Audit</button>
                </form>

                @php
                    $skipInfo = $stageSkip['stage_2'];
                    $alreadySkipped = $application->auditStages->firstWhere('stage_code', 'stage_2')?->status === 'skipped';
                @endphp
                <details class="mt-2">
                    <summary><strong>Skip tahap opsional (Stage 2)</strong></summary>
                    @unless ($skipInfo['allowed'])
                        <div class="alert alert-warning mt-2">{{ $skipInfo['reason'] }}</div>
                    @endunless
                    @if ($alreadySkipped)
                        <div class="alert alert-info mt-2">Tahap ini sudah dilewati.</div>
                    @endif
                    <form class="mt-2" method="post" action="{{ route('audit.stage.skip', $application) }}">
                        @csrf
                        <input type="hidden" name="stage_code" value="stage_2">
                        <fieldset @disabled(! $skipInfo['allowed'] || $alreadySkipped) style="border:0;padding:0;margin:0">
                            <div class="grid-2">
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
                        </fieldset>
                    </form>
                </details>
            </section>
        @else
            <section class="card">
                <div class="alert alert-secondary" style="background: var(--bg-alt); border: 1px solid var(--line); color: var(--muted); padding: 16px; border-radius: 6px;">
                    Bagian ini tidak termasuk dalam lingkup penugasan Anda.
                </div>
            </section>
        @endif

        <section class="card mt-2">
            <h3>Riwayat Audit Stage 2</h3>
            @php
                $stage2History = $application->auditStages->where('stage_code', 'stage_2');
            @endphp
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Tahap</th><th>Status</th><th>Tanggal</th><th>Mandays</th><th>Tim</th><th>File</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($stage2History as $stage)
                            <tr>
                                <td>STAGE 2</td>
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
                            <tr><td colspan="6" class="empty">Tahap ini belum dimulai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="tab-pane" id="qms" style="display: none;">
        @if ($hasScope('qms'))
            <section class="card">
                <h2>Pencatatan Audit QMS / Audit Lapangan</h2>
                <form method="post" action="{{ route('audit.stage', $application) }}" enctype="multipart/form-data" data-ajax>
                    @csrf
                    <input type="hidden" name="stage_code" value="qms">
                    <div class="grid-3">
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
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">File Laporan QMS / Lapangan</label>
                            <input class="form-control" type="file" name="report[]" multiple>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ringkasan Hasil</label>
                        <textarea class="form-textarea" name="summary"></textarea>
                    </div>
                    <button class="btn btn-primary">Simpan Tahap Audit</button>
                </form>
            </section>

            <section class="card mt-2">
                <h2>Buat Temuan Audit</h2>
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

            @if (in_array($application->status, ['qms_audit', 'corrective_action'], true))
                @php
                    $qmsStage = $application->auditStages->where('stage_code', 'qms')->first();
                    $hasOpenFindings = $application->findings->where('status', '!=', 'closed')->isNotEmpty();
                @endphp
                <section class="card mt-2">
                    @if (!$qmsStage)
                        <div class="alert alert-warning" style="margin: 0;">
                            <strong>Informasi Penyelesaian Audit:</strong> Tahap QMS/Lapangan belum dimulai.
                        </div>
                    @elseif ($qmsStage->status === 'uploaded')
                        <div class="alert alert-warning" style="margin: 0;">
                            <strong>Informasi Penyelesaian Audit:</strong> Laporan QMS/Lapangan sudah diunggah dan belum berstatus Disetujui.
                        </div>
                    @elseif ($qmsStage->status === 'revision')
                        <div class="alert alert-warning" style="margin: 0;">
                            <strong>Informasi Penyelesaian Audit:</strong> Laporan QMS/Lapangan perlu direvisi sebelum audit dapat diselesaikan.
                        </div>
                    @elseif ($qmsStage->status === 'approved' && $hasOpenFindings)
                        <div class="alert alert-warning" style="margin: 0;">
                            <strong>Informasi Penyelesaian Audit:</strong> Masih terdapat temuan terbuka. Selesaikan proses Corrective Action.
                        </div>
                    @elseif ($qmsStage->status === 'approved' && !$hasOpenFindings)
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; padding: 12px; background: var(--bg-alt); border: 2px dashed #10b981; border-radius: 6px;">
                            <div>
                                <h3 style="margin: 0; color: #10b981;">Selesaikan Audit &amp; Kirim ke Tim Teknis</h3>
                                <p style="margin: 4px 0 0; color: var(--muted); font-size: 0.9rem;">
                                    Pemeriksaan QMS/Lapangan telah disetujui dan tidak ada temuan terbuka. Anda dapat langsung mengirimkannya ke Tim Teknis.
                                </p>
                            </div>
                            <form method="post" action="{{ route('audit.complete', $application) }}" data-confirm="Apakah Anda yakin ingin menyelesaikan audit ini dan meneruskan permohonan ke Tim Teknis?" data-confirm-title="Selesaikan Audit" data-confirm-yes="Ya, Selesaikan &amp; Kirim" data-ajax>
                                @csrf
                                <input type="hidden" name="action_date" value="{{ now()->format('Y-m-d') }}">
                                <input type="hidden" name="notes" value="Audit selesai tanpa temuan terbuka.">
                                <button type="submit" class="btn btn-success" style="font-weight: 600; padding: 10px 20px;">
                                    Selesai Audit &amp; Kirim ke Tim Teknis
                                </button>
                            </form>
                        </div>
                    @endif
                </section>
            @endif
        @else
            <section class="card">
                <div class="alert alert-secondary" style="background: var(--bg-alt); border: 1px solid var(--line); color: var(--muted); padding: 16px; border-radius: 6px;">
                    Bagian ini tidak termasuk dalam lingkup penugasan Anda.
                </div>
            </section>
        @endif

        <section class="card mt-2">
            <h3>Riwayat Audit QMS / Lapangan</h3>
            @php
                $qmsHistory = $application->auditStages->where('stage_code', 'qms');
            @endphp
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Tahap</th><th>Status</th><th>Tanggal</th><th>Mandays</th><th>Tim</th><th>File</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($qmsHistory as $stage)
                            <tr>
                                <td>QMS / LAPANGAN</td>
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
                            <tr><td colspan="6" class="empty">Tahap ini belum dimulai.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="tab-pane" id="corrective_action" style="display: none;">
        @if ($hasScope('corrective_action'))
            @if ($application->findings->isEmpty())
                <section class="card">
                    <div class="alert alert-info">
                        <strong>Tidak diperlukan</strong>
                        <p>Tidak ada temuan pada permohonan ini. Tindakan koreksi tidak diperlukan.</p>
                    </div>
                </section>
            @else
                @php
                    $allClosed = $application->findings->where('status', '!=', 'closed')->isEmpty();
                    $hasRevision = $application->findings->where('status', 'revision')->isNotEmpty();
                    $hasWaitingClient = false;
                    foreach ($application->findings as $f) {
                        if ($f->status !== 'closed' && ($f->correctiveActions->isEmpty() || $f->correctiveActions->last()->status === 'draft')) {
                            $hasWaitingClient = true;
                            break;
                        }
                    }
                    $latestReview = null;
                    if ($allClosed) {
                        $allReviews = collect();
                        foreach ($application->findings as $f) {
                            foreach ($f->correctiveActions as $ca) {
                                foreach ($ca->reviews as $rev) {
                                    $allReviews->push($rev);
                                }
                            }
                        }
                        $latestReview = $allReviews->sortByDesc('action_date')->first();
                    }
                @endphp

                @if ($allClosed)
                    <section class="card">
                        <div class="alert alert-success">
                            <strong>Selesai</strong>
                            <p>Seluruh tindakan koreksi telah diterima.</p>
                            @if ($latestReview)
                                <span class="small muted">Ditutup pada {{ $latestReview->action_date->format('d M Y') }}</span>
                            @endif
                        </div>
                    </section>
                @elseif ($hasRevision)
                    <section class="card">
                        <div class="alert alert-warning">
                            <strong>Perlu Revisi</strong>
                            <p>Menunggu perbaikan Klien. Terdapat {{ $application->findings->where('status', 'revision')->count() }} item tindakan koreksi yang perlu revisi.</p>
                        </div>
                    </section>
                @elseif ($hasWaitingClient)
                    <section class="card">
                        <div class="alert alert-warning">
                            <strong>Menunggu Klien</strong>
                            <p>Menunggu tindakan koreksi dari Klien.</p>
                        </div>
                    </section>
                @endif

                <section class="card mt-2">
                    <h2>Daftar Temuan Audit</h2>
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>No. Temuan</th><th>Kategori</th><th>Uraian</th><th>Batas Waktu</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($application->findings as $finding)
                                    <tr>
                                        <td><strong>{{ $finding->finding_number }}</strong></td>
                                        <td><span class="badge badge-{{ $finding->finding_type === 'major' ? 'danger' : 'info' }}">{{ strtoupper($finding->finding_type) }}</span></td>
                                        <td>{{ $finding->description }}</td>
                                        <td>{{ optional($finding->due_date)->format('d M Y') ?: '-' }}</td>
                                        <td><span class="badge badge-{{ $finding->status === 'closed' ? 'success' : ($finding->status === 'revision' ? 'danger' : 'warning') }}">{{ $finding->status }}</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card mt-2">
                    <h2>Review Tindakan Koreksi</h2>
                    @foreach ($application->findings as $finding)
                        <div style="padding:18px 0;border-bottom:1px solid var(--line)">
                            <div class="flex justify-between">
                                <strong>{{ $finding->finding_number }} · {{ strtoupper($finding->finding_type) }}</strong>
                                <span class="badge badge-{{ $finding->status === 'closed' ? 'success' : 'warning' }}">{{ $finding->status }}</span>
                            </div>
                            <p class="mt-1">{{ $finding->description }}</p>

                            @if ($finding->correctiveActions->isEmpty() || $finding->correctiveActions->last()->status === 'draft')
                                <p class="muted small mt-1">Menunggu tindakan koreksi dari Klien.</p>
                            @else
                                @foreach ($finding->correctiveActions as $ca)
                                    @if ($ca->status !== 'draft')
                                        <div class="alert alert-info mt-2">
                                            <div class="flex justify-between">
                                                <strong>Jawaban Klien (Revisi {{ $ca->revision }})</strong>
                                                <span class="small muted">Dikirim {{ optional($ca->submitted_at ?: $ca->created_at)->format('d M Y H:i') }}</span>
                                            </div>
                                            <p><b>Akar penyebab:</b> {{ $ca->root_cause ?: '-' }}</p>
                                            <p><b>Koreksi:</b> {{ $ca->correction ?: '-' }}</p>
                                            <p><b>Tindakan korektif:</b> {{ $ca->corrective_action ?: '-' }}</p>
                                            @if ($ca->files->isNotEmpty())
                                                <p><b>Bukti pendukung:</b>
                                                    @foreach ($ca->files as $evidence)
                                                        <a class="btn btn-light btn-sm" href="{{ route('secure-files.corrective-action', $evidence) }}">{{ $evidence->original_name }}</a>
                                                    @endforeach
                                                </p>
                                            @endif

                                            @if ($ca->reviews->isNotEmpty())
                                                <div class="mt-2" style="background: rgba(0,0,0,0.05); padding: 8px; border-radius: 4px;">
                                                    <strong>Catatan Review Sebelumnya:</strong>
                                                    @foreach ($ca->reviews as $rev)
                                                        <p class="small mb-0">[{{ $rev->status }}] {{ $rev->notes }} ({{ optional($rev->action_date)->format('d M Y') }})</p>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if ($finding->status !== 'closed' && in_array($ca->status, ['submitted', 'revision_submitted'], true))
                                                <form method="post" action="{{ route('audit.corrective-actions.review', $ca) }}" class="mt-2">
                                                    @csrf
                                                    <div class="grid-3">
                                                        <div class="form-group">
                                                            <label class="form-label">Keputusan</label>
                                                            <select class="form-select" name="status">
                                                                <option value="accepted">Diterima / Cukup</option>
                                                                <option value="revision">Perlu Revisi / Belum Cukup</option>
                                                                <option value="sufficient">Cukup</option>
                                                                <option value="insufficient">Belum Cukup</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Tanggal Review</label>
                                                            <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="form-label">Catatan Auditor</label>
                                                            <input class="form-control" name="notes" required placeholder="Tuliskan alasan atau catatan review">
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-primary btn-sm">Simpan Review</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    @endforeach
                </section>
            @endif
        @else
            <section class="card">
                <div class="alert alert-secondary" style="background: var(--bg-alt); border: 1px solid var(--line); color: var(--muted); padding: 16px; border-radius: 6px;">
                    Bagian ini tidak termasuk dalam lingkup penugasan Anda.
                </div>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
<script>
(function() {
    function getStoredTab() {
        const hash = window.location.hash.replace('#', '');
        if (hash && ['ringkasan', 'stage_1', 'stage_2', 'qms', 'corrective_action'].includes(hash)) {
            return hash;
        }
        return sessionStorage.getItem('audit_active_tab_{{ $application->id }}') || 'ringkasan';
    }

    function switchTab(targetId) {
        const tabs = document.querySelectorAll('#audit-tabs .tab');
        const panes = document.querySelectorAll('.tab-pane');
        if (!document.getElementById(targetId)) targetId = 'ringkasan';

        tabs.forEach(t => {
            if (t.dataset.tab === targetId) {
                t.classList.add('active');
            } else {
                t.classList.remove('active');
            }
        });
        panes.forEach(p => {
            if (p.id === targetId) {
                p.style.display = 'block';
            } else {
                p.style.display = 'none';
            }
        });

        sessionStorage.setItem('audit_active_tab_{{ $application->id }}', targetId);
        if (history.replaceState) {
            history.replaceState(null, null, '#' + targetId);
        }
    }

    // Gunakan Event Delegation pada document agar tetap bekerja bahkan setelah reload/AJAX refresh #page-content!
    document.addEventListener('click', function(e) {
        const tabBtn = e.target.closest('#audit-tabs .tab');
        if (tabBtn && tabBtn.dataset.tab) {
            e.preventDefault();
            switchTab(tabBtn.dataset.tab);
        }
    });

    function initActiveTab() {
        switchTab(getStoredTab());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initActiveTab);
    } else {
        initActiveTab();
    }

    // Amati perubahan pada #page-content oleh refreshContent (data-ajax)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && document.getElementById('audit-tabs')) {
                initActiveTab();
            }
        });
    });

    const pageContent = document.getElementById('page-content') || document.body;
    observer.observe(pageContent, { childList: true, subtree: false });
})();
</script>
@endpush
