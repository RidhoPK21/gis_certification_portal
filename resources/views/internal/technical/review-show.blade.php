@extends('layouts.app')

@section('title', 'Tinjauan Teknis — ' . $application->order_number)

@section('content')
    <div class="page-head">
        <div>
            <h1>Tinjauan Teknis</h1>
            <p>{{ $application->order_number }} · {{ $application->company_name }} · <x-scheme-badge :scheme="$application->scheme" /></p>
        </div>
        <div>
            <a class="btn btn-light" href="{{ route('technical.reviews.index') }}">Kembali</a>
        </div>
    </div>

    @unless (auth()->user()->hasSignature())
        <div class="alert alert-warning">
            Anda belum mengunggah tanda tangan elektronik. Slot tanda tangan teknis pada PDF akan kosong.
            <a href="{{ route('profile.edit') }}">Unggah tanda tangan di Profil</a> terlebih dahulu.
        </div>
    @endunless

    @php($itemsByCode = $review ? $review->items->keyBy('item_code') : collect())

    <section class="card" id="tinjauan-teknis">
        <h2>Penilaian Aspek Teknis</h2>
        <form method="post" action="{{ route('technical.reviews.save', $application) }}">
            @csrf
            @foreach ($technicalFields as $code => $label)
                @php($val = $application->value($code))
                @php($existing = $itemsByCode->get($code))
                <input type="hidden" name="items[{{ $loop->index }}][type]" value="checklist">
                <input type="hidden" name="items[{{ $loop->index }}][code]" value="{{ $code }}">
                <input type="hidden" name="items[{{ $loop->index }}][label]" value="{{ $label }}">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">{{ $label }}</label>
                        <input class="form-control" value="{{ is_array($val) ? implode(', ', $val) : $val }}" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hasil kajian</label>
                        <select class="form-select" name="items[{{ $loop->index }}][status]">
                            <option value="pending" @selected(($existing?->review_status ?? 'pending') === 'pending')>Belum dikaji</option>
                            <option value="sufficient" @selected($existing?->review_status === 'sufficient')>Cukup/Sesuai</option>
                            <option value="insufficient" @selected($existing?->review_status === 'insufficient')>Belum cukup/Tidak sesuai</option>
                        </select>
                        <input class="form-control mt-1" name="items[{{ $loop->index }}][notes]" value="{{ $existing?->notes }}" placeholder="Keterangan">
                    </div>
                </div>
            @endforeach
            <div class="grid-3">
                <div class="form-group">
                    <label class="form-label">Tanggal</label>
                    <input class="form-control" type="date" name="action_date" value="{{ optional($review?->action_date)->format('Y-m-d') ?? now()->format('Y-m-d') }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Peninjau Teknis</label>
                    <input class="form-control" name="signed_name" value="{{ $review?->signed_name ?? auth()->user()->name }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <input class="form-control" name="notes" value="{{ $review?->notes }}">
                </div>
            </div>
            <button class="btn btn-primary">Simpan Tinjauan Teknis</button>
        </form>

        <hr style="border:0;border-top:1px solid var(--line);margin:24px 0">

        <form method="post" action="{{ route('technical.reviews.complete', $application) }}"
              data-confirm="Kirim hasil tinjauan teknis ke Admin untuk keputusan akhir? Pastikan penilaian sudah disimpan."
              data-confirm-title="Selesai & Kirim ke Admin" data-confirm-yes="Ya, kirim">
            @csrf
            <button class="btn btn-success" @disabled(! $review)>Selesai &amp; Kirim ke Admin</button>
            @unless ($review)
                <div class="small muted mt-1">Simpan tinjauan teknis lebih dahulu sebelum mengirim ke Admin.</div>
            @endunless
        </form>
    </section>
@endsection
