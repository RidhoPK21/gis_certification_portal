<div class="grid-3">
    <div class="form-group">
        <label class="form-label">Section</label>
        <select class="form-select" name="scheme_section_id" {{ $field ? 'disabled' : '' }}>
            @foreach ($scheme->sections as $section)
                <option value="{{ $section->id }}" @selected(($field?->scheme_section_id) === $section->id)>
                    {{ $section->title }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Kode</label>
        <input class="form-control" name="code" value="{{ $field?->code }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">Tipe</label>
        <select class="form-select" name="type">
            @foreach (['text', 'textarea', 'number', 'email', 'date', 'url', 'select', 'radio', 'checkbox_group', 'boolean'] as $type)
                <option @selected($field?->type === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="grid-2">
    <div class="form-group">
        <label class="form-label">Label</label>
        <input class="form-control" name="label" value="{{ $field?->label }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">Placeholder</label>
        <input class="form-control" name="placeholder" value="{{ $field?->placeholder }}">
    </div>
    <div class="form-group">
        <label class="form-label">Help Text</label>
        <input class="form-control" name="help_text" value="{{ $field?->help_text }}">
    </div>
    <div class="form-group">
        <label class="form-label">Unit</label>
        <input class="form-control" name="unit" value="{{ $field?->unit }}">
    </div>
    <div class="form-group">
        <label class="form-label">Urutan</label>
        <input class="form-control" type="number" name="sort_order" value="{{ $field?->sort_order ?? 10 }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">Validasi (satu per baris/koma)</label>
        <input class="form-control" name="validation_rules_text" value="{{ implode(',', $field?->validation_rules ?? []) }}">
    </div>
</div>

<div class="grid-2">
    <div class="form-group">
        <label class="form-label">Opsi <span class="muted">value|Label</span></label>
        <textarea class="form-textarea" name="options_text">@if ($field)@foreach ($field->options as $option){{ $option->value }}|{{ $option->label }}
@endforeach
@endif</textarea>
    </div>
    <div class="form-group">
        <label class="form-label">Conditional Rules JSON</label>
        <textarea class="form-textarea" name="conditional_rules_text">{{ $field?->conditional_rules ? json_encode($field->conditional_rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '' }}</textarea>
    </div>
</div>

<label>
    <input type="checkbox" name="is_required" value="1" @checked($field?->is_required)>
    Field wajib saat submit
</label>
