@extends('layouts.admin')

@section('title', $school->name . ' — Card Template')
@section('heading', 'Card Template Editor')
@section('subheading', $school->name)

@section('content')
@if(session('success'))
    <div class="mb-4 bg-green-500/10 border border-green-500/30 text-green-400 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="mb-4 bg-red-500/10 border border-red-500/30 text-red-400 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
@endif

<div class="mb-4 flex items-center gap-3">
    <a href="{{ route('admin.schools.show', $school) }}" class="text-sm text-slate-400 hover:text-slate-200">&larr; Back to School</a>
    @if($template)
        <span class="text-xs text-slate-600">|</span>
        <span class="text-xs text-slate-500">v{{ $template->version }} &middot; {{ $template->fields->count() }} field(s)</span>
    @endif
</div>

{{-- Upload bar --}}
<div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4 mb-5 flex flex-wrap items-center gap-4">
    <div class="flex items-center gap-3">
        <label class="text-xs text-slate-400 uppercase tracking-wide">Front Image</label>
        <input type="file" id="uploadFront" accept=".png,.jpg,.jpeg" class="hidden">
        <button type="button" id="btnUploadFront"
                class="border border-slate-600 hover:border-primary-500 text-slate-300 text-sm px-3 py-1.5 rounded-lg transition">
            Upload Front PNG/JPG
        </button>
        <span id="frontStatus" class="text-xs text-slate-500"></span>
    </div>
    <div class="flex items-center gap-3">
        <label class="text-xs text-slate-400 uppercase tracking-wide">Back Image</label>
        <input type="file" id="uploadBack" accept=".png,.jpg,.jpeg" class="hidden">
        <button type="button" id="btnUploadBack"
                class="border border-slate-600 hover:border-primary-500 text-slate-300 text-sm px-3 py-1.5 rounded-lg transition">
            Upload Back PNG/JPG
        </button>
        <span id="backStatus" class="text-xs text-slate-500"></span>
    </div>
    <div class="flex-1 text-right">
        <span class="text-xs text-slate-600">1012 &times; 638 px recommended (CR80 @300 DPI)</span>
    </div>
</div>

{{-- Two-panel editor --}}
<div class="flex gap-5">

    {{-- Left: live preview (60%) --}}
    <div class="flex-[3] min-w-0">
        <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4">
            <p class="text-xs text-slate-500 uppercase tracking-wide mb-3">Live Preview — Front</p>

            {{-- Outer scaler: the preview box is 506 × 319 (half of CR80 canvas) --}}
            <div class="overflow-hidden rounded-lg" style="width:506px;height:319px;background:#111;">
                <div id="card-preview" style="position:relative;width:1012px;height:638px;transform:scale(0.5);transform-origin:top left;overflow:hidden;">
                    <img id="previewBg"
                         src="{{ $template ? route('admin.card-template.image', [$school, 'front']) : '' }}"
                         alt="" style="position:absolute;top:0;left:0;width:1012px;height:638px;object-fit:cover;{{ $template ? '' : 'display:none;' }}">
                    {{-- Field overlays injected by JS --}}
                </div>
            </div>
        </div>
    </div>

    {{-- Right: field config panel (40%) --}}
    <div class="flex-[2] min-w-0">
        <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-4">

            {{-- Add field --}}
            <div class="flex items-center gap-2">
                <select id="addFieldKey"
                        class="flex-1 bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-primary-500">
                    <option value="">— select field to add —</option>
                    @foreach($fieldLabels as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button type="button" id="btnAddField"
                        class="bg-primary-600 hover:bg-primary-700 text-white text-sm px-4 py-2 rounded-lg whitespace-nowrap transition">
                    + Add
                </button>
            </div>

            {{-- Field list --}}
            <div id="fieldList" class="flex flex-col gap-3 max-h-[480px] overflow-y-auto pr-1">
                {{-- Populated by JS --}}
            </div>

            {{-- Save form --}}
            <form id="saveForm" method="POST" action="{{ route('admin.card-template.save', $school) }}" class="mt-2 pt-3 border-t border-slate-700/50 flex items-center gap-3">
                @csrf
                <input type="hidden" name="fields" id="fieldsJson" value="[]">
                <input type="text" name="name" placeholder="Template name (optional)"
                       value="{{ $template?->name }}"
                       class="flex-1 bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary-500">
                <button type="submit" id="btnSave"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm px-5 py-2 rounded-lg font-medium transition">
                    Save
                </button>
            </form>

        </div>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
// ── State ─────────────────────────────────────────────────────────────────
const CANVAS_W = 1012, CANVAS_H = 638;
const FIELD_LABELS = @json($fieldLabels);
let fields = [];  // [{id, field_key, x, y, w, h, font_family, font_size, font_color, text_align, font_weight, custom_value}]

// Seed from saved template if one exists.
const savedFields = @json($savedFields ?? []);

let nextId = 0;
savedFields.forEach(f => addFieldObject({...f, id: nextId++}));

// ── Add field ─────────────────────────────────────────────────────────────
function addField() {
    const sel = document.getElementById('addFieldKey');
    const key = sel.value;
    if (!key) return;
    const isImage = key === 'photo' || key === 'qr_code';
    addFieldObject({
        id: nextId++, field_key: key,
        x: 50, y: 50, w: isImage ? 100 : 300, h: isImage ? 100 : 30,
        font_family: 'Inter', font_size: 14, font_color: '#000000',
        text_align: 'left', font_weight: 'normal', custom_value: '',
    });
    sel.value = '';
}

function addFieldObject(f) {
    fields.push(f);
    renderFieldCard(f);
    renderOverlay(f);
}

// ── Render one field config card (no inline handlers — CSP blocks them) ───
function mk(tag, cls, attrs = {}) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    Object.entries(attrs).forEach(([k, v]) => { el[k] = v; });
    return el;
}
function mkLabel(text) {
    const l = mk('label', 'text-slate-500'); l.textContent = text; return l;
}
function mkInput(type, value, cls) {
    const i = mk('input', cls ?? 'w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500');
    i.type = type; i.value = value; return i;
}
function mkSelect(options, selected, cls) {
    const s = mk('select', cls ?? 'w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500');
    options.forEach(o => { const opt = document.createElement('option'); opt.value = o; opt.textContent = o; if (o === selected) opt.selected = true; s.appendChild(opt); });
    return s;
}
function mkWrap(children) {
    const d = mk('div', null); children.forEach(c => d.appendChild(c)); return d;
}

function renderFieldCard(f) {
    const list = document.getElementById('fieldList');
    const el   = mk('div', 'bg-dark-900 border border-slate-700/60 rounded-lg p-3');
    el.id = 'fc-' + f.id;
    const isText   = f.field_key !== 'photo' && f.field_key !== 'qr_code';
    const isCustom = f.field_key.startsWith('custom_');

    // Header row
    const header = mk('div', 'flex items-center justify-between mb-2');
    const title  = mk('span', 'text-xs font-semibold text-slate-200'); title.textContent = FIELD_LABELS[f.field_key] ?? f.field_key;
    const rmBtn  = mk('button', 'text-xs text-red-400 hover:text-red-300'); rmBtn.type = 'button'; rmBtn.textContent = 'Remove';
    rmBtn.addEventListener('click', () => removeField(f.id));
    header.appendChild(title); header.appendChild(rmBtn);
    el.appendChild(header);

    // X / Y / W / H row
    const xywh = mk('div', 'grid grid-cols-4 gap-1.5 text-xs mb-1.5');
    [['X px', 'x', f.x], ['Y px', 'y', f.y], ['W px', 'w', f.w], ['H px', 'h', f.h]].forEach(([lbl, key, val]) => {
        const inp = mkInput('number', val);
        inp.addEventListener('input', () => updateField(f.id, key, +inp.value));
        el['_inp_' + key] = inp;
        xywh.appendChild(mkWrap([mkLabel(lbl), inp]));
    });
    el.appendChild(xywh);

    if (isText) {
        const row2 = mk('div', 'grid grid-cols-2 gap-1.5 text-xs mb-1.5');

        const fontSel = mkSelect(['Inter','NotoSansEthiopic','Roboto'], f.font_family);
        fontSel.addEventListener('input', () => updateField(f.id, 'font_family', fontSel.value));
        row2.appendChild(mkWrap([mkLabel('Font'), fontSel]));

        const sizeInp = mkInput('number', f.font_size);
        sizeInp.addEventListener('input', () => updateField(f.id, 'font_size', +sizeInp.value));
        row2.appendChild(mkWrap([mkLabel('Size'), sizeInp]));

        const colorInp = mkInput('color', f.font_color, 'w-full h-8 rounded cursor-pointer bg-dark-800 border border-slate-700');
        colorInp.addEventListener('input', () => updateField(f.id, 'font_color', colorInp.value));
        row2.appendChild(mkWrap([mkLabel('Color'), colorInp]));

        const alignSel = mkSelect(['left','center','right'], f.text_align);
        alignSel.addEventListener('input', () => updateField(f.id, 'text_align', alignSel.value));
        row2.appendChild(mkWrap([mkLabel('Align'), alignSel]));

        el.appendChild(row2);
    }

    if (isCustom) {
        const customInp = mkInput('text', f.custom_value ?? '', 'w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500 mt-0.5');
        customInp.addEventListener('input', () => updateField(f.id, 'custom_value', customInp.value));
        const wrap = mk('div', 'text-xs'); wrap.appendChild(mkLabel('Custom text')); wrap.appendChild(customInp);
        el.appendChild(wrap);
    }

    list.appendChild(el);
}

// ── Overlay management ────────────────────────────────────────────────────
function overlayColor(key) {
    if (key === 'photo')   return 'rgba(59,130,246,0.3)';
    if (key === 'qr_code') return 'rgba(139,92,246,0.3)';
    return 'rgba(16,185,129,0.25)';
}

function renderOverlay(f) {
    const preview = document.getElementById('card-preview');
    let el = document.getElementById('ov-' + f.id);
    if (!el) {
        el = document.createElement('div');
        el.id = 'ov-' + f.id;
        el.style.cssText = 'position:absolute;border:2px dashed rgba(255,255,255,0.6);cursor:move;user-select:none;display:flex;align-items:center;justify-content:center;box-sizing:border-box;';
        preview.appendChild(el);
        makeDraggable(el, f.id);
    }
    el.style.left   = f.x + 'px';
    el.style.top    = f.y + 'px';
    el.style.width  = (f.w || 100) + 'px';
    el.style.height = (f.h || 30) + 'px';
    el.style.background = overlayColor(f.field_key);
    el.innerHTML = `<span style="font-size:10px;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.8);padding:2px 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${FIELD_LABELS[f.field_key] ?? f.field_key}</span>`;
}

function removeOverlay(id) {
    document.getElementById('ov-' + id)?.remove();
}

// ── Drag-to-position ──────────────────────────────────────────────────────
function makeDraggable(el, id) {
    let startX, startY, origX, origY;
    el.addEventListener('mousedown', e => {
        e.preventDefault();
        startX = e.clientX; startY = e.clientY;
        const f = fields.find(x => x.id === id);
        origX = f.x; origY = f.y;
        function onMove(e) {
            // The preview is scaled 0.5, so divide delta by 0.5
            const dx = (e.clientX - startX) / 0.5;
            const dy = (e.clientY - startY) / 0.5;
            updateField(id, 'x', Math.round(Math.max(0, origX + dx)));
            updateField(id, 'y', Math.round(Math.max(0, origY + dy)));
        }
        function onUp() {
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
        }
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
    });
}

// ── Update field value ────────────────────────────────────────────────────
function updateField(id, key, value) {
    const f = fields.find(x => x.id === id);
    if (!f) return;
    f[key] = value;
    // Sync the input that triggered if it's x/y/w/h (from dragging)
    if (['x','y','w','h'].includes(key)) {
        const card = document.getElementById('fc-' + id);
        if (card) {
            const labels = {x:0,y:1,w:2,h:3};
            const inputs = card.querySelectorAll('input[type=number]');
            if (inputs[labels[key]]) inputs[labels[key]].value = value;
        }
    }
    renderOverlay(f);
}

function removeField(id) {
    fields = fields.filter(f => f.id !== id);
    document.getElementById('fc-' + id)?.remove();
    removeOverlay(id);
}

// ── Collect and submit ────────────────────────────────────────────────────
function collectFields() {
    document.getElementById('fieldsJson').value = JSON.stringify(
        fields.map(({field_key,x,y,w,h,font_family,font_size,font_color,text_align,font_weight,custom_value}) =>
            ({field_key,x,y,w,h,font_family,font_size,font_color,text_align,font_weight,custom_value})
        )
    );
}

// ── Image upload ──────────────────────────────────────────────────────────
function uploadImage(side, file) {
    const status = document.getElementById(side === 'front' ? 'frontStatus' : 'backStatus');
    status.textContent = 'Uploading…';
    const fd = new FormData();
    fd.append('side', side);
    fd.append('image', file);
    fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content ?? '');
    fetch('{{ route('admin.card-template.upload', $school) }}', {method:'POST', body:fd})
        .then(r => r.json())
        .then(data => {
            if (data.success && side === 'front') {
                const img = document.getElementById('previewBg');
                img.src = data.url + '?t=' + Date.now();
                img.style.display = '';
                status.textContent = 'Uploaded';
            } else if (data.success) {
                status.textContent = 'Uploaded (back)';
            } else {
                status.textContent = 'Error';
            }
        })
        .catch(() => { status.textContent = 'Upload failed'; });
}

// ── Wire up all button listeners ──────────────────────────────────────────
document.getElementById('btnUploadFront').addEventListener('click', () => document.getElementById('uploadFront').click());
document.getElementById('btnUploadBack').addEventListener('click',  () => document.getElementById('uploadBack').click());
document.getElementById('uploadFront').addEventListener('change', e => { if (e.target.files[0]) uploadImage('front', e.target.files[0]); });
document.getElementById('uploadBack').addEventListener('change',  e => { if (e.target.files[0]) uploadImage('back',  e.target.files[0]); });
document.getElementById('btnAddField').addEventListener('click', () => addField());
document.getElementById('btnSave').addEventListener('click', () => collectFields());
</script>
@endsection
