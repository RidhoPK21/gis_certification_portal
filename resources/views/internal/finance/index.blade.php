@extends('layouts.app')

@section('title', 'Invoice & Pembayaran')

@section('content')
    <div class="page-head">
        <div>
            <h1>Invoice &amp; Pembayaran</h1>
            <p>Status pelunasan dipisahkan dari milestone tahap 1, 2, dan 3 agar tidak ambigu.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Klien</th>
                    <th>Skema</th>
                    <th>Invoice</th>
                    <th>Status Bayar</th>
                    <th>Milestone</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    <tr>
                        <td><strong>{{ $app->order_number }}</strong></td>
                        <td>{{ $app->company_name }}<div class="small muted">{{ $app->client->name }}</div></td>
                        <td>{{ $app->scheme->short_name }}</td>
                        <td>{{ $app->invoice?->invoice_number ?: 'Belum dibuat' }}</td>
                        <td>
                            <span class="badge badge-{{ $app->invoice?->payment_status === 'paid' ? 'success' : ($app->invoice ? 'warning' : 'neutral') }}">
                                {{ $app->invoice?->payment_status ?: '-' }}
                            </span>
                        </td>
                        <td>{{ $app->invoice?->current_milestone ?: 0 }}</td>
                        <td><a class="btn btn-primary btn-sm" href="{{ route('finance.show', $app) }}">Proses</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">Tidak ada order pada antrean Finance.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
@endsection
