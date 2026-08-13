{{--
    Seluruh dokumen yang diunggah klien, baca-saja.

    Sengaja tanpa input tersembunyi ber-value kode dokumen: penilaian per
    dokumen punya formulirnya sendiri, dan sejumlah test memastikan dokumen
    administrasi tidak bocor ke formulir penilaian teknis dengan mencari
    value="{kode}". Daftar ini hanya tautan berkas.

    Variabel: $application (wajib), $title (opsional)
--}}
<section class="card mt-2" id="dokumen-klien">
    <h2>{{ $title ?? 'Dokumen Unggahan Klien' }}</h2>
    <p class="muted small">Seluruh berkas yang dikirim klien untuk order ini, termasuk yang dikaji Admin Permohonan.</p>
    @php($uploaded = $application->documents->filter(fn ($doc) => $doc->currentVersion !== null))
    @if ($uploaded->isEmpty())
        <div class="empty">Klien belum mengunggah dokumen apa pun.</div>
    @else
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>No</th><th>Dokumen</th><th>Berkas</th><th>Versi</th><th>Diunggah</th></tr>
                </thead>
                <tbody>
                    @foreach ($uploaded as $n => $doc)
                        <tr>
                            <td>{{ $n + 1 }}</td>
                            <td>{{ $doc->document_name ?: $doc->document_code }}</td>
                            <td>
                                <a class="btn btn-light btn-sm" href="{{ route('secure-files.application-document', $doc) }}">
                                    {{ $doc->currentVersion->original_name }}
                                </a>
                            </td>
                            <td>v{{ $doc->currentVersion->version }}</td>
                            <td class="small muted">{{ optional($doc->currentVersion->created_at)->format('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
