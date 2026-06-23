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
        <button onclick="document.getElementById('uploadFront').click()"
                class="border border-slate-600 hover:border-primary-500 text-slate-300 text-sm px-3 py-1.5 rounded-lg transition">
            Upload Front PNG/JPG
        </button>
        <span id="frontStatus" class="text-xs text-slate-500"></span>
    </div>
    <div class="flex items-center gap-3">
        <label class="text-xs text-slate-400 uppercase tracking-wide">Back Image</label>
        <input type="file" id="uploadBack" accept=".png,.jpg,.jpeg" class="hidden">
        <button onclick="document.getElementById('uploadBack').click()"
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
                <button onclick="addField()"
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
                <button type="submit" onclick="collectFields()"
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

// ── Render one field config card ──────────────────────────────────────────
function renderFieldCard(f) {
    const list = document.getElementById('fieldList');
    const el = document.createElement('div');
    el.id = 'fc-' + f.id;
    el.className = 'bg-dark-900 border border-slate-700/60 rounded-lg p-3';
    const isText = f.field_key !== 'photo' && f.field_key !== 'qr_code';
    el.innerHTML = `
<div class="flex items-center justify-between mb-2">
    <span class="text-xs font-semibold text-slate-200">${FIELD_LABELS[f.field_key] ?? f.field_key}</span>
    <button class="text-xs text-red-400 hover:text-red-300" onclick="removeField(${f.id})">Remove</button>
</div>
<div class="grid grid-cols-4 gap-1.5 text-xs mb-1.5">
    <div><label class="text-slate-500">X px</label><input type="number" value="${f.x}" oninput="updateField(${f.id},'x',+this.value)"
        class="w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500"></div>
    <div><label class="text-slate-500">Y px</label><input type="number" value="${f.y}" oninput="updateField(${f.id},'y',+this.value)"
        class="w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500"></div>
    <div><label class="text-slate-500">W px</label><input type="number" value="${f.w}" oninput="updateField(${f.id},'w',+this.value)"
        class="w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500"></div>
    <div><label class="text-slate-500">H px</label><input type="number" value="${f.h}" oninput="updateField(${f.id},'h',+this.value)"
        class="w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500"></div>
</div>
${isText ? `
<div class="grid grid-cols-2 gap-1.5 text-xs mb-1.5">
    <div><label class="text-slate-500">Font</label>
        <select oninput="updateField(${f.id},'font_family',this.value)"
            class="w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500">
            <option ${f.font_family==='Inter'?'selected':''}>Inter</option>
            <option ${f.font_family==='NotoSansEthiopic'?'selected':''}>NotoSansEthiopic</option>
            <option ${f.font_family==='Roboto'?'selected':''}>Roboto</option>
        </select>
    </div>
    <div><label class="text-slate-500">Size</label><input type="number" value="${f.font_size}" oninput="updateField(${f.id},'font_size',+this.value)"
        class="w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500"></div>
    <div><label class="text-slate-500">Color</label><input type="color" value="${f.font_color}" oninput="updateField(${f.id},'font_color',this.value)"
        class="w-full h-8 rounded cursor-pointer bg-dark-800 border border-slate-700"></div>
    <div><label class="text-slate-500">Align</label>
        <select oninput="updateField(${f.id},'text_align',this.value)"
            class="w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500">
            <option ${f.text_align==='left'?'selected':''}>left</option>
            <option ${f.text_align==='center'?'selected':''}>center</option>
            <option ${f.text_align==='right'?'selected':''}>right</option>
        </select>
    </div>
</div>` : ''}
${f.field_key.startsWith('custom_') ? `
<div class="text-xs"><label class="text-slate-500">Custom text</label>
<input type="text" value="${f.custom_value ?? ''}" oninput="updateField(${f.id},'custom_value',this.value)"
    class="w-full bg-dark-800 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500 mt-0.5"></div>` : ''}
`;
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

document.getElementById('uploadFront').addEventListener('change', e => {
    if (e.target.files[0]) uploadImage('front', e.target.files[0]);
});
document.getElementById('uploadBack').addEventListener('change', e => {
    if (e.target.files[0]) uploadImage('back', e.target.files[0]);
});
</script>
@endsection
