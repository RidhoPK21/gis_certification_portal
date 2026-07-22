@extends('layouts.app')

@section('title', 'Sertifikat ' . $application->order_number)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $application->order_number }}</h1>
            <p>{{ $application->company_name }} · {{ $application->scheme->short_name }}</p>
        </div>
        <span class="badge badge-info">@statuslabel($application->status)</span>
    </div>

    @if (session('generated_link'))
        <div class="alert alert-success">
            <strong>Link berhasil dibuat</strong>
            <p><code>{{ session('generated_link.url') }}</code></p>
            @if (session('generated_link.password'))
                <p>Password sekali tampil: <strong style="font-size:1.2rem">{{ session('generated_link.password') }}</strong></p>
            @endif
            <p class="small">Simpan informasi ini pada kanal komunikasi yang disetujui GIS. Password tidak disimpan dalam bentuk asli.</p>
        </div>
    @endif

    <div class="grid-2">
        <section class="card">
            <h2>Upload Draft Sertifikat</h2>
            <form method="post" action="{{ route('technical.draft.upload', $application) }}" enctype="multipart/form-data" data-ajax>
                @csrf
                <div class="form-group">
                    <label class="form-label">File Draft PDF</label>
                    <input class="form-control" type="file" name="draft" accept="application/pdf" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan Versi</label>
                    <textarea class="form-textarea" name="notes"></textarea>
                </div>
                <button class="btn btn-primary">Upload Draft</button>
            </form>
            <h3>Versi Draft</h3>
            @forelse ($application->certificateDrafts as $draft)
                <div style="padding:14px 0;border-bottom:1px solid var(--line)">
                    <div class="flex justify-between">
                        <strong>Versi {{ $draft->version }}</strong>
                        <span class="badge badge-info">{{ $draft->status }}</span>
                    </div>
                    <div class="small muted">{{ $draft->original_name }} · {{ $draft->created_at->format('d M Y H:i') }}</div>
                    <form class="mt-1" method="post" action="{{ route('technical.draft.link', $draft) }}">
                        @csrf
                        <div class="flex gap-1 wrap">
                            <input class="form-control" style="max-width:260px" type="datetime-local" name="expires_at">
                            <button class="btn btn-light btn-sm">Generate Link Preview</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="empty">Belum ada draft.</div>
            @endforelse
        </section>

        <section class="card">
            <h2>Upload Sertifikat Final</h2>
            <form method="post" action="{{ route('technical.final.upload', $application) }}" enctype="multipart/form-data" data-ajax
                  data-confirm="Terbitkan sertifikat final untuk order ini?"
                  data-confirm-title="Upload Sertifikat Final"
                  data-confirm-yes="Ya, terbitkan">
                @csrf
                <div class="form-group">
                    <label class="form-label">Nomor Sertifikat</label>
                    <input class="form-control" name="certificate_number" required>
                </div>
                <div class="form-group">
                    <label class="form-label">File Sertifikat PDF</label>
                    <input class="form-control" type="file" name="certificate" accept="application/pdf" required>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tanggal Terbit</label>
                        <input class="form-control" type="date" name="issued_date" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tanggal Berakhir</label>
                        <input class="form-control" type="date" name="expiry_date">
                    </div>
                </div>
                <button class="btn btn-success">Upload Sertifikat Final</button>
            </form>
            @if ($application->certificateFinal)
                <div class="alert alert-success mt-2">
                    <strong>{{ $application->certificateFinal->certificate_number }}</strong>
                    <p>{{ $application->certificateFinal->original_name }}</p>
                    <form method="post" action="{{ route('technical.final.link', $application->certificateFinal) }}">
                        @csrf
                        <div class="flex gap-1 wrap">
                            <input class="form-control" style="max-width:260px" type="datetime-local" name="expires_at">
                            <button class="btn btn-primary btn-sm">Generate Link + Password</button>
                        </div>
                    </form>
                </div>
            @endif
        </section>
    </div>

    <section class="card mt-2">
        <h2>Link &amp; Log Akses</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Jenis</th><th>Dibuat</th><th>Kedaluwarsa</th><th>Status</th><th>Akses</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($links as $link)
                        <tr>
                            <td>{{ $link->link_type }}</td>
                            <td>{{ $link->created_at->format('d M Y H:i') }}</td>
                            <td>{{ $link->expires_at->format('d M Y H:i') }}</td>
                            <td>{{ $link->isUsable() ? 'Aktif' : 'Tidak aktif' }}</td>
                            <td>{{ $link->access_count }}</td>
                            <td>
                                @if ($link->is_active)
                                    <form method="post" action="{{ route('technical.link.revoke', $link) }}"
                                          data-confirm="Nonaktifkan link sertifikat ini? Klien tidak akan bisa mengaksesnya lagi."
                                          data-confirm-title="Nonaktifkan Link"
                                          data-confirm-type="danger"
                                          data-confirm-yes="Ya, nonaktifkan">
                                        @csrf
                                        <button class="btn btn-danger btn-sm">Nonaktifkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty">Belum ada link.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($application->certificateFinal)
        <section class="card mt-2">
            <div class="page-head">
                <div>
                    <h2>Surveillance Planner</h2>
                    <p>Perhitungan rule-assisted yang dapat diaudit; tanggal dapat dikonfirmasi atau disesuaikan Tim Teknis.</p>
                </div>
                <span class="badge badge-info">GIS-SURV-1.0</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Siklus</th><th>Rencana Otomatis</th><th>Jadwal / Aktual / Status / Catatan</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($application->surveillanceSchedules as $schedule)
                            <tr>
                                <td>Surveillance {{ $schedule->cycle }}</td>
                                <td>{{ $schedule->planned_date->format('d M Y') }}</td>
                                <td>
                                    <form method="post" action="{{ route('technical.surveillance.update', $schedule) }}">
                                        @csrf
                                        <div class="flex gap-1 wrap" style="align-items:flex-end">
                                            <div>
                                                <label class="small muted">Jadwal</label>
                                                <input class="form-control" type="date" name="scheduled_date" value="{{ optional($schedule->scheduled_date)->format('Y-m-d') }}">
                                            </div>
                                            <div>
                                                <label class="small muted">Aktual</label>
                                                <input class="form-control" type="date" name="actual_date" value="{{ optional($schedule->actual_date)->format('Y-m-d') }}">
                                            </div>
                                            <div>
                                                <label class="small muted">Status</label>
                                                <select class="form-select" name="status">
                                                    @foreach (['planned' => 'Planning', 'scheduled' => 'Terjadwal', 'completed' => 'Realisasi', 'cancelled' => 'Dibatalkan'] as $value => $label)
                                                        <option value="{{ $value }}" @selected($schedule->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div style="min-width:220px;flex:1">
                                                <label class="small muted">Catatan</label>
                                                <input class="form-control" name="notes" value="{{ $schedule->notes }}">
                                            </div>
                                            <button class="btn btn-light btn-sm">Simpan</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty">Rencana surveillance dibuat otomatis saat sertifikat final diunggah.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($application->status === 'final_certificate')
        <section class="card mt-2">
            <h2>Tutup Proses Sertifikasi</h2>
            <p class="muted">Pastikan nomor sertifikat, file final, dan link aman sudah diperiksa.</p>
            <form method="post" action="{{ route('technical.complete', $application) }}"
                  data-confirm="Tutup proses sertifikasi dan aktifkan rencana surveillance?"
                  data-confirm-title="Tandai Selesai"
                  data-confirm-yes="Ya, selesai">
                @csrf
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Tanggal Penyelesaian</label>
                        <input class="form-control" type="date" name="action_date" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Catatan Penutupan</label>
                        <textarea class="form-textarea" name="notes" required>Proses sertifikasi selesai dan sertifikat final telah dirilis.</textarea>
                    </div>
                </div>
                <button class="btn btn-success">Tandai Selesai</button>
            </form>
        </section>
    @endif
@endsection
