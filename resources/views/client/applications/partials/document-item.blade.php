@php
    /**
     * Satu baris slot unggahan dokumen.
     *
     * $required  SchemeRequiredDocument dari snapshot form
     * $document  ApplicationDocument milik permohonan (boleh null)
     * $number    nomor urut di dalam grupnya
     * $locked    true bila slot dikunci (template Formulir Wajib GIS belum dibagikan)
     */
    $locked = $locked ?? false;
@endphp

<div class="doc-item{{ $locked ? ' doc-item-locked' : '' }}" id="field-{{ $required->code }}">
    <div>
        <h4>
            {{ $number }}. {{ $required->name }}
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
