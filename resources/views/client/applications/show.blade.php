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
@endsection
