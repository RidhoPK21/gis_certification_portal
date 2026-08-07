{{--
    Bagian 4 s.d. 8 FrO.7204 — tanggung jawab Tim Teknis, yang menandatangani
    sebagai Approval dan menetapkan keputusan akhir tinjauan.
--}}
@php
    $ispoData = $review?->ispo_data ?? [];
    $mandays = $ispoData['mandays'] ?? [];
@endphp

<section class="card mt-2" id="dokumen">
    <h2>Tinjauan Permohonan ISPO — Bagian 4 s.d. 8</h2>
    <p class="muted small">
        Mengikuti formulir <strong>{{ config('review.ispo.form.code') }}</strong>. Bagian 1 s.d. 3
        sudah diisi Admin Permohonan. Anda menandatangani sebagai <strong>Approval</strong>.
    </p>

    <form method="post" action="{{ route('technical.reviews.save', $application) }}">
        @csrf
        <input type="hidden" name="review_type" value="technical">

        @include('internal.partials.ispo-review-rows', ['groups' => $ispoGroups, 'saved' => $ispoSaved, 'offset' => 0])

        <h3 class="ispo-group-title">6. Penetapan Mandays Audit</h3>
        <div class="table-wrap">
            <table class="table ispo-review-table">
                <thead>
                    <tr>
                        <th>Sektor/Subruang Lingkup</th>
                        <th style="width:110px">Tahap 1 (HOK)</th>
                        <th style="width:110px">Tahap 2 (HOK)</th>
                        <th style="width:90px">Total (HOK)</th>
                        <th>Dasar/Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (config('review.ispo.mandays_sectors') as $sector)
                        @php
                            $row = $mandays[$sector['code']] ?? [];
                            $s1 = old("ispo.mandays.{$sector['code']}.stage_1", $row['stage_1'] ?? '');
                            $s2 = old("ispo.mandays.{$sector['code']}.stage_2", $row['stage_2'] ?? '');
                        @endphp
                        <tr>
                            <td>{{ $sector['label'] }}</td>
                            <td><input class="form-control mandays-input" type="number" step="any" min="0"
                                       name="ispo[mandays][{{ $sector['code'] }}][stage_1]" value="{{ $s1 }}"
                                       data-sector="{{ $sector['code'] }}"></td>
                            <td><input class="form-control mandays-input" type="number" step="any" min="0"
                                       name="ispo[mandays][{{ $sector['code'] }}][stage_2]" value="{{ $s2 }}"
                                       data-sector="{{ $sector['code'] }}"></td>
                            {{-- Total dihitung otomatis dan tidak dikirim: PDF menjumlahkannya
                                 ulang dari tahap 1 dan 2 agar tidak pernah berselisih. --}}
                            <td><output class="mandays-total" id="mandays-total-{{ $sector['code'] }}">
                                {{ (filled($s1) || filled($s2)) ? ((float) ($s1 ?: 0) + (float) ($s2 ?: 0)) : '—' }}
                            </output></td>
                            <td><input class="form-control" name="ispo[mandays][{{ $sector['code'] }}][note]"
                                       value="{{ old("ispo.mandays.{$sector['code']}.note", $row['note'] ?? '') }}"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <h3 class="ispo-group-title">7. Penugasan Tim Audit dan Panelis</h3>
        <p class="small muted">
            Tim auditor diambil dari penugasan auditor pada halaman permohonan, bukan diketik ulang.
        </p>
        <div class="form-group">
            <label class="form-label" for="ispo-panelists">Panelis/Reviewer/Pengambil Keputusan</label>
            <select class="form-select" id="ispo-panelists" name="panelist_ids[]" multiple size="5">
                @foreach ($panelistCandidates as $candidate)
                    <option value="{{ $candidate->id }}"
                        @selected(in_array($candidate->id, old('panelist_ids', $review?->panelist_ids ?? []), false))>
                        {{ $candidate->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <h3 class="ispo-group-title">8. Hasil Tinjauan Permohonan</h3>
        <div class="grid-3">
            <div class="form-group">
                <label class="form-label" for="ispo-decision">Hasil Tinjauan Permohonan</label>
                <select class="form-select" id="ispo-decision" name="ispo[decision]">
                    <option value="">— belum diputuskan —</option>
                    @foreach (config('review.ispo.decision_options') as $value => $label)
                        <option value="{{ $value }}" @selected(old('ispo.decision', $ispoData['decision'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="ispo-reverif">Hasil verifikasi ulang</label>
                <select class="form-select" id="ispo-reverif" name="ispo[reverification]">
                    <option value="">— belum diisi —</option>
                    @foreach (config('review.ispo.reverification_options') as $value => $label)
                        <option value="{{ $value }}" @selected(old('ispo.reverification', $ispoData['reverification'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="ispo-action-date">Tanggal Tinjauan</label>
                <input class="form-control" type="date" id="ispo-action-date" name="action_date"
                       value="{{ old('action_date', optional($review?->action_date)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
            </div>
        </div>

        <div class="grid-3">
            <div class="form-group">
                <label class="form-label" for="ispo-requested">Tanggal permintaan kelengkapan</label>
                <input class="form-control" type="date" id="ispo-requested" name="ispo[completion_requested_at]"
                       value="{{ old('ispo.completion_requested_at', $ispoData['completion_requested_at'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label" for="ispo-due">Batas waktu kelengkapan</label>
                <input class="form-control" type="date" id="ispo-due" name="ispo[completion_due_at]"
                       value="{{ old('ispo.completion_due_at', $ispoData['completion_due_at'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label" for="ispo-returned">Tanggal dokumen diterima kembali</label>
                <input class="form-control" type="date" id="ispo-returned" name="ispo[documents_returned_at]"
                       value="{{ old('ispo.documents_returned_at', $ispoData['documents_returned_at'] ?? '') }}">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="ispo-followup">Tindak lanjut/dokumen yang harus dilengkapi</label>
            <textarea class="form-textarea" id="ispo-followup" name="ispo[follow_up]" rows="2">{{ old('ispo.follow_up', $ispoData['follow_up'] ?? '') }}</textarea>
        </div>

        <div class="grid-2">
            <div class="form-group">
                <label class="form-label" for="ispo-tech-notes">Catatan Belum Memenuhi</label>
                <input class="form-control" id="ispo-tech-notes" name="notes" value="{{ old('notes', $review?->notes) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Approval</label>
                <input class="form-control" value="{{ auth()->user()->name }}" readonly>
                <p class="small muted" style="margin-top:6px">Diambil dari akun Anda dan tercetak pada kotak tanda tangan Approval.</p>
            </div>
        </div>

        <input type="hidden" name="signed_name" value="{{ auth()->user()->name }}">
        <button class="btn btn-primary mt-2" type="submit">Simpan Tinjauan Bagian 4–8</button>
    </form>
</section>

<script>
/* Total mandays dihitung di layar agar peninjau langsung melihat jumlahnya;
   nilainya sendiri tidak dikirim, PDF menjumlahkan ulang dari sumbernya. */
document.addEventListener('input', function (e) {
    const input = e.target.closest('.mandays-input');
    if (!input) return;
    const sector = input.dataset.sector;
    const inputs = document.querySelectorAll('.mandays-input[data-sector="' + sector + '"]');
    let total = 0;
    let filled = false;
    inputs.forEach(function (el) {
        if (el.value !== '') { filled = true; total += parseFloat(el.value) || 0; }
    });
    const out = document.getElementById('mandays-total-' + sector);
    if (out) out.textContent = filled ? total : '—';
});
</script>
