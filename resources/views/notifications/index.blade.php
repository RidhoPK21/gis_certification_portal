@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
    <div class="page-head">
        <div>
            <h1>Notifikasi</h1>
            <p>Pesan proses dengan tautan menuju order atau item yang membutuhkan tindakan.</p>
        </div>
    </div>

    <section class="card">
        @forelse ($notifications as $notification)
            <form method="post" action="{{ route('notifications.read', $notification) }}" style="padding:16px 0;border-bottom:1px solid var(--line)">
                @csrf
                <button style="all:unset;display:block;width:100%;cursor:pointer">
                    <div class="flex justify-between gap-1">
                        <div>
                            <strong>{{ $notification->title }}</strong>
                            @unless ($notification->read_at)<span class="badge badge-info">Baru</span>@endunless
                            <p class="mb-0">{{ $notification->message }}</p>
                            <span class="small muted">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        <span>→</span>
                    </div>
                </button>
            </form>
        @empty
            <div class="empty">Tidak ada notifikasi.</div>
        @endforelse
    </section>

    {{ $notifications->links() }}
@endsection
