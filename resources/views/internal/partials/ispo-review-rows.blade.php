{{--
    Tabel hasil kajian Tinjauan Permohonan ISPO (FrO.7204/GIS-3).

    Berbeda dari formulir LSSM yang memakai pilihan bercoret, formulir ISPO
    memakai kolom bercentang — satu kolom per pilihan — dengan Keterangan
    berupa teks bebas yang diketik peninjau.

    Menerima:
      $groups   array<string, array<int, row>>  baris dikelompokkan per bagian
      $saved    array<string, array{status, notes}>  hasil yang sudah tersimpan
      $offset   int  indeks awal items[] agar tidak bentrok antar tabel
      $docLinks Collection|null  dokumen unggahan klien untuk tautan berkas
--}}
@php $index = $offset ?? 0; @endphp

@foreach ($groups as $groupTitle => $rows)
    <h3 class="ispo-group-title">{{ $groupTitle }}</h3>
    <div class="table-wrap">
        <table class="table ispo-review-table">
            <thead>
                <tr>
                    <th style="width:38px">No</th>
                    <th>Dokumen/Data</th>
                    @foreach ($rows[0]['options'] as $label)
                        <th style="width:78px" class="text-center">{{ $label }}</th>
                    @endforeach
                    <th style="width:220px">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @php
                        $key = $row['type'] . '.' . $row['code'];
                        $item = $saved[$key] ?? null;
                        $status = old("items.$index.status", $item['status'] ?? null);
                    @endphp
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $row['label'] }}
                            <input type="hidden" name="items[{{ $index }}][type]" value="{{ $row['type'] }}">
                            <input type="hidden" name="items[{{ $index }}][code]" value="{{ $row['code'] }}">
                            <input type="hidden" name="items[{{ $index }}][label]" value="{{ $row['label'] }}">
                        </td>
                        @foreach ($row['options'] as $value => $label)
                            <td class="text-center">
                                <input type="radio" name="items[{{ $index }}][status]" value="{{ $value }}"
                                       @checked($status === $value)
                                       aria-label="{{ $label }} — {{ $row['label'] }}">
                            </td>
                        @endforeach
                        <td>
                            <input class="form-control" name="items[{{ $index }}][notes]"
                                   value="{{ old("items.$index.notes", $item['notes'] ?? '') }}"
                                   placeholder="Nomor dokumen, masa berlaku, kekurangan, atau tindak lanjut">
                        </td>
                    </tr>
                    @php $index++; @endphp
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach

@php $nextOffset = $index; @endphp
