{{--
    Verifikasi Tim Teknis pada Fr.7201 (LSPro).

    Berbeda dari skema lain, Tim Teknis tidak menilai dokumen baris per baris:
    tugasnya memeriksa apakah kajian Admin sudah benar, lalu menerima seluruhnya
    atau mengembalikannya untuk diperbaiki. Karena itu tabel di bawah bersifat
    tampilan saja, dan kolom "Hasil Kajian Peninjau" pada PDF baru terisi setelah
    verifikasi ini disetujui.
--}}
@php
    $adminReview = $application->reviews->where('review_type', 'administration')->sortByDesc('id')->first();
    $adminItems = $adminReview?->items->keyBy('item_code') ?? collect();
    $verification = old('sni_verification', $review?->status === 'approved' ? 'accept' : ($review ? 'return' : ''));
@endphp

<section class="card" id="tinjauan-teknis">
    <h2>Verifikasi Tinjauan Permohonan — Fr.7201/GIS-4</h2>
    <p class="muted small">
        Kajian dokumen dikerjakan Admin Permohonan. Tugas Anda memeriksa apakah kajian itu sudah benar,
        lalu <strong>menerima</strong> atau <strong>mengembalikan ke Admin</strong> untuk diperbaiki.
        Tanda tangan Anda tercetak pada kotak <strong>Peninjau Dokumen Permohonan (Manajer Administrasi)</strong>.
    </p>

    @if (! $adminReview)
        <div class="alert alert-warning">
            Admin Permohonan belum menyimpan kajian dokumen, jadi belum ada yang bisa diverifikasi.
        </div>
    @else
        <h3 class="mt-2">Hasil Kajian Admin (Pengoleksi Dokumen)</h3>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px">No</th>
                        <th>Dokumen</th>
                        <th style="width:120px">File</th>
                        <th style="width:130px">Hasil Kajian</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($technicalDocuments as $i => $required)
                        @php
                            $doc = $application->documents->firstWhere('document_code', $required->code);
                            $item = $adminItems->get($required->code);
                            $status = $item?->review_status ?? 'pending';
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $required->name }}</td>
                            <td>
                                @if ($doc?->currentVersion)
                                    <a class="btn btn-light btn-sm" href="{{ route('secure-files.application-document', $doc) }}">Lihat</a>
                                @else
                                    <span class="text-danger small">Tidak ada</span>
                                @endif
                            </td>
                            <td>
                                @if ($status === 'pending')
                                    <span class="muted small">Belum dikaji</span>
                                @else
                                    {{ config('review.result_options')[$status] ?? $status }}
                                @endif
                            </td>
                            <td class="small">{{ $item?->notes ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <form method="post" action="{{ route('technical.reviews.save', $application) }}" class="mt-3">
        @csrf

        <div class="grid-3">
            <div class="form-group">
                <label class="form-label" for="sni-verifikasi">Hasil verifikasi</label>
                <select class="form-select" id="sni-verifikasi" name="sni_verification" required>
                    <option value="">— pilih —</option>
                    <option value="accept" @selected($verification === 'accept')>Terima — kajian Admin sudah benar</option>
                    <option value="return" @selected($verification === 'return')>Kembalikan ke Admin untuk diperbaiki</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="sni-tanggal">Tanggal verifikasi</label>
                <input class="form-control" type="date" id="sni-tanggal" name="action_date"
                       value="{{ old('action_date', optional($review?->action_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Peninjau Dokumen Permohonan</label>
                <input class="form-control" value="{{ auth()->user()->name }}" readonly>
                <p class="small muted" style="margin-top:6px">Diambil dari akun Anda dan tercetak pada kotak Manajer Administrasi.</p>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="sni-catatan">Catatan untuk Admin</label>
            <textarea class="form-textarea" id="sni-catatan" name="notes" rows="2"
                      placeholder="Wajib diisi bila dikembalikan: sebutkan bagian mana yang perlu diperbaiki.">{{ old('notes', $review?->notes) }}</textarea>
        </div>

        <button class="btn btn-primary" type="submit">Simpan Verifikasi</button>
    </form>
</section>
