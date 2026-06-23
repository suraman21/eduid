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
        <span class="text-xs text-slate-500">v{{ $template->version }} &middot; {{ $template->fields->count() }} field(s) &middot; {{ $canvasW }}&times;{{ $canvasH }} px</span>
    @endif
</div>

{{-- Upload bar --}}
<div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4 mb-5">
    <div class="flex flex-wrap items-center gap-6">
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400 uppercase tracking-wide font-medium">Front</span>
            <input type="file" id="uploadFront" accept=".png,.jpg,.jpeg" class="hidden">
            <button type="button" id="btnUploadFront"
                    class="border border-slate-600 hover:border-primary-500 text-slate-300 text-sm px-3 py-1.5 rounded-lg transition">
                Upload Image
            </button>
            <span id="frontStatus" class="text-xs text-slate-500"></span>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-xs text-slate-400 uppercase tracking-wide font-medium">Back</span>
            <input type="file" id="uploadBack" accept=".png,.jpg,.jpeg" class="hidden">
            <button type="button" id="btnUploadBack"
                    class="border border-slate-600 hover:border-primary-500 text-slate-300 text-sm px-3 py-1.5 rounded-lg transition">
                Upload Image
            </button>
            <span id="backStatus" class="text-xs text-slate-500"></span>
        </div>
        <div class="flex-1 text-right text-xs text-slate-600">
            Any size &amp; orientation — portrait or landscape. Uploaded image dimensions set the canvas.
        </div>
    </div>

    {{-- Upload progress / error bar --}}
    <div id="uploadMsg" class="hidden mt-3 px-3 py-2 rounded-lg text-sm"></div>
</div>

{{-- Two-panel editor --}}
<div class="flex gap-5">

    {{-- Left: live preview --}}
    <div class="flex-[3] min-w-0">
        <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs text-slate-500 uppercase tracking-wide">Live Preview — Front</p>
                <span id="canvasDims" class="text-xs text-slate-600">{{ $canvasW }}&times;{{ $canvasH }} px</span>
            </div>

            {{-- Outer scaler — width fixed at 500px; height adjusts to canvas aspect ratio --}}
            <div id="preview-wrapper" class="overflow-hidden rounded-lg bg-slate-950 relative"
                 style="width:500px;height:{{ round(500 / $canvasW * $canvasH) }}px;">
                <div id="card-preview" style="position:absolute;top:0;left:0;transform-origin:top left;overflow:hidden;
                     width:{{ $canvasW }}px;height:{{ $canvasH }}px;transform:scale({{ round(500/$canvasW, 4) }});">
                    <img id="previewBg" alt=""
                         src="{{ $template && Storage::disk('local')->exists("cards/{$school->id}/front.png") ? route('admin.card-template.image', [$school, 'front']) . '?v=' . time() : '' }}"
                         style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:fill;{{ ($template && Storage::disk('local')->exists('cards/' . $school->id . '/front.png')) ? '' : 'display:none;' }}">
                    {{-- Placeholder when no image --}}
                    <div id="previewPlaceholder" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.2);font-size:14px;
                         {{ ($template && Storage::disk('local')->exists('cards/' . $school->id . '/front.png')) ? 'display:none;' : '' }}">
                        Upload a front image to begin
                    </div>
                    {{-- Field overlays injected by JS --}}
                </div>
            </div>
        </div>
    </div>

    {{-- Right: field config panel --}}
    <div class="flex-[2] min-w-0">
        <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-4">

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

            <div id="fieldList" class="flex flex-col gap-3 max-h-[480px] overflow-y-auto pr-1"></div>

            <form id="saveForm" method="POST" action="{{ route('admin.card-template.save', $school) }}"
                  class="mt-2 pt-3 border-t border-slate-700/50 flex items-center gap-3">
                @csrf
                <input type="hidden" name="fields"   id="fieldsJson"  value="[]">
                <input type="hidden" name="canvas_w" id="canvasWInput" value="{{ $canvasW }}">
                <input type="hidden" name="canvas_h" id="canvasHInput" value="{{ $canvasH }}">
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
// ── State ──────────────────────────────────────────────────────────────────
const FIELD_LABELS = @json($fieldLabels);
let canvasW = {{ $canvasW }};
let canvasH = {{ $canvasH }};
const PREVIEW_W = 500;  // outer wrapper fixed width in CSS pixels
let currentScale = PREVIEW_W / canvasW;
let fields = [];
let nextId  = 0;

const savedFields = @json($savedFields ?? []);
savedFields.forEach(f => addFieldObject({...f, id: nextId++}));

// ── Preview sizing ─────────────────────────────────────────────────────────
function updatePreviewSize(w, h) {
    canvasW = w; canvasH = h;
    currentScale = PREVIEW_W / canvasW;
    const scaledH = Math.round(canvasH * currentScale);

    const wrapper  = document.getElementById('preview-wrapper');
    const canvas   = document.getElementById('card-preview');
    const bg       = document.getElementById('previewBg');

    wrapper.style.height  = scaledH + 'px';
    canvas.style.width    = canvasW + 'px';
    canvas.style.height   = canvasH + 'px';
    canvas.style.transform = 'scale(' + currentScale.toFixed(4) + ')';

    bg.style.width  = '100%';
    bg.style.height = '100%';

    document.getElementById('canvasDims').textContent = canvasW + '×' + canvasH + ' px';
    document.getElementById('canvasWInput').value = canvasW;
    document.getElementById('canvasHInput').value = canvasH;

    // Re-render all overlays so they stay in the right place.
    fields.forEach(f => renderOverlay(f));
}

// ── Add field ──────────────────────────────────────────────────────────────
function addField() {
    const sel = document.getElementById('addFieldKey');
    const key = sel.value;
    if (!key) return;
    const isImage = key === 'photo' || key === 'qr_code';
    addFieldObject({
        id: nextId++, field_key: key,
        x: 50, y: 50,
        w: isImage ? Math.round(canvasW * 0.1) : Math.round(canvasW * 0.3),
        h: isImage ? Math.round(canvasH * 0.18) : Math.round(canvasH * 0.07),
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

// ── Build field config card (DOM API — no inline handlers for CSP) ─────────
function mk(tag, cls, props = {}) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    Object.entries(props).forEach(([k, v]) => { el[k] = v; });
    return el;
}
function mkLabel(text) {
    const l = mk('label', 'block text-slate-500 mb-0.5'); l.textContent = text; return l;
}
function mkNumber(value, onChange) {
    const i = mk('input', 'w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500');
    i.type = 'number'; i.value = value;
    i.addEventListener('input', () => onChange(+i.value));
    return i;
}
function mkSelect(opts, selected, onChange) {
    const s = mk('select', 'w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500');
    opts.forEach(o => {
        const opt = document.createElement('option');
        opt.value = o; opt.textContent = o; if (o === selected) opt.selected = true;
        s.appendChild(opt);
    });
    s.addEventListener('input', () => onChange(s.value));
    return s;
}
function mkColor(value, onChange) {
    const i = mk('input', 'w-full h-8 rounded cursor-pointer bg-dark-800 border border-slate-700');
    i.type = 'color'; i.value = value;
    i.addEventListener('input', () => onChange(i.value));
    return i;
}
function mkWrap(label, input) {
    const d = mk('div', null);
    d.appendChild(mkLabel(label)); d.appendChild(input); return d;
}

function renderFieldCard(f) {
    const list = document.getElementById('fieldList');
    const el   = mk('div', 'bg-dark-900 border border-slate-700/60 rounded-lg p-3');
    el.id = 'fc-' + f.id;

    const isText   = f.field_key !== 'photo' && f.field_key !== 'qr_code';
    const isCustom = f.field_key.startsWith('custom_');

    // Header
    const header = mk('div', 'flex items-center justify-between mb-2');
    const title  = mk('span', 'text-xs font-semibold text-slate-200');
    title.textContent = FIELD_LABELS[f.field_key] ?? f.field_key;
    const rmBtn = mk('button', 'text-xs text-red-400 hover:text-red-300', {type: 'button'});
    rmBtn.textContent = 'Remove';
    rmBtn.addEventListener('click', () => removeField(f.id));
    header.appendChild(title); header.appendChild(rmBtn);
    el.appendChild(header);

    // Position & size (X/Y/W/H)
    const xywh = mk('div', 'grid grid-cols-4 gap-1.5 text-xs mb-1.5');
    const xInp = mkNumber(f.x, v => { updateField(f.id, 'x', v); });
    const yInp = mkNumber(f.y, v => { updateField(f.id, 'y', v); });
    const wInp = mkNumber(f.w, v => { updateField(f.id, 'w', v); });
    const hInp = mkNumber(f.h, v => { updateField(f.id, 'h', v); });
    // Store refs for drag sync
    el._xInp = xInp; el._yInp = yInp; el._wInp = wInp; el._hInp = hInp;
    [['X px', xInp], ['Y px', yInp], ['W px', wInp], ['H px', hInp]]
        .forEach(([lbl, inp]) => xywh.appendChild(mkWrap(lbl, inp)));
    el.appendChild(xywh);

    if (isText) {
        const row2 = mk('div', 'grid grid-cols-2 gap-1.5 text-xs mb-1.5');
        row2.appendChild(mkWrap('Font',  mkSelect(['Inter','NotoSansEthiopic','Roboto'], f.font_family, v => updateField(f.id,'font_family',v))));
        row2.appendChild(mkWrap('Size',  mkNumber(f.font_size, v => updateField(f.id,'font_size',v))));
        row2.appendChild(mkWrap('Color', mkColor(f.font_color, v => updateField(f.id,'font_color',v))));
        row2.appendChild(mkWrap('Align', mkSelect(['left','center','right'], f.text_align, v => updateField(f.id,'text_align',v))));
        el.appendChild(row2);
    }

    if (isCustom) {
        const inp = mk('input', 'w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500 mt-0.5');
        inp.type = 'text'; inp.value = f.custom_value ?? '';
        inp.addEventListener('input', () => updateField(f.id, 'custom_value', inp.value));
        const w = mk('div', 'text-xs'); w.appendChild(mkLabel('Custom text')); w.appendChild(inp);
        el.appendChild(w);
    }

    list.appendChild(el);
}

// ── Overlay ────────────────────────────────────────────────────────────────
function overlayColor(key) {
    if (key === 'photo')   return 'rgba(59,130,246,0.35)';
    if (key === 'qr_code') return 'rgba(139,92,246,0.35)';
    return 'rgba(16,185,129,0.28)';
}

function renderOverlay(f) {
    const preview = document.getElementById('card-preview');
    let el = document.getElementById('ov-' + f.id);
    if (!el) {
        el = document.createElement('div');
        el.id = 'ov-' + f.id;
        el.style.cssText = 'position:absolute;border:2px dashed rgba(255,255,255,.65);cursor:move;user-select:none;display:flex;align-items:center;justify-content:center;box-sizing:border-box;';
        preview.appendChild(el);
        makeDraggable(el, f.id);
    }
    el.style.left       = f.x + 'px';
    el.style.top        = f.y + 'px';
    el.style.width      = (f.w || 80) + 'px';
    el.style.height     = (f.h || 30) + 'px';
    el.style.background = overlayColor(f.field_key);

    const lbl = el.querySelector('span') ?? document.createElement('span');
    lbl.style.cssText = 'font-size:10px;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.9);padding:2px 4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none;';
    lbl.textContent = FIELD_LABELS[f.field_key] ?? f.field_key;
    if (!el.contains(lbl)) el.appendChild(lbl);
}

// ── Drag-to-position ───────────────────────────────────────────────────────
function makeDraggable(el, id) {
    let startX, startY, origX, origY;
    el.addEventListener('mousedown', e => {
        e.preventDefault();
        startX = e.clientX; startY = e.clientY;
        const f = fields.find(x => x.id === id);
        origX = f.x; origY = f.y;

        function onMove(e) {
            const dx = (e.clientX - startX) / currentScale;
            const dy = (e.clientY - startY) / currentScale;
            const nx = Math.round(Math.max(0, origX + dx));
            const ny = Math.round(Math.max(0, origY + dy));
            updateField(id, 'x', nx);
            updateField(id, 'y', ny);
        }
        function onUp() {
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
        }
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
    });
}

// ── Update field ───────────────────────────────────────────────────────────
function updateField(id, key, value) {
    const f = fields.find(x => x.id === id);
    if (!f) return;
    f[key] = value;
    // Sync number inputs when dragging updates x/y
    const card = document.getElementById('fc-' + id);
    if (card) {
        if (key === 'x' && card._xInp) card._xInp.value = value;
        if (key === 'y' && card._yInp) card._yInp.value = value;
    }
    renderOverlay(f);
}

function removeField(id) {
    fields = fields.filter(f => f.id !== id);
    document.getElementById('fc-' + id)?.remove();
    document.getElementById('ov-' + id)?.remove();
}

// ── Collect & submit ───────────────────────────────────────────────────────
function collectFields() {
    document.getElementById('fieldsJson').value = JSON.stringify(
        fields.map(({field_key, x, y, w, h, font_family, font_size, font_color, text_align, font_weight, custom_value}) =>
            ({field_key, x, y, w, h, font_family, font_size, font_color, text_align, font_weight, custom_value})
        )
    );
}

// ── Image upload ───────────────────────────────────────────────────────────
function showMsg(text, type) {
    const el = document.getElementById('uploadMsg');
    el.className = 'mt-3 px-3 py-2 rounded-lg text-sm ' +
        (type === 'error'   ? 'bg-red-500/10 border border-red-500/30 text-red-400' :
         type === 'success' ? 'bg-green-500/10 border border-green-500/30 text-green-400' :
                              'bg-blue-500/10 border border-blue-500/30 text-blue-400');
    el.textContent = text;
    el.classList.remove('hidden');
}

function uploadImage(side, file) {
    const statusId = side === 'front' ? 'frontStatus' : 'backStatus';
    const status   = document.getElementById(statusId);
    status.textContent = '';
    showMsg('Uploading ' + side + ' image…', 'info');

    const fd = new FormData();
    fd.append('side',   side);
    fd.append('image',  file);
    fd.append('_token', document.querySelector('meta[name=csrf-token]')?.content ?? '');

    fetch('{{ route('admin.card-template.upload', $school) }}', {method: 'POST', body: fd})
        .then(r => {
            if (!r.ok) {
                return r.text().then(txt => { throw new Error('Server error ' + r.status + ': ' + txt.slice(0, 200)); });
            }
            return r.json();
        })
        .then(data => {
            if (!data.success) {
                showMsg('Upload failed: ' + (data.error ?? 'unknown error'), 'error');
                status.textContent = 'Failed';
                return;
            }
            status.textContent = data.width + '×' + data.height + ' px';

            if (side === 'front') {
                // Update preview background
                const img  = document.getElementById('previewBg');
                const ph   = document.getElementById('previewPlaceholder');
                img.style.display = '';
                if (ph) ph.style.display = 'none';
                // Resize canvas to match new image dims
                updatePreviewSize(data.width, data.height);
                // Force-reload the image (cache-bust)
                img.src = data.url + '?t=' + Date.now();
                showMsg('Front image uploaded (' + data.width + '×' + data.height + ' px). Canvas updated.', 'success');
            } else {
                showMsg('Back image uploaded (' + data.width + '×' + data.height + ' px).', 'success');
            }
        })
        .catch(err => {
            showMsg('Upload error: ' + err.message, 'error');
            status.textContent = 'Error';
        });
}

// ── Wire buttons ───────────────────────────────────────────────────────────
document.getElementById('btnUploadFront').addEventListener('click', () => document.getElementById('uploadFront').click());
document.getElementById('btnUploadBack').addEventListener('click',  () => document.getElementById('uploadBack').click());
document.getElementById('uploadFront').addEventListener('change', e => { if (e.target.files[0]) uploadImage('front', e.target.files[0]); e.target.value = ''; });
document.getElementById('uploadBack').addEventListener('change',  e => { if (e.target.files[0]) uploadImage('back',  e.target.files[0]); e.target.value = ''; });
document.getElementById('btnAddField').addEventListener('click', () => addField());
document.getElementById('saveForm').addEventListener('submit', () => collectFields());
</script>
@endsection
