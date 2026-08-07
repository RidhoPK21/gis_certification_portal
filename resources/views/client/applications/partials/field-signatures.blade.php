{{--
    Blok tanda tangan pemohon pada bagian K FrO.7201.

    Formulir cetaknya menyediakan tiga baris Tempat dan Tanggal - Nama dan
    Jabatan - Tanda Tangan dan Stempel, jadi jumlah penanda tangan dibatasi
    tiga. Gambar tanda tangannya diunggah terpisah lewat rute unggah berkas
    field, lalu path-nya disimpan pada kolom tersembunyi supaya ikut tercetak
    pada PDF FrO.7201.
--}}
@php
    $maxSignatories = 3;
    $rows = array_values(is_array($value) ? $value : []);
    $rows = array_slice($rows, 0, $maxSignatories);
@endphp

<div class="signature-list" data-field-code="{{ $field->code }}" data-max="{{ $maxSignatories }}">
    <div class="signature-grid">
        @for ($i = 0; $i < $maxSignatories; $i++)
            @php
                $rowData = $rows[$i] ?? [];
                $signaturePath = $rowData['tanda_tangan'] ?? null;
                $prefix = 'fields[' . $field->code . '][' . $i . ']';
            @endphp
            <div class="signature-card {{ $i > 0 && empty($signaturePath) && empty($rowData['nama']) ? 'signature-optional' : '' }}">
                <div class="signature-card-title">Penanda tangan {{ $i + 1 }}@if ($i > 0) <span class="muted">(opsional)</span>@endif</div>

                <label class="form-label" for="sig-{{ $i }}-tempat">Tempat</label>
                <input class="form-control" id="sig-{{ $i }}-tempat" type="text"
                       name="{{ $prefix }}[tempat]" value="{{ $rowData['tempat'] ?? '' }}">

                <label class="form-label" for="sig-{{ $i }}-tanggal">Tanggal</label>
                <input class="form-control" id="sig-{{ $i }}-tanggal" type="date"
                       name="{{ $prefix }}[tanggal]" value="{{ $rowData['tanggal'] ?? '' }}">

                <label class="form-label" for="sig-{{ $i }}-nama">Nama</label>
                <input class="form-control" id="sig-{{ $i }}-nama" type="text"
                       name="{{ $prefix }}[nama]" value="{{ $rowData['nama'] ?? '' }}">

                <label class="form-label" for="sig-{{ $i }}-jabatan">Jabatan</label>
                <input class="form-control" id="sig-{{ $i }}-jabatan" type="text"
                       name="{{ $prefix }}[jabatan]" value="{{ $rowData['jabatan'] ?? '' }}">

                <label class="form-label" for="sig-{{ $i }}-file">Tanda Tangan dan Stempel</label>
                <div class="signature-drop" data-index="{{ $i }}">
                    <div class="signature-preview" id="sig-preview-{{ $i }}" style="{{ $signaturePath ? '' : 'display:none' }}">
                        @if ($signaturePath)
                            <img src="{{ route('secure-files.application-signature', ['application' => $application, 'index' => $i]) }}"
                                 alt="Tanda tangan penanda tangan {{ $i + 1 }}">
                        @endif
                    </div>
                    <input type="hidden" name="{{ $prefix }}[tanda_tangan]" value="{{ $signaturePath }}"
                           id="sig-path-{{ $i }}">
                    <input class="form-control signature-file-input" id="sig-{{ $i }}-file" type="file"
                           accept=".jpg,.jpeg,.png"
                           data-index="{{ $i }}"
                           data-field-code="{{ $field->code }}"
                           data-upload-url="{{ route('client.applications.upload-signature', $application) }}">
                    <small class="text-muted d-block mt-1">JPG atau PNG, maksimal 2 MB.</small>
                    <div class="small signature-status" id="sig-status-{{ $i }}" style="display:none"></div>
                </div>
            </div>
        @endfor
    </div>
</div>
