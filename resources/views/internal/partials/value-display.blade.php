{{--
    Menampilkan satu nilai isian klien pada ringkasan internal.

    Sejak Form Aplikasi ISPO memakai field bertipe tabel, nilainya bisa berupa
    larik bersarang — larik baris untuk 'repeatable' dan objek berkunci baris
    untuk 'table'. Keduanya tidak bisa diringkas dengan implode(); di sini
    keduanya dicetak sebagai tabel kecil agar peninjau tetap bisa memeriksa
    seluruh isian klien.

    Menerima: $field, $val
--}}
@php
    $columns = $field->column_definitions ?? [];
    $rowLabels = collect($field->row_definitions ?? [])->pluck('label', 'code');

    $columnLabel = function (string $code) use ($columns) {
        foreach ($columns as $column) {
            if (($column['code'] ?? null) === $code) {
                return $column['label'] ?? $code;
            }
        }

        return $code;
    };

    // Terjemahkan nilai select pada kolom tabel menjadi labelnya.
    $cellText = function (string $code, $value) use ($columns) {
        foreach ($columns as $column) {
            if (($column['code'] ?? null) !== $code) {
                continue;
            }
            foreach ($column['options'] ?? [] as $option) {
                if ((string) ($option['value'] ?? '') === (string) $value) {
                    return $option['label'];
                }
            }
        }

        return is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
    };

    $isNested = is_array($val) && collect($val)->contains(fn ($item) => is_array($item));
@endphp

@if ($isNested && $columns)
    @php
        // 'repeatable' berupa larik berurutan; 'table' berkunci kode baris.
        $rows = collect($val)
            ->map(fn ($row, $key) => ['key' => $key, 'cells' => (array) $row])
            ->filter(fn ($row) => collect($row['cells'])->contains(fn ($v) => filled($v)))
            ->values();
    @endphp

    @if ($rows->isEmpty())
        <span class="text-muted">Belum diisi</span>
    @else
        <div class="table-wrap">
            <table class="table table-sm" style="font-size:12px">
                <thead>
                    <tr>
                        <th>{{ $field->type === 'table' ? 'Baris' : 'No' }}</th>
                        @foreach ($columns as $column)
                            <th>{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        <tr>
                            <td>{{ $field->type === 'table' ? ($rowLabels[$row['key']] ?? $row['key']) : $i + 1 }}</td>
                            @foreach ($columns as $column)
                                <td>{{ filled($row['cells'][$column['code']] ?? null) ? $cellText($column['code'], $row['cells'][$column['code']]) : '-' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@elseif ($isNested)
    {{-- Larik bersarang tanpa definisi kolom (mis. tanda tangan pemohon). --}}
    <ul class="small" style="margin:0;padding-left:18px">
        @foreach ($val as $row)
            @if (is_array($row) && collect($row)->contains(fn ($v) => filled($v)))
                <li>
                    @foreach ($row as $key => $cell)
                        @if (filled($cell) && $key !== 'tanda_tangan')
                            <strong>{{ $columnLabel($key) }}:</strong> {{ is_array($cell) ? implode(', ', $cell) : $cell }}@if (! $loop->last) · @endif
                        @endif
                    @endforeach
                    @if (filled($row['tanda_tangan'] ?? null))
                        <span class="badge">tanda tangan terlampir</span>
                    @endif
                </li>
            @endif
        @endforeach
    </ul>
@elseif (is_array($val))
    {{ implode(', ', array_map('strval', $val)) }}
@else
    {{ $val }}
@endif
@if ($field->unit){{ $field->unit }}@endif
