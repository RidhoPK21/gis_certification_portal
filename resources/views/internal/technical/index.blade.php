@extends('layouts.app')

@section('title', 'Draft & Sertifikat Final')

@section('content')
    <div class="page-head">
        <div>
            <h1>Draft &amp; Sertifikat Final</h1>
            <p>Kelola versi draft, link preview ber-watermark, sertifikat final, password, dan masa berlaku.</p>
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
                    <th>Draft</th>
                    <th>Final</th>
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
                        <td>{{ $app->certificateDrafts->count() }} versi</td>
                        <td>{{ $app->certificateFinal?->certificate_number ?: '-' }}</td>
                        <td><a class="btn btn-primary btn-sm" href="{{ route('technical.show', $app) }}">Kelola</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Tidak ada order pada tahap teknis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
@endsection
