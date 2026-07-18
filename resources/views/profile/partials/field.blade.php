@php
    $required = $required ?? false;
    $type = $type ?? 'text';
@endphp

<div class="form-group" style="margin-bottom: 16px;">
    <label
        for="{{ $name }}"
        style="display: block; margin-bottom: 6px; font-weight: 650; font-size: 13px; color: #152a3d;"
    >
        {{ $label }}@if ($required)<span style="color: #c0392b;"> *</span>@endif
    </label>

    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ $value }}"
        @if ($required) required @endif
        style="
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d6e2ec;
            border-radius: 10px;
            font-size: 14px;
        "
    >

    @error($name)
        <div style="margin-top: 6px; color: #c0392b; font-size: 12px;">
            {{ $message }}
        </div>
    @enderror
</div>
