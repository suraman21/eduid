import React, { useEffect, useRef, useState } from 'react';

/**
 * Crops/zooms/pans a chosen image INTO a fixed CR80 frame, then bakes the
 * result to a CR80-aspect PNG (at the canvas pixel resolution) so the stored
 * background can never distort at print time. The frame aspect never changes —
 * only what part of the image fills it.
 */
export default function CropModal({
    file, side, canvasW, canvasH, onCancel, onApply,
}: {
    file: File;
    side: 'front' | 'back';
    canvasW: number;
    canvasH: number;
    onCancel: () => void;
    onApply: (blob: Blob) => void;
}) {
    const frameW = 380;
    const frameH = Math.round(frameW * (canvasH / canvasW));

    const imgRef = useRef<HTMLImageElement | null>(null);
    const [url, setUrl] = useState('');
    const [nat, setNat] = useState<{ w: number; h: number } | null>(null);
    const [error, setError] = useState('');
    const [zoom, setZoom] = useState(1);
    const [tx, setTx] = useState(0);
    const [ty, setTy] = useState(0);
    const drag = useRef<{ x: number; y: number; tx: number; ty: number } | null>(null);

    useEffect(() => {
        const u = URL.createObjectURL(file);
        setUrl(u);
        const im = new Image();
        im.onload = () => { setNat({ w: im.naturalWidth, h: im.naturalHeight }); imgRef.current = im; };
        im.onerror = () => setError('Could not load this image. Use a PNG or JPG under 10 MB.');
        im.src = u;
        return () => URL.revokeObjectURL(u);
    }, [file]);

    if (error) {
        return (
            <Overlay>
                <div className="bg-dark-800 border border-slate-700 rounded-xl p-5 w-[380px] text-center">
                    <p className="text-sm text-red-400 mb-3">{error}</p>
                    <button onClick={onCancel} className="px-4 py-2 text-sm bg-slate-700 hover:bg-slate-600 text-white rounded-lg">Close</button>
                </div>
            </Overlay>
        );
    }

    if (!nat) {
        return <Overlay><p className="text-slate-300 text-sm">Loading image…</p></Overlay>;
    }

    const coverScale = Math.max(frameW / nat.w, frameH / nat.h);
    const dw = nat.w * coverScale * zoom;
    const dh = nat.h * coverScale * zoom;
    const maxX = Math.max(0, (dw - frameW) / 2);
    const maxY = Math.max(0, (dh - frameH) / 2);
    const cx = Math.min(maxX, Math.max(-maxX, tx));
    const cy = Math.min(maxY, Math.max(-maxY, ty));
    const left = (frameW - dw) / 2 + cx;
    const top = (frameH - dh) / 2 + cy;

    function onZoom(z: number) {
        setZoom(z);
        // re-clamp pan against the new size
        const ndw = nat!.w * coverScale * z;
        const ndh = nat!.h * coverScale * z;
        const mx = Math.max(0, (ndw - frameW) / 2);
        const my = Math.max(0, (ndh - frameH) / 2);
        setTx(t => Math.min(mx, Math.max(-mx, t)));
        setTy(t => Math.min(my, Math.max(-my, t)));
    }

    function onDown(e: React.MouseEvent) { drag.current = { x: e.clientX, y: e.clientY, tx: cx, ty: cy }; }
    useEffect(() => {
        function move(e: MouseEvent) {
            if (!drag.current) return;
            setTx(drag.current.tx + (e.clientX - drag.current.x));
            setTy(drag.current.ty + (e.clientY - drag.current.y));
        }
        function up() { drag.current = null; }
        window.addEventListener('mousemove', move);
        window.addEventListener('mouseup', up);
        return () => { window.removeEventListener('mousemove', move); window.removeEventListener('mouseup', up); };
    }, []);

    function bake() {
        const c = document.createElement('canvas');
        c.width = canvasW; c.height = canvasH;
        const ctx = c.getContext('2d');
        if (!ctx || !imgRef.current) return;
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvasW, canvasH);
        const s = canvasW / frameW; // frame px → canvas px
        ctx.drawImage(imgRef.current, left * s, top * s, dw * s, dh * s);
        c.toBlob(b => { if (b) onApply(b); }, 'image/png');
    }

    return (
        <Overlay>
            <div className="bg-dark-800 border border-slate-700 rounded-xl p-5 w-[440px]">
                <p className="text-sm font-semibold text-slate-200 mb-1">Crop {side} image to CR80</p>
                <p className="text-xs text-slate-500 mb-3">Drag to reposition · zoom to fill. The card aspect is locked to CR80 — the image is cropped, never stretched.</p>

                <div
                    className="relative mx-auto overflow-hidden rounded-lg bg-slate-950 cursor-move select-none"
                    style={{ width: frameW, height: frameH }}
                    onMouseDown={onDown}
                >
                    {url && <img src={url} alt="" draggable={false} style={{ position: 'absolute', left, top, width: dw, height: dh, maxWidth: 'none' }} />}
                    <div className="pointer-events-none absolute inset-0 border-2 border-primary-500/60 rounded-lg" />
                </div>

                <div className="flex items-center gap-2 mt-3">
                    <span className="text-xs text-slate-500">Zoom</span>
                    <input type="range" min={1} max={4} step={0.01} value={zoom} onChange={e => onZoom(+e.target.value)} className="flex-1 accent-primary-500" />
                </div>

                <div className="flex justify-end gap-2 mt-4">
                    <button onClick={onCancel} className="px-4 py-2 text-sm text-slate-400 hover:text-slate-200">Cancel</button>
                    <button onClick={bake} className="px-4 py-2 text-sm font-medium bg-primary-600 hover:bg-primary-700 text-white rounded-lg">Apply crop</button>
                </div>
            </div>
        </Overlay>
    );
}

function Overlay({ children }: { children: React.ReactNode }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
            {children}
        </div>
    );
}
