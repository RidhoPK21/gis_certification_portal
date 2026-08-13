{{--
    Penugasan tim auditor oleh Tim Teknis.

    Dipakai di halaman tinjauan teknis (menentukan tim yang tercetak pada PDF
    tinjauan) dan nanti di halaman Surat Tugas (mengganti tim sebelum surat
    terbit). Keduanya memakai endpoint yang sama.

    Variabel:
      $application (wajib)
      $auditors    (wajib) kandidat auditor aktif
      $intro       (opsional) kalimat pengantar pengganti bawaan
--}}
<section class="card mt-2" id="penugasan-auditor">
    <div class="page-head">
        <div>
            <h2>Penugasan Tim Auditor</h2>
            <p>{{ $intro ?? 'Tim yang dipilih di sini tercetak pada PDF tinjauan dan mengisi tabel auditor pada Surat Tugas. Auditor baru dapat bekerja setelah Surat Tugas tahapnya diterbitkan.' }}</p>
        </div>
    </div>
    <div class="grid-2">
        <form method="post" action="{{ route('technical.audit-assignments.store', $application) }}">
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
                    <label class="form-label">Lingkup Penugasan Auditor</label>
                    <select class="form-select" name="stage_code">
                        <option value="all">Semua Tahap</option>
                        <option value="stage_1">Stage 1</option>
                        <option value="stage_2">Stage 2</option>
                        <option value="qms">QMS/Lapangan</option>
                        <option value="corrective_action">Corrective Action</option>
                    </select>
                    <small class="text-muted d-block mt-1">Lingkup menentukan bagian proses yang dapat dilihat dan dikerjakan oleh Auditor yang ditugaskan.</small>
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
                        <tr><th>Nama</th><th>Peran</th><th>Lingkup Penugasan Auditor</th><th>Tanggal</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($application->auditAssignments as $assignment)
                            <tr>
                                <td>{{ $assignment->auditor?->name ?: '-' }}</td>
                                <td>{{ $assignment->assignment_role }}</td>
                                <td>{{ $assignment->stage_code }}</td>
                                <td>{{ optional($assignment->assigned_date)->format('d M Y') }}</td>
                                <td>
                                    <form method="post" action="{{ route('technical.audit-assignments.destroy', $assignment) }}"
                                          data-confirm="Keluarkan {{ $assignment->auditor?->name ?: 'auditor ini' }} dari penugasan order ini?"
                                          data-confirm-title="Hapus Penugasan" data-confirm-type="danger" data-confirm-yes="Ya, hapus">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-light btn-sm">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty">Belum ada auditor yang ditugaskan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
