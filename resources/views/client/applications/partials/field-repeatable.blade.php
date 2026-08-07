{{--
    Tabel yang barisnya ditambah sendiri pemohon (scheme_fields.type =
    'repeatable') — mis. G.1 Daftar Kebun/Anggota atau E. Daftar Lokasi.

    Baris disimpan sebagai larik berurutan [ { kodeKolom: isi }, ... ]. Indeks
    baris pada atribut name sengaja ditulis ulang setiap kali baris ditambah
    atau dihapus, supaya PHP tetap menerimanya sebagai larik berurutan dan
    tidak menyisakan lubang indeks setelah penghapusan.
--}}
@php
    $columns = $field->column_definitions ?? [];
    $rows = array_values(is_array($value) ? $value : []);
@endphp

<div class="repeatable-field" data-field-code="{{ $field->code }}">
    <div class="table-scroll">
        <table class="form-matrix repeatable-table">
            <thead>
                <tr>
                    <th scope="col" style="width:44px">No.</th>
                    @foreach ($columns as $column)
                        <th scope="col">{{ $column['label'] }}</th>
                    @endforeach
                    <th scope="col" style="width:64px"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="repeatable-body">
                @foreach ($rows as $index => $rowData)
                    <tr class="repeatable-row">
                        <td class="row-number">{{ $index + 1 }}</td>
                        @foreach ($columns as $column)
                            @php $cell = $rowData[$column['code']] ?? null; @endphp
                            <td>
                                @if (($column['type'] ?? 'text') === 'select')
                                    <select class="form-select" data-col="{{ $column['code'] }}"
                                            name="fields[{{ $field->code }}][{{ $index }}][{{ $column['code'] }}]">
                                        <option value="">Pilih...</option>
                                        @foreach ($column['options'] ?? [] as $option)
                                            <option value="{{ $option['value'] }}" @selected((string) $cell === (string) $option['value'])>{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input class="form-control" data-col="{{ $column['code'] }}"
                                           type="{{ in_array($column['type'] ?? 'text', ['number', 'date'], true) ? $column['type'] : 'text' }}"
                                           @if (($column['type'] ?? '') === 'number') step="any" @endif
                                           name="fields[{{ $field->code }}][{{ $index }}][{{ $column['code'] }}]"
                                           value="{{ is_array($cell) ? '' : $cell }}">
                                @endif
                            </td>
                        @endforeach
                        <td>
                            <button type="button" class="btn btn-sm btn-light repeatable-remove" title="Hapus baris">&times;</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <template class="repeatable-template">
        <tr class="repeatable-row">
            <td class="row-number"></td>
            @foreach ($columns as $column)
                <td>
                    @if (($column['type'] ?? 'text') === 'select')
                        <select class="form-select" data-col="{{ $column['code'] }}" name="">
                            <option value="">Pilih...</option>
                            @foreach ($column['options'] ?? [] as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    @else
                        <input class="form-control" data-col="{{ $column['code'] }}"
                               type="{{ in_array($column['type'] ?? 'text', ['number', 'date'], true) ? $column['type'] : 'text' }}"
                               @if (($column['type'] ?? '') === 'number') step="any" @endif name="" value="">
                    @endif
                </td>
            @endforeach
            <td>
                <button type="button" class="btn btn-sm btn-light repeatable-remove" title="Hapus baris">&times;</button>
            </td>
        </tr>
    </template>

    <button type="button" class="btn btn-sm btn-light repeatable-add mt-1">+ Tambah baris</button>
</div>
