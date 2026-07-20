@extends('layouts.app')

@section('title', 'Audit & Temuan')

@section('content')
    <div class="page-head">
        <div>
            <h1>Audit &amp; Temuan</h1>
            <p>Stage 1 dan Stage 2 mengikuti konfigurasi per skema. Skip selalu membutuhkan alasan.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Klien</th>
                    <th>Skema</th>
                    <th>Status</th>
                    <th>Tahap Tercatat</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    <tr>
                        <td><strong>{{ $app->order_number }}</strong></td>
                        <td>{{ $app->company_name }}</td>
                        <td>{{ $app->scheme->short_name }}</td>
                        <td><span class="badge badge-info">@statuslabel($app->status)</span></td>
                        <td>{{ $app->auditStages->pluck('stage_code')->join(', ') ?: '-' }}</td>
                        <td><a class="btn btn-primary btn-sm" href="{{ route('audit.show', $app) }}">Buka Audit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Tidak ada order audit.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
@endsection
