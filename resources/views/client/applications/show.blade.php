@extends('layouts.app')

@section('title', 'Detail Permohonan')

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $application->order_number ?: 'Draft #' . $application->id }}</h1>
            <p>{{ $application->scheme->name }} · {{ $application->company_name }}</p>
        </div>
        <div class="flex gap-1 wrap">
            @if ($application->canBeEditedByClient())
                <a class="btn btn-primary" href="{{ route('client.applications.edit', $application) }}">Lanjutkan Pengisian</a>
            @endif
            <span class="badge badge-{{ \App\Enums\ApplicationStatus::tryFrom($application->status)?->tone() ?? 'neutral' }}">
                @statuslabel($application->status)
            </span>
        </div>
    </div>

    <div class="grid-2">
        <section class="card">
            <h2>Ringkasan</h2>
            <dl class="detail-list">
                <dt>Skema</dt><dd>{{ $application->scheme->short_name }}</dd>
                <dt>Nomor order</dt><dd>{{ $application->order_number ?: 'Belum dibuat' }}</dd>
                <dt>Tanggal order</dt><dd>{{ optional($application->order_date)->format('d M Y') ?: '-' }}</dd>
                <dt>Kelengkapan</dt>
                <dd>
                    <div class="progress"><span style="width:{{ $completion }}%"></span></div>
                    <span class="small">{{ $completion }}%</span>
                </dd>
                <dt>Kontak</dt><dd>{{ $application->contact_email }}<br>{{ $application->contact_phone }}</dd>
            </dl>
        </section>
        <section class="card">
            <h2>Timeline Proses</h2>
            <div class="timeline">
                @forelse ($application->statusHistory as $history)
                    <div class="timeline-item done">
                        <div class="timeline-line"><span class="timeline-dot"></span></div>
                        <div class="timeline-content">
                            <h4>@statuslabel($history->to_status)</h4>
                            <p>{{ $history->notes }}</p>
                            <span class="small muted">{{ $history->action_date->format('d M Y H:i') }}</span>
                        </div>
                    </div>
                @empty
                    <div class="empty">Belum ada aktivitas.</div>
                @endforelse
            </div>
        </section>
    </div>

    @if ($trackingQr)
        <section class="card mt-2">
            <h2>QR Pelacakan Permohonan</h2>
            <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap;margin-top:6px">
                {{-- Latar QR sengaja tetap putih di mode gelap: kode QR
                     butuh kontras terang agar tetap terbaca pemindai. --}}
                <div style="background:#ffffff;padding:10px;border:1px solid var(--border);border-radius:10px;line-height:0">
                    {!! $trackingQr !!}
                </div>
                <div style="flex:1;min-width:240px">
                    <p class="small muted" style="margin:0 0 10px">
                        Pindai atau bagikan QR ini untuk memantau progres permohonan
                        <strong>{{ $application->order_number }}</strong> lewat halaman publik.
                        Halaman tersebut hanya menampilkan tahapan beserta tanggalnya dan
                        <strong>tidak memberi akses ke dokumen Anda</strong>.
                    </p>
                    <div class="flex gap-1 wrap">
                        <a class="btn btn-primary btn-sm" href="{{ $trackingQrDownloadUrl }}">Unduh QR</a>
                        <a class="btn btn-light btn-sm" href="{{ $trackingUrl }}" target="_blank" rel="noopener">Buka halaman pelacakan</a>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <section class="card mt-2">
        <h2>Dokumen Permohonan</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dokumen</th>
                        <th>Versi</th>
                        <th>Status Kajian</th>
                        <th>Catatan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($application->documents as $doc)
                        <tr>
                            <td>{{ $doc->document_name }}</td>
                            <td>{{ $doc->currentVersion?->version ?: '-' }}</td>
                            <td>{{ $doc->review_status }}</td>
                            <td>{{ $doc->review_note ?: '-' }}</td>
                            <td>
                                @if ($doc->currentVersion)
                                    <a class="btn btn-light btn-sm" href="{{ route('secure-files.application-document', $doc) }}">Download</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty">Belum ada dokumen.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card mt-2">
        <h2>Invoice &amp; Pembayaran</h2>
        @if ($application->invoice)
            <dl class="detail-list">
                <dt>Nomor Invoice</dt><dd>{{ $application->invoice->invoice_number }}</dd>
                <dt>Nilai</dt><dd>Rp {{ number_format((float) $application->invoice->amount, 0, ',', '.') }}</dd>
                <dt>Status</dt><dd>{{ $application->invoice->stageLabel() }}</dd>
                @if ($application->invoice->file_path)
                    <dt>Dokumen Invoice</dt>
                    <dd><a class="btn btn-light btn-sm" href="{{ route('secure-files.invoice', $application->invoice) }}">Download Invoice</a></dd>
                @endif
            </dl>
        @else
            <div class="empty">Invoice belum diterbitkan.</div>
        @endif
    </section>

    <section class="card mt-2">
        <h2>Audit</h2>
        @forelse ($application->auditStages as $stage)
            <div style="padding:10px 0;border-bottom:1px solid var(--line)">
                <strong>{{ strtoupper(str_replace('_', ' ', $stage->stage_code)) }}</strong>
                <div class="small muted">{{ $stage->status }} · {{ optional($stage->audit_date)->format('d M Y') ?: '-' }}</div>
            </div>
        @empty
            <div class="empty">Tahap audit belum dimulai.</div>
        @endforelse
    </section>

    <section class="card mt-2">
        <h2>Temuan &amp; Tindakan Koreksi</h2>
        @forelse ($application->findings as $finding)
            <div class="alert {{ $finding->status === 'closed' ? 'alert-success' : 'alert-warning' }}">
                <strong>{{ $finding->finding_number }} · {{ ucfirst($finding->finding_type) }}</strong>
                <p>{{ $finding->description }}</p>
                <span class="small">Status: {{ $finding->status }} · Tenggat {{ optional($finding->due_date)->format('d M Y') }}</span>
            </div>
        @empty
            <div class="empty">Tidak ada temuan.</div>
        @endforelse
    </section>

    <section class="card mt-2">
        <h2>Sertifikat</h2>
        @if ($application->certificateFinal)
            <div class="alert alert-success">
                <strong>Sertifikat final telah dirilis.</strong>
                <p>Nomor {{ $application->certificateFinal->certificate_number }}.</p>
            </div>

            @if ($certificateUrl && $certificateLinkActive)
                <a class="btn btn-primary" href="{{ $certificateUrl }}" target="_blank" rel="noopener">
                    Buka Halaman Unduh Sertifikat
                </a>
                <p class="small muted mt-1">
                    Password unduhan dikirim terpisah oleh Tim Teknis GIS melalui kanal
                    komunikasi resmi, dan tidak pernah ditampilkan di portal ini.
                </p>
            @elseif ($certificateUrl)
                <div class="alert alert-warning">
                    Link akses sertifikat sudah kedaluwarsa atau dinonaktifkan.
                    Hubungi Tim Teknis GIS untuk penerbitan link baru.
                </div>
            @else
                <div class="alert alert-info">Link akses akan muncul di sini setelah Tim Teknis membuatnya.</div>
            @endif

            @if ($verifyQr)
                <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-top:18px;padding-top:16px;border-top:1px solid var(--border)">
                    {{-- Latar QR sengaja tetap putih di mode gelap: kode QR
                         butuh kontras terang agar tetap terbaca pemindai. --}}
                    <div style="background:#ffffff;padding:8px;border:1px solid var(--border);border-radius:10px;line-height:0">
                        {!! $verifyQr !!}
                    </div>
                    <div style="flex:1;min-width:220px">
                        <strong>QR verifikasi sertifikat</strong>
                        <p class="small muted" style="margin:6px 0">
                            Bagikan atau cetak QR ini bila ada pihak yang perlu memastikan keaslian
                            sertifikat Anda. QR hanya membuka halaman verifikasi publik dan
                            <strong>tidak memberi akses ke berkas sertifikat</strong>.
                        </p>
                        <a href="{{ $verifyUrl }}" target="_blank" rel="noopener" class="small">Buka halaman verifikasi</a>
                    </div>
                </div>
            @endif
        @elseif ($application->certificateDrafts->count())
            <div class="alert alert-info">Draft sertifikat sudah tersedia. Link preview akan muncul pada notifikasi setelah dibuat Tim Teknis.</div>
        @else
            <div class="empty">Belum ada draft atau sertifikat final.</div>
        @endif
    </section>

    @if ($application->surveillanceSchedules->isNotEmpty())
        <section class="card mt-2">
            <h2>Rencana Surveillance</h2>
            <p class="muted">Tanggal planning dihitung sistem dan dapat dikonfirmasi oleh Tim GIS.</p>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Siklus</th><th>Planning</th><th>Jadwal</th><th>Aktual</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($application->surveillanceSchedules as $schedule)
                            <tr>
                                <td>Surveillance {{ $schedule->cycle }}</td>
                                <td>{{ $schedule->planned_date->format('d M Y') }}</td>
                                <td>{{ optional($schedule->scheduled_date)->format('d M Y') ?: 'Belum ditetapkan' }}</td>
                                <td>{{ optional($schedule->actual_date)->format('d M Y') ?: '-' }}</td>
                                <td><span class="badge badge-info">{{ ucfirst($schedule->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
