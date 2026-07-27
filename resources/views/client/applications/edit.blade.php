@extends('layouts.app')

@section('title', 'Isi Permohonan ' . $application->scheme->short_name)

@section('content')
    <div class="page-head">
        <div>
            <h1>{{ $application->scheme->short_name }}</h1>
            <p>{{ $application->order_number ?: 'Draft #' . $application->id }} · {{ $application->company_name }}</p>
        </div>
        <div class="flex gap-1 wrap">
            <a class="btn btn-light" href="{{ route('client.applications.show', $application) }}">Lihat Ringkasan</a>
            @if ($application->canBeDeletedByClient())
                <form method="post" action="{{ route('client.applications.destroy', $application) }}"
                      data-confirm="Draft ini beserta seluruh isian dan dokumen yang sudah diunggah akan dihapus permanen."
                      data-confirm-title="Hapus Draft Permohonan"
                      data-confirm-type="danger"
                      data-confirm-yes="Ya, hapus">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-light text-danger" type="submit">Hapus Draft</button>
                </form>
            @endif
        </div>
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
                                    @if (($productGroups ?? null) && $field->code === 'product_name')
                                        <select class="form-select sni-product-group" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]">
                                            <option value="">Pilih produk...</option>
                                            @foreach ($productGroups as $group)
                                                <option value="{{ $group->name }}" @selected((string) $value === (string) $group->name)>{{ $group->name }}</option>
                                            @endforeach
                                        </select>
                                    @elseif (($productGroups ?? null) && $field->code === 'product_category')
                                        <select class="form-select sni-product-category" id="input-{{ $field->code }}" name="fields[{{ $field->code }}]" data-selected="{{ $value }}">
                                            <option value="">Pilih kategori...</option>
                                            @foreach ($productGroups as $group)
                                                @foreach ($group->categories as $cat)
                                                    <option value="{{ $cat->name }}" data-group="{{ $group->name }}" @selected((string) $value === (string) $cat->name)>{{ $cat->name }}</option>
                                                @endforeach
                                            @endforeach
                                        </select>
                                    @elseif ($field->type === 'textarea')
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

            <section class="card form-section" id="documents" style="margin-bottom:16px;"
                     data-upload-url="{{ route('client.documents.store', $application) }}">
                <h2>Upload Dokumen</h2>
                <p class="muted">Pilih file dan dokumen langsung terunggah tanpa perlu memuat ulang halaman. Anda bisa memilih ulang untuk mengganti versi. File lama tidak dihapus saat revisi; sistem membuat versi baru dan menyimpan checksum SHA-256.</p>
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
                                <div class="small text-success doc-current" id="doc-current-{{ $required->code }}"
                                     style="{{ $doc?->currentVersion ? '' : 'display:none' }}">
                                    @if ($doc?->currentVersion)
                                        ✓ {{ $doc->currentVersion->original_name }} · versi {{ $doc->currentVersion->version }} · kajian: {{ $doc->review_status }}
                                    @endif
                                </div>
                                <div class="small doc-status" id="doc-status-{{ $required->code }}" style="display:none;margin-top:4px"></div>
                            </div>
                            <div>
                                <input class="form-control doc-upload-input" type="file"
                                       data-doc-code="{{ $required->code }}"
                                       data-doc-name="{{ $required->name }}">
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card form-section" id="submit">
                <h2>Pernyataan &amp; Submit</h2>
                <div class="alert alert-info">Pastikan data dan dokumen benar. Setelah submit, form terkunci sampai Admin meminta revisi spesifik.</div>
                <form method="post" action="{{ route('client.applications.submit', $application) }}"
                      data-confirm="Setelah dikirim, form terkunci sampai Admin meminta revisi. Kirim permohonan ke tim GIS sekarang?"
                      data-confirm-title="Kirim Permohonan"
                      data-confirm-yes="Ya, kirim">
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
(function(){const form=document.getElementById('application-form'),status=document.getElementById('autosave-status');if(!form)return;let timer;const values=()=>{const out={};new FormData(form).forEach((v,k)=>{const m=k.match(/^fields\[([^\]]+)\](?:\[\])?$/);if(!m)return;if(k.endsWith('[]')){out[m[1]]=out[m[1]]||[];out[m[1]].push(v)}else out[m[1]]=v});return out};const passes=(r,v)=>{if(!r||Object.keys(r).length===0)return true;if(r.all)return r.all.every(x=>passes(x,v));if(r.any)return r.any.some(x=>passes(x,v));const a=v[r.field],e=r.value;switch(r.operator||'equals'){case'equals':return String(a??'')===String(e??'');case'not_equals':return String(a??'')!==String(e??'');case'in':return (e||[]).includes(a);case'truthy':return !!a;case'falsy':return !a;case'contains':return Array.isArray(a)?a.includes(e):String(a||'').includes(String(e));default:return true}};const refresh=()=>{const v=values();document.querySelectorAll('.dynamic-field').forEach(el=>{let r={};try{r=JSON.parse(el.dataset.condition||'{}')}catch(e){}el.classList.toggle('hidden',!passes(r,v))})};const doSave=async(manual)=>{status.textContent='Menyimpan draft...';const fd=new FormData(form);fd.set('_method','PUT');try{const res=await fetch(form.action,{method:'POST',body:fd,headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});if(!res.ok)throw new Error();const t=new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});status.textContent=(manual?'Draft tersimpan ':'Draft tersimpan otomatis ')+t;if(manual&&window.Swal)window.Swal.fire({toast:true,position:'top-end',icon:'success',title:'Draft tersimpan',showConfirmButton:false,timer:2200,timerProgressBar:true})}catch(e){status.textContent=manual?'Gagal menyimpan draft. Coba lagi.':'Autosave gagal. Gunakan tombol Simpan Draft.';if(manual&&window.Swal)window.Swal.fire({icon:'error',title:'Gagal menyimpan draft',confirmButtonColor:'#b42318'})}};const autosave=()=>{clearTimeout(timer);status.textContent='Perubahan belum disimpan...';timer=setTimeout(()=>doSave(false),1300)};form.addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);const b=e.submitter;if(b){const o=b.innerHTML;b.disabled=true;b.innerHTML='Menyimpan...';doSave(true).finally(()=>{b.disabled=false;b.innerHTML=o})}else{doSave(true)}});form.addEventListener('input',()=>{refresh();autosave()});form.addEventListener('change',()=>{refresh();autosave()});refresh()})();

/*
 * Live upload dokumen: pilih file langsung terunggah lewat AJAX,
 * tanpa reload halaman, dan input dikosongkan agar bisa memilih ulang.
 */
(function(){
    const section=document.getElementById('documents');
    if(!section)return;
    const url=section.dataset.uploadUrl;
    const token=document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    section.addEventListener('change',async function(event){
        const input=event.target;
        if(!input.classList.contains('doc-upload-input'))return;
        const file=input.files&&input.files[0];
        if(!file)return;

        const code=input.dataset.docCode;
        const name=input.dataset.docName;
        const statusEl=document.getElementById('doc-status-'+code);
        const currentEl=document.getElementById('doc-current-'+code);

        input.disabled=true;
        statusEl.style.display='block';
        statusEl.className='small doc-status';
        statusEl.style.color='var(--muted)';
        statusEl.textContent='Mengunggah '+file.name+'...';

        const fd=new FormData();
        fd.append('document_code',code);
        fd.append('file',file);

        try{
            const res=await fetch(url,{
                method:'POST',
                body:fd,
                headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':token}
            });
            const data=await res.json().catch(()=>({}));

            if(!res.ok){
                const msg=data.errors?.file?.[0]||data.message||'Gagal mengunggah dokumen.';
                statusEl.style.color='var(--danger,#b42318)';
                statusEl.textContent='✕ '+msg;
            }else{
                currentEl.style.display='block';
                currentEl.textContent='✓ '+data.original_name+' · versi '+data.version+' · kajian: '+data.review_status;
                statusEl.style.color='#17663a';
                statusEl.textContent='Berhasil diunggah '+new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'})+'. Anda bisa memilih file lagi untuk mengganti versi.';
            }
        }catch(e){
            statusEl.style.color='var(--danger,#b42318)';
            statusEl.textContent='✕ Koneksi gagal. Coba lagi.';
        }finally{
            input.disabled=false;
            input.value='';
        }
    });
})();

/*
 * Dropdown bertingkat khusus skema SNI: pilih Produk (grup) -> pilihan
 * Kategori otomatis terfilter sesuai grup terpilih.
 */
(function(){
    const groupSel=document.querySelector('.sni-product-group');
    const catSel=document.querySelector('.sni-product-category');
    if(!groupSel||!catSel)return;
    const options=Array.from(catSel.querySelectorAll('option[data-group]'));
    const preselect=catSel.getAttribute('data-selected')||'';

    function refilter(keepValue){
        const group=groupSel.value;
        const current=keepValue?catSel.value:'';
        let hasCurrent=false;
        options.forEach(opt=>{
            const match=opt.getAttribute('data-group')===group;
            opt.hidden=!match;
            opt.disabled=!match;
            if(match&&opt.value===current)hasCurrent=true;
        });
        if(!hasCurrent)catSel.value='';
    }

    // Pemuatan awal: jika ada nilai kategori tersimpan, pertahankan.
    if(preselect){catSel.value=preselect;refilter(true);}
    else refilter(false);

    groupSel.addEventListener('change',()=>refilter(false));
})();
</script>
@endpush
