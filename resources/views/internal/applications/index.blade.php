@extends('layouts.app')

@section('title', 'Review Permohonan')

@section('content')
    <div class="page-head">
        <div>
            <h1>Review Permohonan</h1>
            <p>Periksa field dan dokumen, minta revisi spesifik, lalu buat tinjauan permohonan.</p>
        </div>
    </div>

    <form class="card" method="get">
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label">Pencarian</label>
                <input class="form-control" name="q" value="{{ request('q') }}" placeholder="Nomor order atau perusahaan">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua status</option>
                    @foreach (['admin_review' => 'Review Admin', 'revision_requested' => 'Revisi Diminta', 'application_approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="align-self:end">
                <button class="btn btn-primary">Terapkan Filter</button>
            </div>
        </div>
    </form>

    <div class="table-wrap mt-2">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Perusahaan</th>
                    <th>Skema</th>
                    <th>Status</th>
                    <th>Submit</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    <tr>
                        <td><strong>{{ $app->order_number ?: 'Draft #' . $app->id }}</strong></td>
                        <td>{{ $app->company_name }}<div class="small muted">{{ $app->client->name }}</div></td>
                        <td>{{ $app->scheme->short_name }}</td>
                        <td>
                            <span class="badge badge-{{ \App\Enums\ApplicationStatus::tryFrom($app->status)?->tone() ?? 'neutral' }}">
                                @statuslabel($app->status)
                            </span>
                        </td>
                        <td>{{ optional($app->submitted_at)->format('d M Y H:i') ?: '-' }}</td>
                        <td><a class="btn btn-primary btn-sm" href="{{ route('internal.applications.show', $app) }}">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Tidak ada permohonan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
@endsection
