@extends('layouts.app')

@section('title', 'Permohonan Saya')

@section('content')
    <div class="page-head">
        <div>
            <h1>Permohonan Saya</h1>
            <p>Semua draft dan order sertifikasi perusahaan Anda.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('client.applications.schemes') }}">＋ Ajukan Baru</a>
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
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                    <option value="">Semua status</option>
                    @foreach (\App\Enums\ApplicationStatus::cases() as $st)
                        <option value="{{ $st->value }}" @selected(request('status') === $st->value)>{{ $st->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex justify-between items-center mt-1">
            <div class="small muted">Filter berdasarkan skema, nomor order, atau status permohonan.</div>
            <div class="flex gap-1">
                @if (request()->hasAny(['q', 'scheme_id', 'status']) && array_filter(request()->only(['q', 'scheme_id', 'status'])))
                    <a class="btn btn-light" href="{{ route('client.applications.index') }}">Reset</a>
                @endif
                <button class="btn btn-primary" type="submit">Terapkan Filter</button>
            </div>
        </div>
    </form>

    <div class="table-wrap mt-2">
        <table class="table">
            <thead>
                <tr>
                    <th>Nomor Order</th>
                    <th>Skema</th>
                    <th>Perusahaan</th>
                    <th>Status</th>
                    <th>Diperbarui</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($applications as $app)
                    <tr>
                        <td style="border-left: 4px solid {{ $app->scheme->accent_color }};">
                            <strong>{{ $app->order_number ?: 'Draft #' . $app->id }}</strong>
                            <div class="small muted">{{ optional($app->order_date)->format('d M Y') }}</div>
                        </td>
                        <td><x-scheme-badge :scheme="$app->scheme" /></td>
                        <td>{{ $app->company_name }}</td>
                        <td>
                            <span class="badge badge-{{ \App\Enums\ApplicationStatus::tryFrom($app->status)?->tone() ?? 'neutral' }}">
                                @statuslabel($app->status)
                            </span>
                        </td>
                        <td>{{ $app->updated_at->diffForHumans() }}</td>
                        <td>
                            <div class="flex gap-1">
                                @if ($app->canBeEditedByClient())
                                    <a class="btn btn-primary btn-sm" href="{{ route('client.applications.edit', $app) }}">Lanjutkan</a>
                                @endif
                                <a class="btn btn-light btn-sm" href="{{ route('client.applications.show', $app) }}">Detail</a>
                                @if ($app->canBeDeletedByClient())
                                    <form method="post" action="{{ route('client.applications.destroy', $app) }}"
                                          data-confirm="Draft ini beserta seluruh isian dan dokumen yang sudah diunggah akan dihapus permanen."
                                          data-confirm-title="Hapus Draft Permohonan"
                                          data-confirm-type="danger"
                                          data-confirm-yes="Ya, hapus">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-light btn-sm text-danger" type="submit">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">
                            @if (request()->hasAny(['q', 'scheme_id', 'status']) && array_filter(request()->only(['q', 'scheme_id', 'status'])))
                                Tidak ada permohonan yang sesuai dengan filter.
                            @else
                                Belum ada permohonan. Mulai dengan memilih skema sertifikasi.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
@endsection
