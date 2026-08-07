@php
    /**
     * Satu baris slot unggahan dokumen.
     *
     * $required  SchemeRequiredDocument dari snapshot form
     * $document  ApplicationDocument milik permohonan (boleh null)
     * $number    nomor urut di dalam grupnya
     * $locked    true bila slot dikunci (template Formulir Wajib GIS belum dibagikan)
     * $visible   false bila syarat dokumen belum terpenuhi oleh isian saat ini
     *
     * Dokumen bersyarat tetap dicetak ke HTML lalu disembunyikan, bukan dibuang
     * di server: syaratnya bergantung pada isian di langkah sebelumnya, dan
     * klien harus melihat slotnya muncul begitu isian itu diubah — tanpa perlu
     * memuat ulang halaman.
     */
    $locked = $locked ?? false;
    $visible = $visible ?? true;
@endphp

<div class="doc-item{{ $locked ? ' doc-item-locked' : '' }}{{ $required->conditional_rules ? ' conditional-document' : '' }}{{ $visible ? '' : ' hidden' }}"
     id="field-{{ $required->code }}"
     @if ($required->conditional_rules) data-condition='@json($required->conditional_rules)' @endif>
    <div>
        <h4>
            <span class="doc-number">{{ $number }}</span>. {{ $required->name }}
            @if ($locked)
                <span class="badge badge-neutral">Terkunci</span>
            @elseif ($required->requirement === 'required')
                <span class="required">*</span>
            @elseif ($required->requirement === 'conditional')
                <span class="badge badge-warning">Conditional</span>
            @endif
        </h4>
        <p>{{ $required->description }} · Format: {{ implode(', ', $required->allowed_extensions ?? config('gis.allowed_extensions')) }} · Maks. {{ $required->max_size_mb }} MB</p>
        <div class="small text-success doc-current" id="doc-current-{{ $required->code }}"
             style="{{ $document?->currentVersion ? '' : 'display:none' }}">
            @if ($document?->currentVersion)
                ✓ {{ $document->currentVersion->original_name }} · versi {{ $document->currentVersion->version }} · kajian: {{ $document->review_status }}
            @endif
        </div>
        <div class="small doc-status" id="doc-status-{{ $required->code }}" style="display:none;margin-top:4px"></div>
    </div>
    <div>
        @if ($locked)
            <span class="small muted">Menunggu template dibagikan</span>
        @else
            <input class="form-control doc-upload-input" type="file"
                   data-doc-code="{{ $required->code }}"
                   data-doc-name="{{ $required->name }}">
        @endif
    </div>
</div>
