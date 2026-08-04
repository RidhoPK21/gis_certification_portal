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
        <p class="muted small">Aspek berikut diisi oleh Tim Teknis dan akan tercetak pada PDF tinjauan permohonan bagian II.</p>
        <form method="post" action="{{ route('technical.reviews.save', $application) }}">
            @csrf

            {{-- Satu form menulis ke dua blok items[]: aspek (checklist) lalu dokumen.
                 Indeks harus memakai counter berjalan, bukan $loop->index, agar
                 kedua blok tidak saling menimpa. --}}
            @php($i = 0)

            @foreach ($technicalFields as $code => $meta)
                @php($val = $application->value($code))
                @php($existing = $itemsByCode->get($code))
                <input type="hidden" name="items[{{ $i }}][type]" value="checklist">
                <input type="hidden" name="items[{{ $i }}][code]" value="{{ $code }}">
                <input type="hidden" name="items[{{ $i }}][label]" value="{{ $meta['label'] }}">
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="aspek-{{ $code }}">{{ $meta['label'] }}</label>
                        @php($current = old('aspects.'.$code, is_array($val) ? implode(', ', $val) : $val))
                        @if ($meta['input'] === 'textarea')
                            <textarea class="form-textarea" id="aspek-{{ $code }}" name="aspects[{{ $code }}]" rows="2">{{ $current }}</textarea>
                        @elseif ($meta['input'] === 'number')
                            <input class="form-control" id="aspek-{{ $code }}" type="number" step="0.5" min="0" name="aspects[{{ $code }}]" value="{{ $current }}">
                        @else
                            <input class="form-control" id="aspek-{{ $code }}" type="text" name="aspects[{{ $code }}]" value="{{ $current }}">
                        @endif
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hasil kajian</label>
                        <select class="form-select" name="items[{{ $i }}][status]">
                            <option value="pending" @selected(($existing?->review_status ?? 'pending') === 'pending')>Belum dikaji</option>
                            <option value="sufficient" @selected($existing?->review_status === 'sufficient')>Cukup/Sesuai</option>
                            <option value="insufficient" @selected($existing?->review_status === 'insufficient')>Belum cukup/Tidak sesuai</option>
                        </select>
                        <input class="form-control mt-1" name="items[{{ $i }}][notes]" value="{{ $existing?->notes }}" placeholder="Keterangan">
                    </div>
                </div>
                @php($i++)
            @endforeach

            <h3 class="mt-3">Kajian Dokumen Teknis</h3>
            @if ($technicalDocuments->isEmpty())
                <div class="empty">Skema ini tidak memiliki dokumen teknis.</div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th>No</th><th>Dokumen</th><th>File</th><th>Hasil Kajian</th><th>Keterangan</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($technicalDocuments as $n => $required)
                                @php($doc = $application->documents->firstWhere('document_code', $required->code))
                                <tr>
                                    <td>{{ $n + 1 }}</td>
                                    <td>
                                        {{ $required->name }}
                                        <input type="hidden" name="items[{{ $i }}][type]" value="document">
                                        <input type="hidden" name="items[{{ $i }}][code]" value="{{ $required->code }}">
                                        <input type="hidden" name="items[{{ $i }}][label]" value="{{ $required->name }}">
                                        <input type="hidden" name="items[{{ $i }}][presence]" value="{{ $doc?->currentVersion ? 'Ada' : 'Tidak Ada' }}">
                                    </td>
                                    <td>
                                        @if ($doc?->currentVersion)
                                            <a class="btn btn-light btn-sm" href="{{ route('secure-files.application-document', $doc) }}">{{ $doc->currentVersion->original_name }}</a>
                                            <div class="small muted">v{{ $doc->currentVersion->version }}</div>
                                        @else
                                            <span class="text-danger">Tidak ada</span>
                                        @endif
                                    </td>
                                    <td>
                                        <select class="form-select" name="items[{{ $i }}][status]">
                                            <option value="pending" @selected(($doc?->review_status ?? 'pending') === 'pending')>Belum dikaji</option>
                                            <option value="sufficient" @selected($doc?->review_status === 'sufficient')>Cukup</option>
                                            <option value="insufficient" @selected($doc?->review_status === 'insufficient')>Belum cukup</option>
                                            <option value="meets" @selected($doc?->review_status === 'meets')>Memenuhi</option>
                                            <option value="not_meets" @selected($doc?->review_status === 'not_meets')>Tidak memenuhi</option>
                                        </select>
                                    </td>
                                    <td><input class="form-control" name="items[{{ $i }}][notes]" value="{{ $doc?->review_note }}"></td>
                                </tr>
                                @php($i++)
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="grid-3 mt-2">
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
