@extends('layouts.app')

@section('title', 'Invoice & Pembayaran')

@section('content')
    <div class="page-head">
        <div>
            <h1>Invoice &amp; Pembayaran</h1>
            <p>Status pelunasan dipisahkan dari milestone tahap 1, 2, dan 3 agar tidak ambigu.</p>
        </div>
    </div>

    <form class="card" method="get">
        <div class="grid-2">
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
        </div>
        <div class="flex justify-between items-center mt-1">
            <div class="small muted">Filter antrean berdasarkan skema atau nomor order.</div>
            <div class="flex gap-1">
                @if (request()->hasAny(['q', 'scheme_id']) && array_filter(request()->only(['q', 'scheme_id'])))
                    <a class="btn btn-light" href="{{ route('finance.index') }}">Reset</a>
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
                    <th>Invoice</th>
                    <th>Status Bayar</th>
                    <th>Milestone</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    <tr>
                        <td style="border-left: 4px solid {{ $app->scheme->accent_color }};">
                            <strong>{{ $app->order_number }}</strong>
                        </td>
                        <td>{{ $app->company_name }}<div class="small muted">{{ $app->client->name }}</div></td>
                        <td><x-scheme-badge :scheme="$app->scheme" /></td>
                        <td>{{ $app->invoice?->invoice_number ?: 'Belum dibuat' }}</td>
                        <td>
                            <span class="badge badge-{{ $app->invoice?->payment_stage === 'lunas' ? 'success' : ($app->invoice ? 'warning' : 'neutral') }}">
                                {{ $app->invoice?->stageLabel() ?: '-' }}
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
