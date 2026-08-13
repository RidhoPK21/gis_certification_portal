{{--
    Seluruh jawaban form yang diisi klien.

    Dipakai bersama oleh halaman review Admin dan halaman tinjauan Tim Teknis,
    sehingga keduanya melihat data yang persis sama. Wrapper <section> sengaja
    ikut di dalam partial: sejumlah test mengiris HTML berdasarkan id section
    ini, jadi id="data-form" harus tetap melekat pada blok ini di mana pun ia
    disisipkan.

    Variabel: $application (wajib).
--}}
<section class="card mt-2" id="data-form">
    <h2>Data Form Klien</h2>
    @foreach ($application->scheme->sections as $section)
        <h3>{{ $section->title }}</h3>
        <div class="table-wrap">
            <table class="table">
                <tbody>
                    @foreach ($section->fields as $field)
                        @php
                            $row = $application->values->firstWhere('field_code', $field->code);
                            $val = $row?->value_json ?? $row?->value_text;
                        @endphp
                        @if (filled($val))
                            <tr>
                                <th style="width:35%">{{ $field->label }}</th>
                                <td>
                                    @if ($field->type === 'file')
                                        @php
                                            $fileData = is_array($val) ? $val : (is_string($val) && str_starts_with($val, '{') ? json_decode($val, true) : null);
                                            $fileName = $fileData['original_name'] ?? (is_string($val) ? $val : null);
                                            $filePath = $fileData['path'] ?? null;
                                        @endphp
                                        @if ($filePath)
                                            <a class="btn btn-light btn-sm" href="{{ route('secure-files.application-field-file', ['application' => $application, 'code' => $field->code]) }}" target="_blank">
                                                ✓ {{ $fileName }}
                                            </a>
                                        @elseif ($fileName)
                                            <span>{{ $fileName }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    @else
                                        @include('internal.partials.value-display', ['field' => $field, 'val' => $val])
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</section>
