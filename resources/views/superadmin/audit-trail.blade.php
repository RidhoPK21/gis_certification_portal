@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')
    <div class="page-head">
        <div>
            <h1>Audit Trail Append-only</h1>
            <p>Perubahan historis tidak ditimpa; setiap event menyimpan aktor, waktu sistem, IP, dan metadata.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Waktu</th><th>User</th><th>Event</th><th>Subjek</th><th>IP</th><th>Perubahan</th></tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->occurred_at->format('d M Y H:i:s') }}</td>
                        <td>{{ $log->user?->name ?: 'System' }}</td>
                        <td><code>{{ $log->event }}</code></td>
                        <td>{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td>
                        <td>{{ $log->ip_address }}</td>
                        <td class="small">
                            <details>
                                <summary>Lihat metadata</summary>
                                <pre style="white-space:pre-wrap">{{ json_encode(['old' => $log->old_values, 'new' => $log->new_values, 'metadata' => $log->metadata], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Belum ada log.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
@endsection
