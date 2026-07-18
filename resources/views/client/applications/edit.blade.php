@extends('layouts.app')

@section('title', 'Isi Permohonan ' . $application->scheme->short_name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $application->scheme->short_name }}</h1>
            <p>{{ $application->order_number ?: 'Draft #' . $application->id }} · {{ $application->company_name }}</p>
        </div>
        <a class="btn btn-light" href="{{ route('client.applications.show', $application) }}">Lihat Ringkasan</a>
    </div>

    <div class="card mb-0">
        <div class="flex justify-between items-center gap-2 wrap">
            <div>
                <strong>Kelengkapan {{ $completion }}%</strong>
                <div class="small muted" id="autosave-status">Perubahan belum disimpan.</div>
            </div>
            <div style="width:min(420px,50%)" class="progress"><span style="width:{{ $completion }}%"></span></div>
        </div>
    </div>

    @if ($application->revisions->where('status', 'open')->count())
        <div class="alert alert-warning mt-2">
            <strong>Perbaikan diminta pada item berikut:</strong>
            <ul>
                @foreach ($application->revisions->where('status', 'open') as $revision)
                    <li>
                        <a href="#field-{{ $revision->target_code }}">{{ $revision->target_label }}</a>:
                        {{ $revision->revision_note }}
                        @if ($revision->due_date)(batas {{ $revision->due_date->format('d M Y') }})@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="wizard-layout mt-2">
        <aside class="card wizard-nav">
            <strong>Tahap Pengisian</strong>
            @foreach ($application->scheme->sections as $i => $section)
                <a href="#section-{{ $section->code }}"><span class="small">{{ $i + 1 }}.</span> {{ $section->title }}</a>
            @endforeach
            <a href="#documents">Dokumen Wajib</a>
            <a href="#submit">Pernyataan &amp; Submit</a>
        </aside>
        <div>
            <form id="application-form" method="post" action="{{ route('client.applications.update', $application) }}">
                @csrf
                @method('PUT')
                @foreach ($application->scheme->sections as $section)
                    <section class="card form-section mt-0" id="section-{{ $section->code }}" style="margin-bottom:16px;">
                        <h2>{{ $section->title }}</h2>
                        @if ($section->description)<p class="muted">{{ $section->description }}</p>@endif
                        <div class="field-grid">
                            @foreach ($section->fields->where('is_active', true) as $field)
                                @php($value = old('fields.' . $field->code, $values[$field->code] ?? null))
                                <div id="field-{{ $field->code }}"
                                     class="form-group {{ in_array($field->type, ['textarea', 'checkbox_group', 'repeatable'], true) ? 'field-full' : '' }} dynamic-field"
                                     data-condition='@json($field->conditional_rules)'>
                                    <label class="form-label" for="input-{{ $field->code }}">
                                        {{ $field->label }}
                                        @if ($field->is_required)<span class="required">*</span>@endif
                                        @if ($field->unit)<span class="muted">({{ $field->unit }})</span>@endif
                                    </label>
                                    @if ($field->type === 'textarea')
                                        <textarea class="form-textarea" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]" placeholder="{{ $field->placeholder }}">{{ $value }}</textarea>
                                    @elseif ($field->type === 'select')
                                        <select class="form-select" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]">
                                            <option value="">Pilih...</option>
                                            @foreach ($field->options as $option)
                                                <option value="{{ $option->value }}" @selected((string) $value === (string) $option->value)>{{ $option->label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($field->type === 'radio')
                                        <div class="flex gap-2 wrap">
                                            @foreach ($field->options as $option)
                                                <label><input type="radio" name="fields[{{ $field->code }}]" value="{{ $option->value }}" @checked((string) $value === (string) $option->value)> {{ $option->label }}</label>
                                            @endforeach
                                        </div>
                                    @elseif ($field->type === 'checkbox_group')
                                        <div class="grid-2">
                                            @foreach ($field->options as $option)
                                                <label><input type="checkbox" name="fields[{{ $field->code }}][]" value="{{ $option->value }}" @checked(in_array($option->value, (array) $value, true))> {{ $option->label }}</label>
                                            @endforeach
                                        </div>
                                    @elseif ($field->type === 'boolean')
                                        <select class="form-select" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]">
                                            <option value="">Pilih...</option>
                                            <option value="yes" @selected($value === 'yes')>Ya</option>
                                            <option value="no" @selected($value === 'no')>Tidak</option>
                                        </select>
                                    @else
                                        <input class="form-control" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]"
                                               type="{{ in_array($field->type, ['email', 'date', 'number', 'url'], true) ? $field->type : 'text' }}"
                                               value="{{ is_array($value) ? '' : $value }}" placeholder="{{ $field->placeholder }}"
                                               @if ($field->type === 'number') step="any" @endif>
                                    @endif
                                    @if ($field->help_text)<div class="form-help">{{ $field->help_text }}</div>@endif
                                    @error('fields.' . $field->code)<div class="error-text">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                        <div class="text-right"><button class="btn btn-light" type="submit">Simpan Draft</button></div>
                    </section>
                @endforeach
            </form>

            <section class="card form-section" id="documents" style="margin-bottom:16px;">
                <h2>Upload Dokumen</h2>
                <p class="muted">File lama tidak dihapus saat revisi. Sistem membuat versi baru dan menyimpan checksum SHA-256.</p>
                <div class="doc-list">
                    @foreach ($applicableDocuments as $required)
                        @php($doc = $application->documents->firstWhere('document_code', $required->code))
                        <div class="doc-item" id="field-{{ $required->code }}">
                            <div>
                                <h4>
                                    {{ $required->name }}
                                    @if ($required->requirement === 'required')<span class="required">*</span>
                                    @elseif ($required->requirement === 'conditional')<span class="badge badge-warning">Conditional</span>@endif
                                </h4>
                                <p>{{ $required->description }} · Format: {{ implode(', ', $required->allowed_extensions ?? config('gis.allowed_extensions')) }} · Maks. {{ $required->max_size_mb }} MB</p>
                                @if ($doc?->currentVersion)
                                    <div class="small text-success">✓ {{ $doc->currentVersion->original_name }} · versi {{ $doc->currentVersion->version }} · kajian: {{ $doc->review_status }}</div>
                                @endif
                            </div>
                            <form method="post" action="{{ route('client.documents.store', $application) }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="document_code" value="{{ $required->code }}">
                                <input class="form-control" type="file" name="file" required>
                                <button class="btn btn-light btn-sm mt-1">{{ $doc ? 'Unggah Versi Baru' : 'Unggah' }}</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card form-section" id="submit">
                <h2>Pernyataan &amp; Submit</h2>
                <div class="alert alert-info">Pastikan data dan dokumen benar. Setelah submit, form terkunci sampai Admin meminta revisi spesifik.</div>
                <form method="post" action="{{ route('client.applications.submit', $application) }}" onsubmit="return confirm('Kirim permohonan ke tim GIS sekarang?')">
                    @csrf
                    <label class="flex gap-1"><input type="checkbox" required> Saya menyatakan data yang disampaikan benar dan saya berwenang mengajukan permohonan ini.</label>
                    <button class="btn btn-primary mt-2">Submit Permohonan</button>
                </form>
            </section>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function(){const form=document.getElementById('application-form'),status=document.getElementById('autosave-status');if(!form)return;let timer;const values=()=>{const out={};new FormData(form).forEach((v,k)=>{const m=k.match(/^fields\[([^\]]+)\](?:\[\])?$/);if(!m)return;if(k.endsWith('[]')){out[m[1]]=out[m[1]]||[];out[m[1]].push(v)}else out[m[1]]=v});return out};const passes=(r,v)=>{if(!r||Object.keys(r).length===0)return true;if(r.all)return r.all.every(x=>passes(x,v));if(r.any)return r.any.some(x=>passes(x,v));const a=v[r.field],e=r.value;switch(r.operator||'equals'){case'equals':return String(a??'')===String(e??'');case'not_equals':return String(a??'')!==String(e??'');case'in':return (e||[]).includes(a);case'truthy':return !!a;case'falsy':return !a;case'contains':return Array.isArray(a)?a.includes(e):String(a||'').includes(String(e));default:return true}};const refresh=()=>{const v=values();document.querySelectorAll('.dynamic-field').forEach(el=>{let r={};try{r=JSON.parse(el.dataset.condition||'{}')}catch(e){}el.classList.toggle('hidden',!passes(r,v))})};const autosave=()=>{clearTimeout(timer);status.textContent='Perubahan belum disimpan...';timer=setTimeout(async()=>{status.textContent='Menyimpan draft...';const fd=new FormData(form);fd.set('_method','PUT');try{const res=await fetch(form.action,{method:'POST',body:fd,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});if(!res.ok)throw new Error();status.textContent='Draft tersimpan otomatis '+new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})}catch(e){status.textContent='Autosave gagal. Gunakan tombol Simpan Draft.'}},1300)};form.addEventListener('input',()=>{refresh();autosave()});form.addEventListener('change',()=>{refresh();autosave()});refresh()})();
</script>
@endpush
