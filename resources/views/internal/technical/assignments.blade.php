@extends('layouts.app')

@section('title', 'Penugasan & Surat Tugas')

@section('content')
    <div class="page-head">
        <div>
            <h1>Penugasan &amp; Surat Tugas</h1>
            <p>
                Order yang sudah lunas di Finance menunggu Surat Tugas dari Tim Teknis.
                Auditor baru dapat mengerjakan sebuah tahap audit setelah Surat Tugas tahap itu diterbitkan.
            </p>
        </div>
    </div>

    <form class="card" method="get">
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label">Pencarian</label>
                <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Nomor order atau perusahaan">
            </div>
            <div class="form-group">
                <label class="form-label">Skema Sertifikasi</label>
                <select class="form-select" name="scheme_id">
                    <option value="">Semua skema</option>
                    @foreach ($schemes as $scheme)
                        <option value="{{ $scheme->id }}" @selected((int) request('scheme_id') === $scheme->id)>{{ $scheme->short_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Belum terbit untuk tahap</label>
                <select class="form-select" name="stage">
                    <option value="">Semua tahap</option>
                    @foreach ($stages as $code => $label)
                        <option value="{{ $code }}" @selected(request('stage') === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex justify-between items-center mt-1">
            <div class="small muted">Saring order yang Surat Tugasnya belum terbit.</div>
            <div class="flex gap-1">
                @if (request()->hasAny(['q', 'scheme_id', 'stage']) && array_filter(request()->only(['q', 'scheme_id', 'stage'])))
                    <a class="btn btn-light" href="{{ route('technical.assignments.index') }}">Reset</a>
                @endif
                <button class="btn btn-primary" type="submit">Terapkan Filter</button>
            </div>
        </div>
    </form>

    <div class="table-wrap mt-2">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Klien</th>
                    <th>Skema</th>
                    <th>Status</th>
                    <th>Tim Auditor</th>
                    @foreach ($stages as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    @php($letters = $app->assignmentLetters->keyBy('stage_code'))
                    <tr>
                        <td style="border-left: 4px solid {{ $app->scheme->accent_color }};">
                            <strong>{{ $app->order_number }}</strong>
                        </td>
                        <td>{{ $app->company_name }}</td>
                        <td><x-scheme-badge :scheme="$app->scheme" /></td>
                        <td><span class="badge badge-info">@statuslabel($app->status)</span></td>
                        <td>
                            @php($team = $app->auditAssignments->where('status', 'assigned'))
                            @forelse ($team as $assignment)
                                <div class="small">
                                    {{ $assignment->auditor?->name ?? '-' }}
                                    <span class="badge badge-neutral">{{ $assignment->assignment_role }}</span>
                                </div>
                            @empty
                                <span class="small text-danger">Belum ada</span>
                            @endforelse
                        </td>
                        @foreach ($stages as $code => $label)
                            @php($letter = $letters->get($code))
                            <td>
                                @if ($letter && $letter->generated_pdf_id)
                                    <a class="btn btn-light btn-sm" href="{{ route('internal.generated-pdf.download', $letter->generated_pdf_id) }}">
                                        Terbit v{{ $letter->pdf_version }}
                                    </a>
                                @elseif ($letter)
                                    <span class="badge badge-warning">Draft</span>
                                @else
                                    <span class="badge badge-neutral">Belum</span>
                                @endif
                            </td>
                        @endforeach
                        <td><a class="btn btn-primary btn-sm" href="{{ route('technical.assignments.show', $app) }}">Kelola</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 6 + count($stages) }}" class="empty">Belum ada order yang siap diterbitkan Surat Tugasnya.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
@endsection
