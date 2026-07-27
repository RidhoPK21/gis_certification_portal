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

    <div class="table-wrap">
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
                        <td>
                            <strong>{{ $app->order_number ?: 'Draft #' . $app->id }}</strong>
                            <div class="small muted">{{ optional($app->order_date)->format('d M Y') }}</div>
                        </td>
                        <td>{{ $app->scheme->short_name }}</td>
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
                        <td colspan="6" class="empty">Belum ada permohonan. Mulai dengan memilih skema sertifikasi.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $applications->links() }}
@endsection
