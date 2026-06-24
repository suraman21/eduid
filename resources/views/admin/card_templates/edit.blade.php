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


@php
    $frontPublicUrl = Storage::disk('public')->exists("card_previews/{$school->id}/front.png")
        ? Storage::disk('public')->url("card_previews/{$school->id}/front.png") . '?v=' . filemtime(Storage::disk('public')->path("card_previews/{$school->id}/front.png"))
        : (($template && Storage::disk('local')->exists("cards/{$school->id}/front.png"))
            ? route('admin.card-template.image', [$school, 'front']) . '?v=' . time()
            : '');
    $backExists = $template && Storage::disk('local')->exists("cards/{$school->id}/back.png");
    $backPublicUrl = Storage::disk('public')->exists("card_previews/{$school->id}/back.png")
        ? Storage::disk('public')->url("card_previews/{$school->id}/back.png") . '?v=' . filemtime(Storage::disk('public')->path("card_previews/{$school->id}/back.png"))
        : ($backExists ? route('admin.card-template.image', [$school, 'back']) . '?v=' . time() : '');
    $backImgPath = $backExists ? "cards/{$school->id}/back.png" : null;
    $backInfo    = $backImgPath ? @getimagesize(Storage::disk('local')->path($backImgPath)) : null;
    $backW       = $backInfo ? $backInfo[0] : $canvasW;
    $backH       = $backInfo ? $backInfo[1] : $canvasH;
    $backScale   = round(400 / $backW, 4);
    $backDispH   = round($backH * $backScale);
    $hasFront    = $frontPublicUrl !== '';
@endphp

{{-- Three-panel editor: front | back | field config --}}
<div class="flex gap-4 items-start">

    {{-- FRONT preview --}}
    <div class="flex-1 min-w-0">
        <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-primary-500 inline-block"></span>
                    <span class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Front</span>
                    <span class="text-xs text-slate-500">drag fields to position</span>
                </div>
                <span id="canvasDims" class="text-xs text-slate-500">{{ $canvasW }}&times;{{ $canvasH }} px</span>
            </div>
            <div id="preview-wrapper" class="overflow-hidden rounded-lg bg-slate-950 relative mx-auto"
                 style="width:400px;height:{{ round(400/$canvasW*$canvasH) }}px;">
                <div id="card-preview" style="position:absolute;top:0;left:0;transform-origin:top left;overflow:hidden;
                     width:{{ $canvasW }}px;height:{{ $canvasH }}px;transform:scale({{ round(400/$canvasW,4) }});">
                    <img id="previewBg" alt="" src="{{ $frontPublicUrl }}"
                         style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:fill;{{ $hasFront ? '' : 'display:none;' }}">
                    <div id="previewPlaceholder" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:rgba(255,255,255,.25);font-size:13px;{{ $hasFront ? 'display:none;' : '' }}">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 20M6 8h.01M4 6h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                        Upload a front image to begin
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BACK preview --}}
    <div class="flex-1 min-w-0">
        <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-slate-500 inline-block"></span>
                    <span class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Back</span>
                    <span class="text-xs text-slate-500">drag fields to position</span>
                </div>
                <span id="backDims" class="text-xs text-slate-500">{{ $backExists ? $backW.'×'.$backH.' px' : '' }}</span>
            </div>
            <div id="back-wrapper" class="overflow-hidden rounded-lg bg-slate-950 relative mx-auto"
                 style="width:400px;height:{{ $backDispH }}px;">
                <div id="back-canvas" style="position:absolute;top:0;left:0;transform-origin:top left;overflow:hidden;
                     width:{{ $backW }}px;height:{{ $backH }}px;transform:scale({{ $backScale }});">
                    <img id="previewBackBg" alt="" src="{{ $backPublicUrl }}"
                         style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:fill;{{ $backExists ? '' : 'display:none;' }}">
                    <div id="backPlaceholder" style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:rgba(255,255,255,.25);font-size:13px;{{ $backExists ? 'display:none;' : '' }}">
                        <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 20M6 8h.01M4 6h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2z"/></svg>
                        Upload a back image
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Field config panel --}}
    <div style="width:290px;flex-shrink:0;">
        <div class="bg-dark-800 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-3">

            {{-- Side toggle + field picker --}}
            <div>
                <p class="text-xs text-slate-500 mb-1.5 font-medium">Add to side:</p>
                <div class="flex items-center gap-1 mb-2">
                    <button type="button" id="sideToggleFront"
                            class="flex-1 text-xs py-1.5 rounded-md font-medium transition bg-primary-600 text-white">
                        ↑ Front
                    </button>
                    <button type="button" id="sideToggleBack"
                            class="flex-1 text-xs py-1.5 rounded-md font-medium transition bg-dark-900 border border-slate-700 text-slate-400 hover:text-white">
                        ↓ Back
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <select id="addFieldKey"
                            class="flex-1 bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-primary-500">
                        <option value="">— select field —</option>
                        @foreach($fieldLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="button" id="btnAddField"
                            class="bg-primary-600 hover:bg-primary-700 text-white text-sm px-4 py-2 rounded-lg whitespace-nowrap transition">
                        + Add
                    </button>
                </div>
            </div>

            <div id="fieldList" class="flex flex-col gap-2.5 max-h-[480px] overflow-y-auto pr-1"></div>

            <div class="pt-3 border-t border-slate-700/50">
                <input type="hidden" id="fieldsJson"  value="[]">
                <input type="hidden" id="canvasWInput" value="{{ $canvasW }}">
                <input type="hidden" id="canvasHInput" value="{{ $canvasH }}">
                <input type="text" id="templateName" placeholder="Template name (optional)"
                       value="{{ $template?->name }}"
                       class="w-full bg-dark-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary-500 mb-2">
                <button type="button" id="btnSave"
                        class="w-full bg-green-600 hover:bg-green-700 text-white text-sm px-5 py-2 rounded-lg font-medium transition">
                    Save Template
                </button>
                <p id="saveStatus" class="text-xs text-slate-500 mt-1.5 text-center hidden"></p>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce }}">
// ── State ──────────────────────────────────────────────────────────────────
const FIELD_LABELS    = @json($fieldLabels);
const SAVE_URL        = '{{ route('admin.card-template.save', $school) }}';
const CSRF            = document.querySelector('meta[name=csrf-token]')?.content ?? '';
const PREVIEW_W       = 400;

let canvasW       = {{ $canvasW }};
let canvasH       = {{ $canvasH }};
let currentScale  = PREVIEW_W / canvasW;
let backCanvasW   = {{ $backW }};
let backCanvasH   = {{ $backH }};
let backScale     = PREVIEW_W / backCanvasW;
let addSide       = 'front';
let fields        = [];
let nextId        = 0;
let saveTimer     = null;

const SAMPLE_DATA = {
    full_name:     'Kidus Bizuayehu',
    full_name_am:  'ቅዱስ ብዙአየሁ',
    uid:           'STU-2024-00123',
    grade_class:   'Grade 10 — Natural',
    section:       'Section A',
    academic_year: '2024 / 2025',
    school_name:   'Abyssinia Academy',
    custom_1:      'Custom Text 1',
    custom_2:      'Custom Text 2',
    custom_3:      'Custom Text 3',
};

// Load saved fields
const savedFields = @json($savedFields ?? []);
savedFields.forEach(f => addFieldObject({...f, id: nextId++}));

// ── Side toggle ────────────────────────────────────────────────────────────
function setSide(side) {
    addSide = side;
    const btnF = document.getElementById('sideToggleFront');
    const btnB = document.getElementById('sideToggleBack');
    if (side === 'front') {
        btnF.className = 'flex-1 text-xs py-1.5 rounded-md font-medium transition bg-primary-600 text-white';
        btnB.className = 'flex-1 text-xs py-1.5 rounded-md font-medium transition bg-dark-900 border border-slate-700 text-slate-400 hover:text-white';
    } else {
        btnB.className = 'flex-1 text-xs py-1.5 rounded-md font-medium transition bg-indigo-600 text-white';
        btnF.className = 'flex-1 text-xs py-1.5 rounded-md font-medium transition bg-dark-900 border border-slate-700 text-slate-400 hover:text-white';
    }
}

// ── Preview sizing ─────────────────────────────────────────────────────────
function updatePreviewSize(w, h) {
    canvasW = w; canvasH = h;
    currentScale = PREVIEW_W / w;
    document.getElementById('preview-wrapper').style.height = Math.round(h * currentScale) + 'px';
    const cv = document.getElementById('card-preview');
    cv.style.width = w + 'px'; cv.style.height = h + 'px';
    cv.style.transform = 'scale(' + currentScale.toFixed(4) + ')';
    document.getElementById('canvasDims').textContent = w + '×' + h + ' px';
    document.getElementById('canvasWInput').value = w;
    document.getElementById('canvasHInput').value = h;
    fields.forEach(f => renderOverlay(f));
}

function updateBackSize(w, h) {
    backCanvasW = w; backCanvasH = h;
    backScale = PREVIEW_W / w;
    document.getElementById('back-wrapper').style.height = Math.round(h * backScale) + 'px';
    const cv = document.getElementById('back-canvas');
    cv.style.width = w + 'px'; cv.style.height = h + 'px';
    cv.style.transform = 'scale(' + backScale.toFixed(4) + ')';
    document.getElementById('backDims').textContent = w + '×' + h + ' px';
    fields.forEach(f => { if ((f.side ?? 'front') === 'back') renderOverlay(f); });
}

// ── Add field ──────────────────────────────────────────────────────────────
function addField() {
    const sel = document.getElementById('addFieldKey');
    const key = sel.value;
    if (!key) return;
    const isImage = key === 'photo' || key === 'qr_code';
    const w = isImage ? Math.round((addSide==='back'?backCanvasW:canvasW) * 0.1) : Math.round((addSide==='back'?backCanvasW:canvasW) * 0.3);
    const h = isImage ? Math.round((addSide==='back'?backCanvasH:canvasH) * 0.18) : Math.round((addSide==='back'?backCanvasH:canvasH) * 0.07);
    addFieldObject({
        id: nextId++, field_key: key, side: addSide,
        x: 50, y: 50, w, h,
        font_family: 'Inter', font_size: 14, font_color: '#000000',
        text_align: 'left', font_weight: 'normal', custom_value: '', border_radius: 0,
    });
    sel.value = '';
}

function addFieldObject(f) {
    if (!f.side) f.side = 'front';
    if (!f.border_radius) f.border_radius = 0;
    fields.push(f);
    renderFieldCard(f);
    renderOverlay(f);
}

// ── DOM helpers ────────────────────────────────────────────────────────────
function mk(tag, cls, props = {}) {
    const el = document.createElement(tag);
    if (cls) el.className = cls;
    Object.entries(props).forEach(([k, v]) => { el[k] = v; });
    return el;
}
function mkLabel(text) { const l = mk('label','block text-slate-500 mb-0.5 text-xs'); l.textContent=text; return l; }
function mkNumber(value, onChange) {
    const i = mk('input','w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500');
    i.type='number'; i.value=value;
    i.addEventListener('input', () => onChange(+i.value)); return i;
}
function mkSelect(opts, selected, onChange) {
    const s = mk('select','w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500');
    opts.forEach(o => { const opt=document.createElement('option'); opt.value=o; opt.textContent=o; if(o===selected) opt.selected=true; s.appendChild(opt); });
    s.addEventListener('input', () => onChange(s.value)); return s;
}
function mkColor(value, onChange) {
    const i = mk('input','w-full h-8 rounded cursor-pointer bg-dark-800 border border-slate-700');
    i.type='color'; i.value=value;
    i.addEventListener('input', () => onChange(i.value)); return i;
}
function mkWrap(label, input) { const d=mk('div'); d.appendChild(mkLabel(label)); d.appendChild(input); return d; }

// ── Field card ─────────────────────────────────────────────────────────────
function renderFieldCard(f) {
    const list    = document.getElementById('fieldList');
    const el      = mk('div','bg-dark-900 border border-slate-700/60 rounded-lg p-3');
    el.id         = 'fc-' + f.id;
    const isText  = f.field_key !== 'photo' && f.field_key !== 'qr_code';
    const isCust  = f.field_key.startsWith('custom_');

    // Header: label + side badge + remove
    const header = mk('div','flex items-center justify-between mb-2');
    const titleWrap = mk('div','flex items-center gap-1.5');
    const title  = mk('span','text-xs font-semibold text-slate-200');
    title.textContent = FIELD_LABELS[f.field_key] ?? f.field_key;
    const sideBadge = mk('span', 'text-xs px-1.5 py-0.5 rounded font-medium ' + (f.side==='back' ? 'bg-indigo-900 text-indigo-300' : 'bg-primary-900 text-primary-300'));
    sideBadge.textContent = f.side === 'back' ? 'Back' : 'Front';
    sideBadge.id = 'badge-' + f.id;
    titleWrap.appendChild(title); titleWrap.appendChild(sideBadge);
    const rmBtn = mk('button','text-xs text-red-400 hover:text-red-300',{type:'button'});
    rmBtn.textContent = 'Remove';
    rmBtn.addEventListener('click', () => removeField(f.id));
    header.appendChild(titleWrap); header.appendChild(rmBtn);
    el.appendChild(header);

    // X / Y / W / H
    const xywh = mk('div','grid grid-cols-4 gap-1.5 text-xs mb-1.5');
    const xInp = mkNumber(f.x, v => updateField(f.id,'x',v));
    const yInp = mkNumber(f.y, v => updateField(f.id,'y',v));
    const wInp = mkNumber(f.w, v => updateField(f.id,'w',v));
    const hInp = mkNumber(f.h, v => updateField(f.id,'h',v));
    el._xInp=xInp; el._yInp=yInp; el._wInp=wInp; el._hInp=hInp;
    [['X px',xInp],['Y px',yInp],['W px',wInp],['H px',hInp]].forEach(([l,i]) => xywh.appendChild(mkWrap(l,i)));
    el.appendChild(xywh);

    // Border radius (all field types)
    const radRow = mk('div','grid grid-cols-2 gap-1.5 text-xs mb-1.5');
    radRow.appendChild(mkWrap('Corner Radius px', mkNumber(f.border_radius, v => updateField(f.id,'border_radius',v))));
    el.appendChild(radRow);

    if (isText) {
        const row2 = mk('div','grid grid-cols-2 gap-1.5 text-xs mb-1.5');
        row2.appendChild(mkWrap('Font',   mkSelect(['Inter','NotoSansEthiopic','Roboto'],f.font_family,v=>updateField(f.id,'font_family',v))));
        row2.appendChild(mkWrap('Size px',mkNumber(f.font_size,v=>updateField(f.id,'font_size',v))));
        row2.appendChild(mkWrap('Color',  mkColor(f.font_color,v=>updateField(f.id,'font_color',v))));
        row2.appendChild(mkWrap('Align',  mkSelect(['left','center','right'],f.text_align,v=>updateField(f.id,'text_align',v))));
        el.appendChild(row2);
    }

    if (isCust) {
        const inp = mk('input','w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500 mt-0.5');
        inp.type='text'; inp.value=f.custom_value??'';
        inp.addEventListener('input',()=>updateField(f.id,'custom_value',inp.value));
        const w = mk('div','text-xs'); w.appendChild(mkLabel('Custom text')); w.appendChild(inp);
        el.appendChild(w);
    }

    list.appendChild(el);
}

// ── Overlay (WYSIWYG) ──────────────────────────────────────────────────────
function renderOverlay(f) {
    const isFront  = (f.side ?? 'front') !== 'back';
    const canvasEl = isFront ? document.getElementById('card-preview') : document.getElementById('back-canvas');
    if (!canvasEl) return;

    let el = document.getElementById('ov-' + f.id);
    if (!el) {
        el = document.createElement('div');
        el.id = 'ov-' + f.id;
        el.style.cssText = 'position:absolute;box-sizing:border-box;cursor:move;user-select:none;overflow:hidden;';
        canvasEl.appendChild(el);
        makeDraggable(el, f.id);
    } else if (el.parentElement !== canvasEl) {
        // Side changed — move overlay to correct canvas
        canvasEl.appendChild(el);
    }

    const w = f.w || 80; const h = f.h || 30;
    el.style.left         = f.x + 'px';
    el.style.top          = f.y + 'px';
    el.style.width        = w + 'px';
    el.style.height       = h + 'px';
    el.style.borderRadius = (f.border_radius || 0) + 'px';
    el.innerHTML = '';

    const isPhoto = f.field_key === 'photo';
    const isQr    = f.field_key === 'qr_code';

    if (isPhoto) {
        el.style.border='2px solid rgba(59,130,246,.85)'; el.style.background='rgba(30,58,138,.55)';
        el.style.display='flex'; el.style.alignItems='center'; el.style.justifyContent='center'; el.style.flexDirection='column'; el.style.gap='4px';
        const svg=document.createElement('div');
        svg.innerHTML='<svg width="'+Math.round(w*.38)+'" height="'+Math.round(h*.45)+'" viewBox="0 0 24 24" fill="rgba(147,197,253,.9)" style="pointer-events:none"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>';
        el.appendChild(svg);
        const t=document.createElement('span'); t.style.cssText='font-size:10px;color:rgba(147,197,253,.9);pointer-events:none;'; t.textContent='Photo';
        el.appendChild(t);
    } else if (isQr) {
        el.style.border='2px solid rgba(139,92,246,.85)'; el.style.background='#fff';
        el.style.display='flex'; el.style.alignItems='center'; el.style.justifyContent='center';
        const sz=Math.min(w,h)-8;
        const div=document.createElement('div');
        div.style.cssText='width:'+sz+'px;height:'+sz+'px;pointer-events:none;background-image:repeating-linear-gradient(0deg,#333 0,#333 1px,transparent 1px,transparent 4px),repeating-linear-gradient(90deg,#333 0,#333 1px,transparent 1px,transparent 4px);background-size:4px 4px;';
        el.appendChild(div);
    } else {
        const sample = (f.custom_value && f.custom_value.trim()) ? f.custom_value : (SAMPLE_DATA[f.field_key] ?? FIELD_LABELS[f.field_key] ?? f.field_key);
        el.style.border='2px dashed rgba(16,185,129,.75)'; el.style.background='rgba(6,78,59,.25)';
        el.style.display='flex'; el.style.alignItems='flex-start'; el.style.padding='2px 4px';
        el.style.justifyContent = f.text_align==='center' ? 'center' : f.text_align==='right' ? 'flex-end' : 'flex-start';
        const txt=document.createElement('span');
        txt.style.cssText='pointer-events:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.2;font-size:'+(f.font_size||14)+'px;color:'+(f.font_color||'#000000')+';font-weight:'+(f.font_weight==='bold'?'700':'400')+';text-shadow:0 0 6px rgba(255,255,255,.6),0 1px 3px rgba(0,0,0,.7);';
        txt.textContent=sample;
        el.appendChild(txt);
    }
}

// ── Drag ───────────────────────────────────────────────────────────────────
function makeDraggable(el, id) {
    let startX, startY, origX, origY;
    el.addEventListener('mousedown', e => {
        e.preventDefault();
        startX=e.clientX; startY=e.clientY;
        const f=fields.find(x=>x.id===id); origX=f.x; origY=f.y;
        const scale = (f.side??'front')==='back' ? backScale : currentScale;
        function onMove(e) {
            const nx=Math.round(Math.max(0,origX+(e.clientX-startX)/scale));
            const ny=Math.round(Math.max(0,origY+(e.clientY-startY)/scale));
            updateField(id,'x',nx); updateField(id,'y',ny);
        }
        function onUp() { window.removeEventListener('mousemove',onMove); window.removeEventListener('mouseup',onUp); }
        window.addEventListener('mousemove',onMove); window.addEventListener('mouseup',onUp);
    });
}

// ── Update field ───────────────────────────────────────────────────────────
function updateField(id, key, value) {
    const f=fields.find(x=>x.id===id); if(!f) return;
    f[key]=value;
    const card=document.getElementById('fc-'+id);
    if(card){
        if(key==='x'&&card._xInp) card._xInp.value=value;
        if(key==='y'&&card._yInp) card._yInp.value=value;
    }
    renderOverlay(f);
}

function removeField(id) {
    fields=fields.filter(f=>f.id!==id);
    document.getElementById('fc-'+id)?.remove();
    document.getElementById('ov-'+id)?.remove();
}

// ── Save (AJAX — no page reload) ───────────────────────────────────────────
function collectAndSave(silent=false) {
    const payload = JSON.stringify(
        fields.map(({field_key,x,y,w,h,font_family,font_size,font_color,text_align,font_weight,custom_value,side,border_radius}) =>
            ({field_key,x,y,w,h,font_family,font_size,font_color,text_align,font_weight,custom_value,side,border_radius}))
    );
    const fd = new FormData();
    fd.append('_token',   CSRF);
    fd.append('fields',   payload);
    fd.append('canvas_w', document.getElementById('canvasWInput').value);
    fd.append('canvas_h', document.getElementById('canvasHInput').value);
    fd.append('name',     document.getElementById('templateName').value);

    const btn    = document.getElementById('btnSave');
    const status = document.getElementById('saveStatus');
    if (!silent) { btn.disabled=true; btn.textContent='Saving…'; }

    fetch(SAVE_URL, {
        method: 'POST',
        headers: {'Accept':'application/json','X-CSRF-TOKEN':CSRF},
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (!silent) {
            btn.disabled=false; btn.textContent='Save Template';
            status.textContent = data.success ? '✓ Saved (v'+data.version+')' : '✗ '+data.message;
            status.className   = 'text-xs mt-1.5 text-center ' + (data.success ? 'text-green-400' : 'text-red-400');
            status.classList.remove('hidden');
            setTimeout(() => status.classList.add('hidden'), 3000);
        }
    })
    .catch(() => {
        if (!silent) { btn.disabled=false; btn.textContent='Save Template'; showMsg('Save failed — check connection.','error'); }
    });
}

// ── Message bar ───────────────────────────────────────────────────────────
function showMsg(text, type) {
    const el=document.getElementById('uploadMsg');
    el.className='mt-3 px-3 py-2 rounded-lg text-sm '+(type==='error'?'bg-red-500/10 border border-red-500/30 text-red-400':type==='success'?'bg-green-500/10 border border-green-500/30 text-green-400':'bg-blue-500/10 border border-blue-500/30 text-blue-400');
    el.textContent=text; el.classList.remove('hidden');
}

// ── Image upload ───────────────────────────────────────────────────────────
function uploadImage(side, file) {
    const status = document.getElementById(side==='front'?'frontStatus':'backStatus');
    status.textContent='';
    showMsg('Uploading '+side+' image…','info');

    const fd=new FormData();
    fd.append('side',side); fd.append('image',file); fd.append('_token',CSRF);

    fetch('{{ route('admin.card-template.upload', $school) }}',{method:'POST',body:fd})
    .then(r=>{
        if(!r.ok) return r.text().then(t=>{throw new Error('Server '+r.status+': '+t.slice(0,200));});
        return r.json();
    })
    .then(data=>{
        if(!data.success){ showMsg('Upload failed: '+(data.error??'unknown'),'error'); status.textContent='Failed'; return; }
        status.textContent=data.width+'×'+data.height+' px';
        const img=document.getElementById(side==='front'?'previewBg':'previewBackBg');
        const ph =document.getElementById(side==='front'?'previewPlaceholder':'backPlaceholder');
        img.src=data.url+'?t='+Date.now(); img.style.display='';
        if(ph) ph.style.display='none';
        if(side==='front'){
            updatePreviewSize(data.width,data.height);
            showMsg('Front image uploaded ('+data.width+'×'+data.height+' px). Canvas updated.','success');
        } else {
            updateBackSize(data.width,data.height);
            showMsg('Back image uploaded ('+data.width+'×'+data.height+' px).','success');
        }
        // Auto-save so image paths and canvas dims are persisted immediately
        collectAndSave(true);
    })
    .catch(err=>{ showMsg('Upload error: '+err.message,'error'); status.textContent='Error'; });
}

// ── Wire ───────────────────────────────────────────────────────────────────
document.getElementById('btnUploadFront').addEventListener('click',()=>document.getElementById('uploadFront').click());
document.getElementById('btnUploadBack').addEventListener('click', ()=>document.getElementById('uploadBack').click());
document.getElementById('uploadFront').addEventListener('change',e=>{if(e.target.files[0]) uploadImage('front',e.target.files[0]); e.target.value='';});
document.getElementById('uploadBack').addEventListener('change', e=>{if(e.target.files[0]) uploadImage('back', e.target.files[0]); e.target.value='';});
document.getElementById('btnAddField').addEventListener('click',()=>addField());
document.getElementById('btnSave').addEventListener('click',()=>collectAndSave(false));
document.getElementById('sideToggleFront').addEventListener('click',()=>setSide('front'));
document.getElementById('sideToggleBack').addEventListener('click', ()=>setSide('back'));
</script>
@endsection
