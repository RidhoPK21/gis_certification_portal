@extends('layouts.app')

@section('title', 'Tindakan Koreksi')

@section('content')
    <div class="page-head">
        <div>
            <h1>Tindakan Koreksi</h1>
            <p>Jawab setiap temuan secara terpisah. Bukti perbaikan dapat terdiri dari beberapa file.</p>
        </div>
    </div>

    @forelse ($findings as $finding)
        <section class="card mb-0" style="margin-bottom:18px">
            <div class="flex justify-between gap-1 wrap">
                <div>
                    <span class="badge badge-warning">{{ strtoupper($finding->finding_type) }}</span>
                    <h2>{{ $finding->finding_number }}</h2>
                    <p>{{ $finding->description }}</p>
                    <div class="small muted">Order {{ $finding->application->order_number }} · Tenggat {{ optional($finding->due_date)->format('d M Y') ?: '-' }}</div>
                </div>
                <span class="badge badge-{{ $finding->status === 'closed' ? 'success' : 'warning' }}">{{ $finding->status }}</span>
            </div>

            @if ($finding->correctiveActions->count())
                <h3>Riwayat jawaban</h3>
                @foreach ($finding->correctiveActions as $ca)
                    <div class="alert {{ $ca->status === 'accepted' ? 'alert-success' : 'alert-info' }}">
                        <strong>Revisi {{ $ca->revision }} · {{ $ca->status }}</strong>
                        <div class="small">Dikirim {{ optional($ca->submitted_at)->format('d M Y H:i') }}</div>
                        @foreach ($ca->reviews as $review)
                            <p><b>Review auditor:</b> {{ $review->status }} - {{ $review->notes }}</p>
                        @endforeach
                    </div>
                @endforeach
            @endif

            @if ($finding->status !== 'closed')
                <details class="mt-2" open>
                    <summary><strong>Kirim tindakan koreksi</strong></summary>
                    <form method="post" action="{{ route('client.corrective-actions.store', $finding) }}" enctype="multipart/form-data" class="mt-2">
                        @csrf
                        <div class="form-group">
                            <label class="form-label">Analisis akar penyebab <span class="required">*</span></label>
                            <textarea class="form-textarea" name="root_cause" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Koreksi langsung <span class="required">*</span></label>
                            <textarea class="form-textarea" name="correction" required></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tindakan korektif untuk mencegah terulang <span class="required">*</span></label>
                            <textarea class="form-textarea" name="corrective_action" required></textarea>
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Tanggal implementasi</label>
                                <input class="form-control" type="date" name="implementation_date">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Bukti perbaikan</label>
                                <input class="form-control" type="file" name="evidence[]" multiple>
                            </div>
                        </div>
                        <button class="btn btn-primary">Kirim ke Auditor</button>
                    </form>
                </details>
            @endif
        </section>
    @empty
        <div class="card empty">Belum ada temuan yang memerlukan tindakan koreksi.</div>
    @endforelse
@endsection
