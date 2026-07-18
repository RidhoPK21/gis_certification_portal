<div class="grid-2">
    <div class="form-group">
        <label class="form-label">Kode</label>
        <input class="form-control" name="code" value="{{ $document?->code }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">Nama Dokumen</label>
        <input class="form-control" name="name" value="{{ $document?->name }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">Kebutuhan</label>
        <select class="form-select" name="requirement">
            @foreach (['required', 'optional', 'conditional'] as $type)
                <option @selected($document?->requirement === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Grup Kajian</label>
        <select class="form-select" name="review_group">
            @foreach (['administration', 'technical'] as $type)
                <option @selected($document?->review_group === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Ekstensi</label>
        <input class="form-control" name="allowed_extensions_text" value="{{ implode(',', $document?->allowed_extensions ?? ['pdf']) }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">Maks. MB</label>
        <input class="form-control" type="number" name="max_size_mb" value="{{ $document?->max_size_mb ?? 20 }}" required>
    </div>
    <div class="form-group">
        <label class="form-label">Urutan</label>
        <input class="form-control" type="number" name="sort_order" value="{{ $document?->sort_order ?? 10 }}" required>
    </div>
</div>

<div class="form-group">
    <label class="form-label">Deskripsi</label>
    <textarea class="form-textarea" name="description">{{ $document?->description }}</textarea>
</div>

<div class="form-group">
    <label class="form-label">Conditional Rules JSON</label>
    <textarea class="form-textarea" name="conditional_rules_text">{{ $document?->conditional_rules ? json_encode($document->conditional_rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '' }}</textarea>
</div>
