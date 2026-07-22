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

    <section class="card mt-2" id="audit-assignment">
        <div class="page-head">
            <div>
                <h2>Penugasan Auditor</h2>
                <p>Auditor hanya dapat membuka dan memproses order yang ditugaskan kepadanya.</p>
            </div>
        </div>
        <div class="grid-2">
            <form method="post" action="{{ route('internal.applications.audit-assignments.store', $application) }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Auditor</label>
                    <select class="form-select" name="auditor_id" required>
                        <option value="">Pilih auditor</option>
                        @foreach ($auditors as $auditor)
                            <option value="{{ $auditor->id }}">{{ $auditor->name }} · {{ $auditor->email }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid-3">
                    <div class="form-group">
                        <label class="form-label">Peran Tim</label>
                        <select class="form-select" name="assignment_role">
                            <option value="LA">Lead Auditor</option>
                            <option value="A">Auditor</option>
                            <option value="TA">Tenaga Ahli</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tahap</label>
                        <select class="form-select" name="stage_code">
                            <option value="all">Semua Tahap</option>
                            <option value="stage_1">Stage 1</option>
                            <option value="stage_2">Stage 2</option>
                            <option value="qms">QMS/Lapangan</option>
                            <option value="corrective_action">Corrective Action</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Penugasan</label>
                        <input class="form-control" type="date" name="assigned_date" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                </div>
                <button class="btn btn-primary">Simpan Penugasan</button>
            </form>
            <div>
                <h3>Tim yang Ditugaskan</h3>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>Nama</th><th>Peran</th><th>Tahap</th><th>Tanggal</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($application->auditAssignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->auditor?->name ?: '-' }}</td>
                                    <td>{{ $assignment->assignment_role }}</td>
                                    <td>{{ $assignment->stage_code }}</td>
                                    <td>{{ optional($assignment->assigned_date)->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty">Belum ada auditor yang ditugaskan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <section class="card mt-2" id="data-form">
        <h2>Data Form Klien</h2>
        @foreach ($application->scheme->sections as $section)
            <h3>{{ $section->title }}</h3>
            <div class="table-wrap">
                <table class="table">
                    <tbody>
                        @foreach ($section->fields as $field)
                            @php($row = $application->values->firstWhere('field_code', $field->code))
                            @php($val = $row?->value_json ?? $row?->value_text)
                            @if (filled($val))
                                <tr>
                                    <th style="width:35%">{{ $field->label }}</th>
                                    <td>{{ is_array($val) ? implode(', ', $val) : $val }} @if ($field->unit){{ $field->unit }}@endif</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    </section>

    <section class="card mt-2" id="dokumen">
        <h2>Kajian Dokumen Administrasi</h2>
        <form method="post" action="{{ route('internal.applications.review', $application) }}">
            @csrf
            <input type="hidden" name="review_type" value="administration">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>No</th><th>Dokumen</th><th>File</th><th>Hasil Kajian</th><th>Keterangan</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($application->scheme->requiredDocuments as $i => $required)
                            @php($doc = $application->documents->firstWhere('document_code', $required->code))
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
                                    @if ($doc?->currentVersion)
                                        <a class="btn btn-light btn-sm" href="{{ route('secure-files.application-document', $doc) }}">{{ $doc->currentVersion->original_name }}</a>
                                        <div class="small muted">v{{ $doc->currentVersion->version }}</div>
                                    @else
                                        <span class="text-danger">Tidak ada</span>
                                    @endif
                                </td>
                                <td>
                                    <select class="form-select" name="items[{{ $i }}][status]">
                                        <option value="pending" @selected(($doc?->review_status ?? 'pending') === 'pending')>Belum dikaji</option>
                                        <option value="sufficient" @selected($doc?->review_status === 'sufficient')>Cukup</option>
                                        <option value="insufficient" @selected($doc?->review_status === 'insufficient')>Belum cukup</option>
                                        <option value="meets" @selected($doc?->review_status === 'meets')>Memenuhi</option>
                                        <option value="not_meets" @selected($doc?->review_status === 'not_meets')>Tidak memenuhi</option>
                                    </select>
                                </td>
                                <td><input class="form-control" name="items[{{ $i }}][notes]" value="{{ $doc?->review_note }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="grid-3 mt-2">
                <div class="form-group">
                    <label class="form-label">Tanggal Tinjauan</label>
                    <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Peninjau</label>
                    <input class="form-control" name="signed_name" value="{{ auth()->user()->name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan Umum</label>
                    <input class="form-control" name="notes">
                </div>
            </div>
            <button class="btn btn-primary">Simpan Kajian Administrasi</button>
        </form>
    </section>

    <section class="card mt-2" id="tinjauan">
        <h2>Tinjauan Teknis &amp; PDF Otomatis</h2>
        <form method="post" action="{{ route('internal.applications.review', $application) }}">
            @csrf
            <input type="hidden" name="review_type" value="technical">
            @php($techFields = ['scope_review_result' => 'Kesesuaian ruang lingkup', 'audit_capability' => 'Kemampuan GIS melakukan audit', 'audit_mandays' => 'Mandays audit', 'required_auditor_competence' => 'Kompetensi auditor', 'assigned_auditor_team' => 'Tim auditor', 'assigned_panelists' => 'Panelis'])
            @foreach ($techFields as $code => $label)
                @php($val = $application->value($code))
                <input type="hidden" name="items[{{ $loop->index }}][type]" value="checklist">
                <input type="hidden" name="items[{{ $loop->index }}][code]" value="{{ $code }}">
                <input type="hidden" name="items[{{ $loop->index }}][label]" value="{{ $label }}">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">{{ $label }}</label>
                        <input class="form-control" value="{{ is_array($val) ? implode(', ', $val) : $val }}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hasil kajian</label>
                        <select class="form-select" name="items[{{ $loop->index }}][status]">
                            <option value="pending">Belum dikaji</option>
                            <option value="sufficient">Cukup/Sesuai</option>
                            <option value="insufficient">Belum cukup/Tidak sesuai</option>
                        </select>
                        <input class="form-control mt-1" name="items[{{ $loop->index }}][notes]" placeholder="Keterangan">
                    </div>
                </div>
            @endforeach
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Tanggal</label>
                    <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Peninjau Teknis</label>
                    <input class="form-control" name="signed_name" value="{{ auth()->user()->name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <input class="form-control" name="notes">
                </div>
            </div>
            <button class="btn btn-primary">Simpan Tinjauan Teknis</button>
        </form>
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

    <section class="card mt-2" id="revisi">
        <h2>Revisi Spesifik</h2>
        <p class="muted">Pilih hanya field/dokumen yang benar-benar perlu diperbaiki. Klien diarahkan langsung ke item tersebut.</p>
        <form method="post" action="{{ route('internal.applications.revision', $application) }}" id="revision-form">
            @csrf
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Pilih</th><th>Item</th><th>Catatan Revisi</th></tr>
                    </thead>
                    <tbody>
                        @php($idx = 0)
                        @foreach ($application->scheme->sections as $section)
                            @foreach ($section->fields as $field)
                                <tr>
                                    <td><input type="checkbox" class="revision-check" data-index="{{ $idx }}"></td>
                                    <td>
                                        <span class="badge badge-neutral">Field</span> {{ $field->label }}
                                        <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][type]" value="field">
                                        <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][code]" value="{{ $field->code }}">
                                        <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][label]" value="{{ $field->label }}">
                                    </td>
                                    <td><input disabled class="form-control revision-input-{{ $idx }}" name="targets[{{ $idx }}][note]" placeholder="Jelaskan perbaikan yang dibutuhkan"></td>
                                </tr>
                                @php($idx++)
                            @endforeach
                        @endforeach
                        @foreach ($application->scheme->requiredDocuments as $required)
                            <tr>
                                <td><input type="checkbox" class="revision-check" data-index="{{ $idx }}"></td>
                                <td>
                                    <span class="badge badge-warning">Dokumen</span> {{ $required->name }}
                                    <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][type]" value="document">
                                    <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][code]" value="{{ $required->code }}">
                                    <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][label]" value="{{ $required->name }}">
                                </td>
                                <td><input disabled class="form-control revision-input-{{ $idx }}" name="targets[{{ $idx }}][note]" placeholder="Jelaskan dokumen yang harus diperbaiki"></td>
                            </tr>
                            @php($idx++)
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="grid-2 mt-2">
                <div class="form-group">
                    <label class="form-label">Batas Perbaikan</label>
                    <input class="form-control" type="date" name="due_date">
                </div>
                <div style="align-self:end">
                    <button class="btn btn-warning">Kirim Permintaan Revisi</button>
                </div>
            </div>
        </form>
        @if ($application->revisions->count())
            <h3>Riwayat Revisi</h3>
            @foreach ($application->revisions->groupBy('revision_round') as $round => $items)
                <div class="alert alert-info">
                    <strong>Putaran {{ $round }}</strong>
                    <div class="table-wrap mt-1">
                        <table class="table">
                            <thead>
                                <tr><th>Item</th><th>Catatan</th><th>Status</th><th>Tindakan Admin</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr>
                                        <td>{{ $item->target_label }}</td>
                                        <td>{{ $item->revision_note }}</td>
                                        <td><span class="badge badge-{{ $item->status === 'resolved' ? 'success' : 'warning' }}">{{ $item->status }}</span></td>
                                        <td>
                                            @if ($item->status !== 'resolved')
                                                <form method="post" action="{{ route('internal.applications.revisions.resolve', [$application, $item]) }}">
                                                    @csrf
                                                    <div class="flex gap-1 wrap">
                                                        <input class="form-control" style="min-width:220px" name="resolution_note" placeholder="Hasil verifikasi perbaikan" required>
                                                        <button class="btn btn-success btn-sm">Tandai Selesai</button>
                                                    </div>
                                                </form>
                                            @else
                                                <span class="small muted">Selesai {{ optional($item->resolved_at)->format('d M Y H:i') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </section>

    @if ($application->status === 'admin_review')
        <section class="grid-2 mt-2">
            <form class="card" method="post" action="{{ route('internal.applications.approve', $application) }}"
                  data-confirm="Setujui permohonan dan teruskan ke Finance? PDF keputusan akan digenerate."
                  data-confirm-title="Setujui Permohonan"
                  data-confirm-yes="Ya, setujui">
                @csrf
                <h2>Setujui Permohonan</h2>
                <div class="form-group">
                    <label class="form-label">Tanggal Keputusan</label>
                    <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea class="form-textarea" name="notes"></textarea>
                </div>
                <button class="btn btn-success">Setujui &amp; Generate PDF</button>
            </form>
            <form class="card" method="post" action="{{ route('internal.applications.reject', $application) }}"
                  data-confirm="Tolak permohonan ini? Tindakan ini menghentikan proses sertifikasi."
                  data-confirm-title="Tolak Permohonan"
                  data-confirm-type="danger"
                  data-confirm-yes="Ya, tolak">
                @csrf
                <h2>Tolak Permohonan</h2>
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
        </section>
    @endif

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

@push('scripts')
<script>document.querySelectorAll('.revision-check').forEach(c=>c.addEventListener('change',()=>{document.querySelectorAll('.revision-input-'+c.dataset.index).forEach(i=>{i.disabled=!c.checked;i.required=c.checked&&i.name.endsWith('[note]')})}));</script>
@endpush
