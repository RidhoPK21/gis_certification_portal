{{--
    Permintaan revisi spesifik ke klien beserta riwayatnya.

    Dipakai Admin Permohonan (dari status admin_review) dan Tim Teknis (dari
    status technical_review), jadi kedua route-nya diberikan lewat variabel.

    Variabel:
      $application  (wajib)
      $action       (wajib) URL tujuan form permintaan revisi
      $resolveRoute (wajib) nama route untuk menandai satu item revisi selesai
      $sectionId    (opsional, bawaan "revisi") id section — test mengiris HTML
                    berdasarkan id ini, jadi tiap halaman memakai id berbeda
      $intro        (opsional) kalimat tambahan di bawah judul
--}}
@php($sectionId = $sectionId ?? 'revisi')
<section class="card mt-2" id="{{ $sectionId }}">
    <h2>Revisi Spesifik</h2>
    <p class="muted">Pilih hanya field/dokumen yang benar-benar perlu diperbaiki. Klien diarahkan langsung ke item tersebut.</p>
    @isset($intro)
        <div class="alert alert-info small">{{ $intro }}</div>
    @endisset
    <form method="post" action="{{ $action }}" data-revision-form>
        @csrf
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Pilih</th><th>Item</th><th>Catatan Revisi</th></tr>
                </thead>
                <tbody>
                    @php($idx = 0)
                    @foreach ($application->scheme->sections as $section)
                        @foreach ($section->fields as $field)
                            <tr>
                                <td><input type="checkbox" class="revision-check" data-index="{{ $idx }}"></td>
                                <td>
                                    <span class="badge badge-neutral">Field</span> {{ $field->label }}
                                    <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][type]" value="field">
                                    <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][code]" value="{{ $field->code }}">
                                    <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][label]" value="{{ $field->label }}">
                                </td>
                                <td><input disabled class="form-control revision-input-{{ $idx }}" name="targets[{{ $idx }}][note]" placeholder="Jelaskan perbaikan yang dibutuhkan"></td>
                            </tr>
                            @php($idx++)
                        @endforeach
                    @endforeach
                    @foreach ($application->scheme->requiredDocuments as $required)
                        <tr>
                            <td><input type="checkbox" class="revision-check" data-index="{{ $idx }}"></td>
                            <td>
                                <span class="badge badge-warning">Dokumen</span> {{ $required->name }}
                                <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][type]" value="document">
                                <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][code]" value="{{ $required->code }}">
                                <input disabled class="revision-input-{{ $idx }}" type="hidden" name="targets[{{ $idx }}][label]" value="{{ $required->name }}">
                            </td>
                            <td><input disabled class="form-control revision-input-{{ $idx }}" name="targets[{{ $idx }}][note]" placeholder="Jelaskan dokumen yang harus diperbaiki"></td>
                        </tr>
                        @php($idx++)
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="grid-2 mt-2">
            <div class="form-group">
                <label class="form-label">Batas Perbaikan</label>
                <input class="form-control" type="date" name="due_date">
            </div>
            <div style="align-self:end">
                <button class="btn btn-warning">Kirim Permintaan Revisi</button>
            </div>
        </div>
    </form>
    @if ($application->revisions->count())
        <h3>Riwayat Revisi</h3>
        @foreach ($application->revisions->groupBy('revision_round') as $round => $items)
            <div class="alert alert-info">
                <strong>Putaran {{ $round }}</strong>
                <div class="table-wrap mt-1">
                    <table class="table">
                        <thead>
                            <tr><th>Item</th><th>Catatan</th><th>Status</th><th>Tindakan Admin</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                <tr>
                                    <td>{{ $item->target_label }}</td>
                                    <td>{{ $item->revision_note }}</td>
                                    <td><span class="badge badge-{{ $item->status === 'resolved' ? 'success' : 'warning' }}">{{ $item->status }}</span></td>
                                    <td>
                                        @if ($item->status !== 'resolved')
                                            <form method="post" action="{{ route($resolveRoute, [$application, $item]) }}">
                                                @csrf
                                                <div class="flex gap-1 wrap">
                                                    <input class="form-control" style="min-width:220px" name="resolution_note" placeholder="Hasil verifikasi perbaikan" required>
                                                    <button class="btn btn-success btn-sm">Tandai Selesai</button>
                                                </div>
                                            </form>
                                        @else
                                            <span class="small muted">Selesai {{ optional($item->resolved_at)->format('d M Y H:i') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</section>

@push('scripts')
<script>
/* Baris revisi baru ikut terkirim hanya saat kotaknya dicentang. Selektor
   memakai atribut, bukan id, agar partial ini aman dipakai di halaman mana pun. */
document.querySelectorAll('.revision-check').forEach(c=>c.addEventListener('change',()=>{document.querySelectorAll('.revision-input-'+c.dataset.index).forEach(i=>{i.disabled=!c.checked;i.required=c.checked&&i.name.endsWith('[note]')})}));
document.querySelectorAll('form[data-revision-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
        if (this.querySelectorAll('.revision-check:checked').length === 0) {
            e.preventDefault();
            if (typeof window.Swal !== 'undefined') {
                window.Swal.fire({
                    icon: 'warning',
                    title: 'Perhatian',
                    text: 'Pilih minimal 1 item (field atau dokumen) yang harus direvisi oleh klien dengan mencentang kotak di sebelah kiri.',
                    confirmButtonText: 'Mengerti',
                    confirmButtonColor: '#b42318'
                });
            } else if (typeof window.flashError === 'function' || typeof flashError === 'function') {
                (window.flashError || flashError)('Pilih minimal 1 item (field atau dokumen) yang harus direvisi oleh klien dengan mencentang kotak di sebelah kiri.');
            } else {
                alert('Pilih minimal 1 item (field atau dokumen) yang harus direvisi.');
            }
        }
    });
});
</script>
@endpush
