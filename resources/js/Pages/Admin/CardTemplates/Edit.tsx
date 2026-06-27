import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import QRCode from 'qrcode';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import TemplateCard, { type TemplateField, type CardTemplateData, type TemplateValues } from '@/Components/TemplateCard';

// ── Types ──────────────────────────────────────────────────────────────────

interface Field {
  id: number;
  field_key: string;
  side: 'front' | 'back';
  x: number;
  y: number;
  w: number;
  h: number;
  font_family: string;
  font_size: number;
  font_color: string;
  text_align: 'left' | 'center' | 'right';
  font_weight: 'normal' | 'bold';
  custom_value: string;
  border_radius: number;
}

interface Props {
  school: { id: number; name: string };
  template: { version: number; field_count: number } | null;
  fieldLabels: Record<string, string>;
  savedFields: Omit<Field, 'id'>[];
  canvasW: number;
  canvasH: number;
  backW: number;
  backH: number;
  frontUrl: string | null;
  backUrl: string | null;
  uploadUrl: string;
  saveUrl: string;
  schoolUrl: string;
}

const PREVIEW_W = 400;

const SAMPLE_DATA: Record<string, string> = {
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

// ── Overlay rendering helper ───────────────────────────────────────────────

function FieldOverlay({ f, scale, onMouseDown }: {
  f: Field;
  scale: number;
  onMouseDown: (e: React.MouseEvent) => void;
}) {
  const isPhoto = f.field_key === 'photo';
  const isQr    = f.field_key === 'qr_code';
  const sz = Math.min(f.w, f.h) - 8;
  const sample = (f.custom_value && f.custom_value.trim())
    ? f.custom_value
    : (SAMPLE_DATA[f.field_key] ?? f.field_key);

  const base: React.CSSProperties = {
    position: 'absolute',
    boxSizing: 'border-box',
    cursor: 'move',
    userSelect: 'none',
    overflow: 'hidden',
    left: f.x,
    top: f.y,
    width: f.w || 80,
    height: f.h || 30,
    borderRadius: f.border_radius || 0,
  };

  if (isPhoto) {
    return (
      <div style={{ ...base, border: '2px solid rgba(59,130,246,.85)', background: 'rgba(30,58,138,.55)', display: 'flex', alignItems: 'center', justifyContent: 'center', flexDirection: 'column', gap: 4 }}
        onMouseDown={onMouseDown}>
        <svg width={Math.round((f.w || 80) * 0.38)} height={Math.round((f.h || 30) * 0.45)} viewBox="0 0 24 24" fill="rgba(147,197,253,.9)" style={{ pointerEvents: 'none' }}>
          <circle cx="12" cy="8" r="4" />
          <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
        </svg>
        <span style={{ fontSize: 10, color: 'rgba(147,197,253,.9)', pointerEvents: 'none' }}>Photo</span>
      </div>
    );
  }
  if (isQr) {
    return (
      <div style={{ ...base, border: '2px solid rgba(139,92,246,.85)', background: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center' }}
        onMouseDown={onMouseDown}>
        <div style={{ width: sz, height: sz, pointerEvents: 'none', backgroundImage: 'repeating-linear-gradient(0deg,#333 0,#333 1px,transparent 1px,transparent 4px),repeating-linear-gradient(90deg,#333 0,#333 1px,transparent 1px,transparent 4px)', backgroundSize: '4px 4px' }} />
      </div>
    );
  }
  return (
    <div style={{ ...base, border: '2px dashed rgba(16,185,129,.75)', background: 'rgba(6,78,59,.25)', display: 'flex', alignItems: 'flex-start', padding: '2px 4px', justifyContent: f.text_align === 'center' ? 'center' : f.text_align === 'right' ? 'flex-end' : 'flex-start' }}
      onMouseDown={onMouseDown}>
      <span style={{ pointerEvents: 'none', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', lineHeight: 1.2, fontSize: f.font_size || 14, color: f.font_color || '#000000', fontWeight: f.font_weight === 'bold' ? 700 : 400, textShadow: '0 0 6px rgba(255,255,255,.6),0 1px 3px rgba(0,0,0,.7)' }}>
        {sample}
      </span>
    </div>
  );
}

// ── Field config card ──────────────────────────────────────────────────────

function FieldCard({ f, onUpdate, onRemove }: {
  f: Field;
  onUpdate: (id: number, key: string, value: number | string) => void;
  onRemove: (id: number) => void;
}) {
  const isText = f.field_key !== 'photo' && f.field_key !== 'qr_code';
  const isCust = f.field_key.startsWith('custom_');
  const inputCls = 'w-full bg-slate-950 border border-slate-700 rounded px-2 py-1 text-white text-xs focus:outline-none focus:border-primary-500';
  const num = (label: string, key: keyof Field, min?: number) => (
    <div>
      <label className="block text-slate-500 mb-0.5 text-[10px]">{label}</label>
      <input type="number" min={min} className={inputCls} value={f[key] as number}
        onChange={e => onUpdate(f.id, key, +e.target.value)} />
    </div>
  );

  return (
    <div className="bg-slate-950 border border-slate-700/60 rounded-lg p-3">
      <div className="flex items-center justify-between mb-2">
        <div className="flex items-center gap-1.5">
          <span className="text-xs font-semibold text-slate-200">{f.field_key}</span>
          <span className={`text-[10px] px-1.5 py-0.5 rounded font-medium ${f.side === 'back' ? 'bg-indigo-900 text-indigo-300' : 'bg-primary-900 text-primary-300'}`}>
            {f.side === 'back' ? 'Back' : 'Front'}
          </span>
        </div>
        <button type="button" onClick={() => onRemove(f.id)} className="text-xs text-red-400 hover:text-red-300">Remove</button>
      </div>

      <div className="grid grid-cols-4 gap-1.5 text-xs mb-1.5">
        {num('X px', 'x', 0)}
        {num('Y px', 'y', 0)}
        {num('W px', 'w', 1)}
        {num('H px', 'h', 1)}
      </div>

      <div className="grid grid-cols-2 gap-1.5 text-xs mb-1.5">
        <div>
          <label className="block text-slate-500 mb-0.5 text-[10px]">Corner Radius px</label>
          <input type="number" min={0} className={inputCls} value={f.border_radius}
            onChange={e => onUpdate(f.id, 'border_radius', +e.target.value)} />
        </div>
      </div>

      {isText && (
        <div className="grid grid-cols-2 gap-1.5 text-xs mb-1.5">
          <div>
            <label className="block text-slate-500 mb-0.5 text-[10px]">Font</label>
            <select className={inputCls} value={f.font_family} onChange={e => onUpdate(f.id, 'font_family', e.target.value)}>
              {['Inter', 'NotoSansEthiopic', 'Roboto'].map(o => <option key={o}>{o}</option>)}
            </select>
          </div>
          <div>
            <label className="block text-slate-500 mb-0.5 text-[10px]">Size px</label>
            <input type="number" min={6} className={inputCls} value={f.font_size}
              onChange={e => onUpdate(f.id, 'font_size', +e.target.value)} />
          </div>
          <div>
            <label className="block text-slate-500 mb-0.5 text-[10px]">Color</label>
            <input type="color" className="w-full h-8 rounded cursor-pointer bg-slate-950 border border-slate-700" value={f.font_color}
              onChange={e => onUpdate(f.id, 'font_color', e.target.value)} />
          </div>
          <div>
            <label className="block text-slate-500 mb-0.5 text-[10px]">Align</label>
            <select className={inputCls} value={f.text_align} onChange={e => onUpdate(f.id, 'text_align', e.target.value)}>
              {['left', 'center', 'right'].map(o => <option key={o}>{o}</option>)}
            </select>
          </div>
        </div>
      )}

      {isCust && (
        <div className="text-xs mt-0.5">
          <label className="block text-slate-500 mb-0.5 text-[10px]">Custom text</label>
          <input type="text" className={inputCls} value={f.custom_value}
            onChange={e => onUpdate(f.id, 'custom_value', e.target.value)} />
        </div>
      )}
    </div>
  );
}

// ── Main editor page ───────────────────────────────────────────────────────

export default function CardTemplatesEdit({
  school, template, fieldLabels, savedFields,
  canvasW: initCanvasW, canvasH: initCanvasH,
  backW: initBackW, backH: initBackH,
  frontUrl: initFrontUrl, backUrl: initBackUrl,
  uploadUrl, saveUrl, schoolUrl,
}: Props) {
  const [fields, setFields] = useState<Field[]>(() =>
    savedFields.map((f, i) => ({ ...f, id: i, side: f.side ?? 'front', border_radius: f.border_radius ?? 0 }))
  );
  const nextId = useRef(savedFields.length);
  const [canvasW, setCanvasW] = useState(initCanvasW);
  const [canvasH, setCanvasH] = useState(initCanvasH);
  const [backCanvasW, setBackCanvasW] = useState(initBackW);
  const [backCanvasH, setBackCanvasH] = useState(initBackH);
  const [frontUrl, setFrontUrl] = useState(initFrontUrl);
  const [backUrl, setBackUrl] = useState(initBackUrl);
  const [addSide, setAddSide] = useState<'front' | 'back'>('front');
  const [addKey, setAddKey] = useState('');
  const [templateName, setTemplateName] = useState(template?.version ? '' : '');
  const [saveStatus, setSaveStatus] = useState('');
  const [saveStatusType, setSaveStatusType] = useState<'ok' | 'err'>('ok');
  const [uploadMsg, setUploadMsg] = useState<{ text: string; type: 'info' | 'success' | 'error' } | null>(null);
  const [frontStatus, setFrontStatus] = useState('');
  const [backStatus, setBackStatus] = useState('');

  // Drag tracking (using refs so mouse handlers don't stale-close over fields)
  const dragging = useRef<{ id: number; startX: number; startY: number; origX: number; origY: number; scale: number } | null>(null);
  const fieldsRef = useRef(fields);
  useEffect(() => { fieldsRef.current = fields; }, [fields]);

  const frontScale = PREVIEW_W / canvasW;
  const backScale  = PREVIEW_W / backCanvasW;

  // ── Drag handlers ──────────────────────────────────────────────────────

  const startDrag = useCallback((e: React.MouseEvent, id: number) => {
    e.preventDefault();
    const f = fieldsRef.current.find(x => x.id === id);
    if (!f) return;
    const scale = f.side === 'back' ? PREVIEW_W / backCanvasW : PREVIEW_W / canvasW;
    dragging.current = { id, startX: e.clientX, startY: e.clientY, origX: f.x, origY: f.y, scale };
  }, [backCanvasW, canvasW]);

  useEffect(() => {
    function onMove(e: MouseEvent) {
      if (!dragging.current) return;
      const { id, startX, startY, origX, origY, scale } = dragging.current;
      const nx = Math.round(Math.max(0, origX + (e.clientX - startX) / scale));
      const ny = Math.round(Math.max(0, origY + (e.clientY - startY) / scale));
      setFields(prev => prev.map(f => f.id === id ? { ...f, x: nx, y: ny } : f));
    }
    function onUp() { dragging.current = null; }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
    return () => { window.removeEventListener('mousemove', onMove); window.removeEventListener('mouseup', onUp); };
  }, []);

  // ── Field operations ───────────────────────────────────────────────────

  const addField = () => {
    if (!addKey) return;
    const isImage = addKey === 'photo' || addKey === 'qr_code';
    const cw = addSide === 'back' ? backCanvasW : canvasW;
    const ch = addSide === 'back' ? backCanvasH : canvasH;
    const w = isImage ? Math.round(cw * 0.1) : Math.round(cw * 0.3);
    const h = isImage ? Math.round(ch * 0.18) : Math.round(ch * 0.07);
    const f: Field = {
      id: nextId.current++,
      field_key: addKey, side: addSide,
      x: 50, y: 50, w, h,
      font_family: 'Inter', font_size: 14, font_color: '#000000',
      text_align: 'left', font_weight: 'normal', custom_value: '', border_radius: 0,
    };
    setFields(prev => [...prev, f]);
    setAddKey('');
  };

  const updateField = (id: number, key: string, value: number | string) => {
    setFields(prev => prev.map(f => f.id === id ? { ...f, [key]: value } : f));
  };

  const removeField = (id: number) => {
    setFields(prev => prev.filter(f => f.id !== id));
  };

  // ── Final-card preview via the shared renderer (real data + live QR) ──────
  const [showFinal, setShowFinal] = useState(true);
  const [sampleQr, setSampleQr] = useState('');
  useEffect(() => {
    QRCode.toString(`${SAMPLE_DATA.uid}|DEMO-TOKEN`, { type: 'svg', margin: 0, color: { dark: '#0f172a', light: '#ffffff' } })
      .then(setSampleQr).catch(() => setSampleQr(''));
  }, []);

  const sampleAvatar = useMemo(() => {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="190" height="240" viewBox="0 0 190 240"><rect width="190" height="240" fill="#dbeafe"/><text x="95" y="138" font-family="Outfit,Arial,sans-serif" font-size="86" font-weight="700" fill="#1e3a5f" text-anchor="middle">KB</text></svg>`;
    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
  }, []);

  const previewValues: TemplateValues = {
    full_name: SAMPLE_DATA.full_name,
    full_name_am: SAMPLE_DATA.full_name_am,
    uid: SAMPLE_DATA.uid,
    class_name: SAMPLE_DATA.grade_class,
    section: SAMPLE_DATA.section,
    academic_year: SAMPLE_DATA.academic_year,
    school_name: school.name,
    photo_url: sampleAvatar,
    qr_svg: sampleQr,
  };

  const previewTemplate: CardTemplateData = {
    frontUrl, backUrl,
    canvasW, canvasH, backW: backCanvasW, backH: backCanvasH,
    fields: fields.map((f): TemplateField => ({
      field_type: f.field_key === 'photo' ? 'image' : f.field_key === 'qr_code' ? 'qr' : 'text',
      field_key: f.field_key,
      side: f.side,
      x: f.x, y: f.y, w: f.w, h: f.h,
      font_size: f.font_size, font_color: f.font_color, font_family: f.font_family,
      text_align: f.text_align, font_weight: f.font_weight,
      border_radius: f.border_radius, custom_value: f.custom_value,
    })),
  };

  // ── Upload ─────────────────────────────────────────────────────────────

  const uploadImage = async (side: 'front' | 'back', file: File) => {
    setUploadMsg({ text: `Uploading ${side} image…`, type: 'info' });
    const fd = new FormData();
    fd.append('side', side);
    fd.append('image', file);
    try {
      const r = await fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '' }, body: fd });
      if (!r.ok) { const t = await r.text(); throw new Error('Server ' + r.status + ': ' + t.slice(0, 200)); }
      const data = await r.json();
      if (!data.success) { setUploadMsg({ text: 'Upload failed: ' + (data.error ?? 'unknown'), type: 'error' }); return; }
      if (side === 'front') {
        setFrontStatus(`${data.width}×${data.height} px`);
        setFrontUrl(data.url + '?t=' + Date.now());
        setCanvasW(data.width);
        setCanvasH(data.height);
        setUploadMsg({ text: `Front image uploaded (${data.width}×${data.height} px). Canvas updated.`, type: 'success' });
      } else {
        setBackStatus(`${data.width}×${data.height} px`);
        setBackUrl(data.url + '?t=' + Date.now());
        setBackCanvasW(data.width);
        setBackCanvasH(data.height);
        setUploadMsg({ text: `Back image uploaded (${data.width}×${data.height} px).`, type: 'success' });
      }
      // auto-save silently
      doSave(true);
    } catch (err: any) {
      setUploadMsg({ text: 'Upload error: ' + err.message, type: 'error' });
      if (side === 'front') setFrontStatus('Error');
      else setBackStatus('Error');
    }
  };

  // ── Save ───────────────────────────────────────────────────────────────

  const doSave = async (silent = false) => {
    const payload = JSON.stringify(
      fields.map(({ field_key, x, y, w, h, font_family, font_size, font_color, text_align, font_weight, custom_value, side, border_radius }) =>
        ({ field_key, x, y, w, h, font_family, font_size, font_color, text_align, font_weight, custom_value, side, border_radius }))
    );
    const fd = new FormData();
    fd.append('_token', (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '');
    fd.append('fields', payload);
    fd.append('canvas_w', String(canvasW));
    fd.append('canvas_h', String(canvasH));
    fd.append('name', templateName);
    try {
      const r = await fetch(saveUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '' }, body: fd });
      const data = await r.json();
      if (!silent) {
        setSaveStatusType(data.success ? 'ok' : 'err');
        setSaveStatus(data.success ? `✓ Saved (v${data.version})` : `✗ ${data.message}`);
        setTimeout(() => setSaveStatus(''), 3000);
      }
    } catch {
      if (!silent) { setSaveStatusType('err'); setSaveStatus('Save failed — check connection.'); setTimeout(() => setSaveStatus(''), 3000); }
    }
  };

  // ── Canvas panel ───────────────────────────────────────────────────────

  const CanvasPanel = ({ side }: { side: 'front' | 'back' }) => {
    const isFront = side === 'front';
    const cw = isFront ? canvasW : backCanvasW;
    const ch = isFront ? canvasH : backCanvasH;
    const scale = PREVIEW_W / cw;
    const dispH = Math.round(ch * scale);
    const url = isFront ? frontUrl : backUrl;
    const status = isFront ? frontStatus : backStatus;
    const fileRef = useRef<HTMLInputElement>(null);

    return (
      <div className="flex-1 min-w-0">
        <div className="bg-dark-800 border border-slate-700/50 rounded-xl p-4">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-2">
              <span className={`w-2 h-2 rounded-full inline-block ${isFront ? 'bg-primary-500' : 'bg-slate-500'}`} />
              <span className="text-xs font-semibold text-slate-300 uppercase tracking-wide">{isFront ? 'Front' : 'Back'}</span>
              <span className="text-xs text-slate-500">drag fields to position</span>
            </div>
            <span className="text-xs text-slate-500">{cw}×{ch} px</span>
          </div>
          <div className="overflow-hidden rounded-lg bg-slate-950 relative mx-auto"
            style={{ width: PREVIEW_W, height: dispH }}>
            <div style={{ position: 'absolute', top: 0, left: 0, transformOrigin: 'top left', overflow: 'hidden', width: cw, height: ch, transform: `scale(${scale.toFixed(4)})` }}>
              {url
                ? <img alt="" src={url} style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', objectFit: 'fill' }} />
                : (
                  <div style={{ position: 'absolute', inset: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 8, color: 'rgba(255,255,255,.25)', fontSize: 13 }}>
                    <svg width="32" height="32" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 20M6 8h.01M4 6h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V8a2 2 0 012-2z" />
                    </svg>
                    Upload a {isFront ? 'front' : 'back'} image {isFront ? 'to begin' : ''}
                  </div>
                )}
              {fields.filter(f => f.side === side).map(f => (
                <FieldOverlay key={f.id} f={f} scale={scale} onMouseDown={e => startDrag(e, f.id)} />
              ))}
            </div>
          </div>
        </div>
      </div>
    );
  };

  // ── Upload bar ─────────────────────────────────────────────────────────

  const frontFileRef = useRef<HTMLInputElement>(null);
  const backFileRef  = useRef<HTMLInputElement>(null);

  const uploadMsgCls = uploadMsg?.type === 'error'
    ? 'mt-3 px-3 py-2 rounded-lg text-sm bg-red-500/10 border border-red-500/30 text-red-400'
    : uploadMsg?.type === 'success'
    ? 'mt-3 px-3 py-2 rounded-lg text-sm bg-green-500/10 border border-green-500/30 text-green-400'
    : 'mt-3 px-3 py-2 rounded-lg text-sm bg-blue-500/10 border border-blue-500/30 text-blue-400';

  return (
    <AdminLayout>
      <Head title={`${school.name} — Card Template`} />
      <PageHeader title="Card Template Editor" subtitle={school.name} />
    <div>
      {/* Back link */}
      <div className="mb-4 flex items-center gap-3">
        <a href={schoolUrl} className="text-sm text-slate-400 hover:text-slate-200">← Back to School</a>
        {template && (
          <>
            <span className="text-xs text-slate-600">|</span>
            <span className="text-xs text-slate-500">v{template.version} · {template.field_count} field(s) · {canvasW}×{canvasH} px</span>
          </>
        )}
      </div>

      {/* Upload bar */}
      <div className="bg-dark-800 border border-slate-700/50 rounded-xl p-4 mb-5">
        <div className="flex flex-wrap items-center gap-6">
          <div className="flex items-center gap-3">
            <span className="text-xs text-slate-400 uppercase tracking-wide font-medium">Front</span>
            <input ref={frontFileRef} type="file" accept=".png,.jpg,.jpeg" className="hidden"
              onChange={e => { if (e.target.files?.[0]) uploadImage('front', e.target.files[0]); e.target.value = ''; }} />
            <button type="button" onClick={() => frontFileRef.current?.click()}
              className="border border-slate-600 hover:border-primary-500 text-slate-300 text-sm px-3 py-1.5 rounded-lg transition">
              Upload Image
            </button>
            <span className="text-xs text-slate-500">{frontStatus}</span>
          </div>
          <div className="flex items-center gap-3">
            <span className="text-xs text-slate-400 uppercase tracking-wide font-medium">Back</span>
            <input ref={backFileRef} type="file" accept=".png,.jpg,.jpeg" className="hidden"
              onChange={e => { if (e.target.files?.[0]) uploadImage('back', e.target.files[0]); e.target.value = ''; }} />
            <button type="button" onClick={() => backFileRef.current?.click()}
              className="border border-slate-600 hover:border-primary-500 text-slate-300 text-sm px-3 py-1.5 rounded-lg transition">
              Upload Image
            </button>
            <span className="text-xs text-slate-500">{backStatus}</span>
          </div>
          <div className="flex-1 text-right text-xs text-slate-600">
            Any size &amp; orientation — portrait or landscape. Uploaded image dimensions set the canvas.
          </div>
        </div>
        {uploadMsg && <div className={uploadMsgCls}>{uploadMsg.text}</div>}
      </div>

      {/* Three-panel editor */}
      <div className="flex gap-4 items-start">
        <CanvasPanel side="front" />
        <CanvasPanel side="back" />

        {/* Field config panel */}
        <div style={{ width: 290, flexShrink: 0 }}>
          <div className="bg-dark-800 border border-slate-700/50 rounded-xl p-4 flex flex-col gap-3">

            {/* Side toggle + field picker */}
            <div>
              <p className="text-xs text-slate-500 mb-1.5 font-medium">Add to side:</p>
              <div className="flex items-center gap-1 mb-2">
                <button type="button" onClick={() => setAddSide('front')}
                  className={`flex-1 text-xs py-1.5 rounded-md font-medium transition ${addSide === 'front' ? 'bg-primary-600 text-white' : 'bg-slate-900 border border-slate-700 text-slate-400 hover:text-white'}`}>
                  ↑ Front
                </button>
                <button type="button" onClick={() => setAddSide('back')}
                  className={`flex-1 text-xs py-1.5 rounded-md font-medium transition ${addSide === 'back' ? 'bg-indigo-600 text-white' : 'bg-slate-900 border border-slate-700 text-slate-400 hover:text-white'}`}>
                  ↓ Back
                </button>
              </div>
              <div className="flex items-center gap-2">
                <select value={addKey} onChange={e => setAddKey(e.target.value)}
                  className="flex-1 bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-primary-500">
                  <option value="">— select field —</option>
                  {Object.entries(fieldLabels).map(([key, label]) => (
                    <option key={key} value={key}>{label}</option>
                  ))}
                </select>
                <button type="button" onClick={addField}
                  className="bg-primary-600 hover:bg-primary-700 text-white text-sm px-4 py-2 rounded-lg whitespace-nowrap transition">
                  + Add
                </button>
              </div>
            </div>

            {/* Field list */}
            <div className="flex flex-col gap-2.5 max-h-[480px] overflow-y-auto pr-1">
              {fields.map(f => (
                <FieldCard key={f.id} f={f} onUpdate={updateField} onRemove={removeField} />
              ))}
            </div>

            {/* Save */}
            <div className="pt-3 border-t border-slate-700/50">
              <input type="text" placeholder="Template name (optional)" value={templateName}
                onChange={e => setTemplateName(e.target.value)}
                className="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-primary-500 mb-2" />
              <button type="button" onClick={() => doSave(false)}
                className="w-full bg-green-600 hover:bg-green-700 text-white text-sm px-5 py-2 rounded-lg font-medium transition">
                Save Template
              </button>
              {saveStatus && (
                <p className={`text-xs mt-1.5 text-center ${saveStatusType === 'ok' ? 'text-green-400' : 'text-red-400'}`}>
                  {saveStatus}
                </p>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Final card preview — the exact shared renderer schools and the public page use */}
      <div className="mt-6 bg-dark-800 border border-slate-700/50 rounded-xl p-4">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <span className="text-xs font-semibold text-slate-300 uppercase tracking-wide">Final card preview</span>
            <span className="text-xs text-slate-500">how schools &amp; the public page render it — real data + live QR</span>
          </div>
          <button type="button" onClick={() => setShowFinal(v => !v)} className="text-xs text-slate-400 hover:text-slate-200">
            {showFinal ? 'Hide' : 'Show'}
          </button>
        </div>
        {showFinal && (frontUrl ? (
          <div className="flex flex-wrap gap-6 justify-center py-3">
            <div className="flex flex-col items-center gap-2">
              <TemplateCard template={previewTemplate} values={previewValues} displayWidth={360} side="front" preview={false} />
              <span className="text-[11px] text-slate-500">Front</span>
            </div>
            {backUrl && (
              <div className="flex flex-col items-center gap-2">
                <TemplateCard template={previewTemplate} values={previewValues} displayWidth={360} side="back" preview={false} />
                <span className="text-[11px] text-slate-500">Back</span>
              </div>
            )}
          </div>
        ) : (
          <p className="text-xs text-slate-500 py-6 text-center">Upload a front image to see the final preview.</p>
        ))}
      </div>
    </div>
    </AdminLayout>
  );
}
