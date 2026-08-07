{{--
    Tabel dengan baris tetap (scheme_fields.type = 'table').

    Barisnya berasal dari row_definitions dan tidak bisa ditambah/dikurangi
    pemohon — mis. H.1 Legalitas dan Status Lahan yang barisnya sudah
    ditentukan formulir: APL, Hutan Konversi/HPK, HGU/HP, dan seterusnya.

    Nilainya disimpan sebagai objek bersarang { kodeBaris: { kodeKolom: isi } }
    sehingga langsung masuk ke application_values.value_json.
--}}
@php
    $columns = $field->column_definitions ?? [];
    $rows = $field->row_definitions ?? [];
    $data = is_array($value) ? $value : [];
@endphp

<div class="table-scroll">
    <table class="form-matrix">
        <thead>
            <tr>
                <th scope="col" class="matrix-row-label">{{ $field->label }}</th>
                @foreach ($columns as $column)
                    <th scope="col">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <th scope="row" class="matrix-row-label">{{ $row['label'] }}</th>
                    @foreach ($columns as $column)
                        @php
                            $cell = $data[$row['code']][$column['code']] ?? null;
                            $name = 'fields[' . $field->code . '][' . $row['code'] . '][' . $column['code'] . ']';
                        @endphp
                        <td>
                            @if (($column['type'] ?? 'text') === 'select')
                                <select class="form-select" name="{{ $name }}">
                                    <option value="">Pilih...</option>
                                    @foreach ($column['options'] ?? [] as $option)
                                        <option value="{{ $option['value'] }}" @selected((string) $cell === (string) $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input class="form-control"
                                       type="{{ in_array($column['type'] ?? 'text', ['number', 'date'], true) ? $column['type'] : 'text' }}"
                                       @if (($column['type'] ?? '') === 'number') step="any" @endif
                                       name="{{ $name }}" value="{{ is_array($cell) ? '' : $cell }}">
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
