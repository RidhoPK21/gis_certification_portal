@extends('layouts.app')

@section('title', 'Permintaan Formulir GIS')

@section('content')
    <div class="page-head">
        <div>
            <h1>Permintaan Formulir Wajib GIS</h1>
            <p>Klien meminta template formulir LS. Setelah disetujui, klien dapat mengunduh template dan mengunggah formulir yang sudah diisi.</p>
        </div>
        @if (auth()->user()->hasRole('superadmin'))
            <a class="btn btn-light" href="{{ route('superadmin.gis-forms.index') }}">Kelola Template</a>
        @endif
    </div>

    <section class="card">
        <div class="flex gap-1 wrap">
            @foreach (['pending' => 'Menunggu', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'] as $key => $label)
                <a class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-light' }}"
                   href="{{ route('internal.gis-form-requests.index', ['status' => $key]) }}">
                    {{ $label }}
                    @if ($key === 'pending' && $pendingCount > 0)
                        ({{ $pendingCount }})
                    @endif
                </a>
            @endforeach
        </div>
    </section>

    <section class="card mt-2">
        <h2>Daftar Permintaan</h2>
        <div class="table-wrap mt-2">
            <table class="table">
                <thead>
                    <tr>
                        <th>Permohonan</th>
                        <th>Skema</th>
                        <th>Diminta</th>
                        <th>Catatan Klien</th>
                        <th>Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->application->order_number ?: 'Draft #' . $item->application->id }}</strong>
                                <br><span class="small muted">{{ $item->application->company_name }}</span>
                            </td>
                            <td><span class="small">{{ $item->application->scheme->short_name }}</span></td>
                            <td class="small">
                                {{ $item->created_at->format('d M Y H:i') }}
                                <br><span class="muted">{{ $item->requester?->name ?? '-' }}</span>
                            </td>
                            <td class="small">{{ $item->client_note ?: '-' }}</td>
                            <td>
                                @if ($item->isPending())
                                    <form method="post" action="{{ route('internal.gis-form-requests.approve', $item) }}"
                                          data-confirm="Template Formulir Wajib GIS akan dibagikan ke klien dan slot unggahannya terbuka. Setujui?"
                                          data-confirm-title="Setujui Permintaan"
                                          data-confirm-yes="Ya, setujui">
                                        @csrf
                                        <input class="form-control" name="response_note" placeholder="Catatan (opsional)" style="margin-bottom:8px">
                                        <button class="btn btn-primary btn-sm">Setujui &amp; Bagikan</button>
                                    </form>
                                    <form method="post" action="{{ route('internal.gis-form-requests.reject', $item) }}" style="margin-top:10px">
                                        @csrf
                                        <input class="form-control" name="response_note" placeholder="Alasan penolakan (wajib)" required style="margin-bottom:8px">
                                        <button class="btn btn-danger btn-sm">Tolak</button>
                                    </form>
                                @else
                                    <span class="badge badge-{{ $item->isApproved() ? 'success' : 'danger' }}">{{ $item->statusLabel() }}</span>
                                    <div class="small muted" style="margin-top:6px">
                                        {{ optional($item->responded_at)->format('d M Y H:i') }}
                                        oleh {{ $item->responder?->name ?? '-' }}
                                    </div>
                                    @if ($item->response_note)
                                        <div class="small" style="margin-top:4px">{{ $item->response_note }}</div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty">Tidak ada permintaan pada status ini.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-2">{{ $requests->links() }}</div>
    </section>
@endsection
