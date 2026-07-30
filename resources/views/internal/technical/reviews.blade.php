@extends('layouts.app')

@section('title', 'Tinjauan Teknis')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tinjauan Teknis</h1>
            <p>Permohonan yang diteruskan Admin untuk dinilai aspek teknisnya. Hasil tinjauan Anda beserta tanda tangan akan tercetak pada PDF tinjauan.</p>
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
            <div class="small muted">Filter antrean tinjauan teknis.</div>
            <div class="flex gap-1">
                @if (request()->hasAny(['q', 'scheme_id']) && array_filter(request()->only(['q', 'scheme_id'])))
                    <a class="btn btn-light" href="{{ route('technical.reviews.index') }}">Reset</a>
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    <tr>
                        <td style="border-left: 4px solid {{ $app->scheme->accent_color }};">
                            <strong>{{ $app->order_number }}</strong>
                        </td>
                        <td>{{ $app->company_name }}</td>
                        <td><x-scheme-badge :scheme="$app->scheme" /></td>
                        <td><span class="badge badge-info">@statuslabel($app->status)</span></td>
                        <td><a class="btn btn-primary btn-sm" href="{{ route('technical.reviews.show', $app) }}">Tinjau</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">Tidak ada permohonan yang menunggu tinjauan teknis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
@endsection
