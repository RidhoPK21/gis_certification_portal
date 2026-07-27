@php
    $required = $required ?? false;
    $type = $type ?? 'text';
@endphp

<div class="form-group" style="margin-bottom: 16px;">
    <label
        for="{{ $name }}"
        style="display: block; margin-bottom: 6px; font-weight: 650; font-size: 13px; color: var(--text);"
    >
        {{ $label }}@if ($required)<span class="required"> *</span>@endif
    </label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        @if ($required) required @endif
        class="form-control"
        style="font-size: 14px;"
    >

    @error($name)
        <div class="error-text">
            {{ $message }}
        </div>
    @enderror
</div>
