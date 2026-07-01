import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { Printer as PrinterIcon, Download, Trash2 } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';

interface Printer {
    id: number;
    name: string;
    x_offset_front_mm: number;
    y_offset_front_mm: number;
    x_offset_back_mm: number;
    y_offset_back_mm: number;
    rotation_offset_deg: number;
    mirror_back: boolean;
    flip_mode: 'long_edge' | 'short_edge';
    is_default: boolean;
}

interface Props {
    printers: Printer[];
    sheets: { id: number; name: string }[];
}

const EMPTY = {
    name: '',
    x_offset_front_mm: 0, y_offset_front_mm: 0,
    x_offset_back_mm: 0, y_offset_back_mm: 0,
    rotation_offset_deg: 0, mirror_back: false as boolean,
    flip_mode: 'long_edge' as 'long_edge' | 'short_edge',
    is_default: false as boolean,
};

export default function Index({ printers, sheets }: Props) {
    const [editing, setEditing] = useState<number | 'new' | null>(printers.length === 0 ? 'new' : null);
    const form = useForm({ ...EMPTY });

    function startEdit(p: Printer) {
        form.setData({
            name: p.name,
            x_offset_front_mm: p.x_offset_front_mm, y_offset_front_mm: p.y_offset_front_mm,
            x_offset_back_mm: p.x_offset_back_mm, y_offset_back_mm: p.y_offset_back_mm,
            rotation_offset_deg: p.rotation_offset_deg, mirror_back: p.mirror_back,
            flip_mode: p.flip_mode, is_default: p.is_default,
        });
        setEditing(p.id);
    }

    function startNew() {
        form.setData({ ...EMPTY });
        setEditing('new');
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (editing === 'new') {
            form.post('/admin/printers', { onSuccess: () => setEditing(null) });
        } else if (typeof editing === 'number') {
            form.patch(`/admin/printers/${editing}`, { onSuccess: () => setEditing(null) });
        }
    }

    function remove(p: Printer) {
        if (!window.confirm(`Remove printer "${p.name}"?`)) return;
        router.delete(`/admin/printers/${p.id}`);
    }

    const num = (label: string, key: keyof typeof EMPTY, step = 0.1) => (
        <div>
            <label className="block text-[10px] text-slate-500 mb-0.5 uppercase">{label}</label>
            <input
                type="number" step={step}
                value={form.data[key] as number}
                onChange={e => form.setData(key, Number(e.target.value) as never)}
                className="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5 text-sm text-slate-200 focus:outline-none focus:border-purple-500"
            />
        </div>
    );

    return (
        <AdminLayout>
            <Head title="Printers & Calibration" />
            <PageHeader
                title="Printers & Calibration"
                subtitle="Each physical printer feeds paper differently — its profile stores the measured front/back offsets so generated sheets compensate automatically."
                actions={
                    <button onClick={startNew} className="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white text-sm font-medium px-4 py-2 rounded-lg">
                        + Add Printer
                    </button>
                }
            />

            {/* Calibration how-to */}
            <div className="bg-white/[0.025] border border-white/10 rounded-xl p-5 mb-6 max-w-3xl">
                <p className="text-slate-200 font-medium mb-2">Calibration wizard</p>
                <ol className="text-xs text-slate-400 space-y-1 list-decimal list-inside">
                    <li>Download & print the <strong className="text-slate-300">FRONT calibration sheet</strong> at 100% (Actual size).</li>
                    <li>Reinsert the same sheet per your printer's flip mode, then print the <strong className="text-slate-300">BACK calibration sheet</strong>.</li>
                    <li>Hold the sheet up to the light. Measure how far the back crosses sit from the front crosses (in mm).</li>
                    <li>Enter the shift as the <strong className="text-slate-300">back offsets</strong>: back printed 1.2mm too far LEFT → X offset back = <code className="text-purple-300">+1.2</code>; 0.8mm too HIGH → Y offset back = <code className="text-purple-300">+0.8</code>.</li>
                    <li>Save, re-print the back calibration sheet — the crosses should now align. Every future print compensates automatically.</li>
                </ol>
            </div>

            {/* Printer list */}
            <div className="space-y-3 max-w-3xl">
                {printers.map(p => (
                    <div key={p.id} className="bg-white/[0.025] border border-white/10 rounded-xl p-5">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <PrinterIcon className="w-5 h-5 text-purple-400" />
                                <div>
                                    <p className="text-slate-200 font-medium">
                                        {p.name}
                                        {p.is_default && <span className="ml-2 text-[10px] px-1.5 py-0.5 rounded bg-emerald-600/20 text-emerald-300">default</span>}
                                    </p>
                                    <p className="text-xs text-slate-500">
                                        Front {p.x_offset_front_mm}/{p.y_offset_front_mm}mm · Back {p.x_offset_back_mm}/{p.y_offset_back_mm}mm
                                        · Rot {p.rotation_offset_deg}° · {p.mirror_back ? 'mirrored back · ' : ''}
                                        flip: {p.flip_mode === 'short_edge' ? 'calendar (short edge)' : 'book (long edge)'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <button onClick={() => startEdit(p)} className="text-sm text-slate-300 border border-white/10 hover:border-white/30 px-3 py-1.5 rounded-lg">Calibrate</button>
                                <button onClick={() => remove(p)} className="text-rose-400 hover:text-rose-300 p-1.5"><Trash2 className="w-4 h-4" /></button>
                            </div>
                        </div>

                        {/* Calibration sheet downloads */}
                        <div className="mt-3 pt-3 border-t border-white/5 flex flex-wrap items-center gap-2">
                            <span className="text-[11px] text-slate-500 uppercase tracking-wide mr-1">Calibration sheets</span>
                            {sheets.map(s => (
                                <span key={s.id} className="flex items-center gap-1">
                                    <a href={`/admin/printers/${p.id}/calibration/${s.id}/front`} className="inline-flex items-center gap-1 text-xs text-purple-300 hover:text-purple-200 border border-purple-500/30 rounded-lg px-2.5 py-1">
                                        <Download className="w-3 h-3" /> {s.name} — Front
                                    </a>
                                    <a href={`/admin/printers/${p.id}/calibration/${s.id}/back`} className="inline-flex items-center gap-1 text-xs text-purple-300 hover:text-purple-200 border border-purple-500/30 rounded-lg px-2.5 py-1">
                                        <Download className="w-3 h-3" /> Back
                                    </a>
                                </span>
                            ))}
                        </div>
                    </div>
                ))}
                {printers.length === 0 && (
                    <p className="text-sm text-slate-500">No printers yet — add your first printer (e.g. "Epson L3250") to start calibrating.</p>
                )}
            </div>

            {/* Add / edit form */}
            {editing !== null && (
                <form onSubmit={submit} className="mt-6 bg-white/[0.025] border border-purple-500/30 rounded-xl p-5 max-w-3xl">
                    <p className="text-slate-200 font-medium mb-4">{editing === 'new' ? 'Add printer' : 'Edit calibration'}</p>

                    <div className="grid sm:grid-cols-2 gap-3 mb-3">
                        <div>
                            <label className="block text-[10px] text-slate-500 mb-0.5 uppercase">Printer name</label>
                            <input
                                type="text" value={form.data.name}
                                onChange={e => form.setData('name', e.target.value)}
                                placeholder="Epson L3250"
                                className="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5 text-sm text-slate-200 focus:outline-none focus:border-purple-500"
                            />
                            {form.errors.name && <p className="text-xs text-rose-400 mt-1">{form.errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-[10px] text-slate-500 mb-0.5 uppercase">Flip mode (duplex reinsert)</label>
                            <select
                                value={form.data.flip_mode}
                                onChange={e => form.setData('flip_mode', e.target.value as 'long_edge' | 'short_edge')}
                                className="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-1.5 text-sm text-slate-200 focus:outline-none focus:border-purple-500"
                            >
                                <option value="long_edge">Like a book (long edge)</option>
                                <option value="short_edge">Like a calendar (short edge)</option>
                            </select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                        {num('X offset front (mm)', 'x_offset_front_mm')}
                        {num('Y offset front (mm)', 'y_offset_front_mm')}
                        {num('X offset back (mm)', 'x_offset_back_mm')}
                        {num('Y offset back (mm)', 'y_offset_back_mm')}
                    </div>

                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4 items-end">
                        {num('Rotation offset (°)', 'rotation_offset_deg', 0.05)}
                        <label className="flex items-center gap-2 text-sm text-slate-300 pb-1.5">
                            <input type="checkbox" checked={form.data.mirror_back} onChange={e => form.setData('mirror_back', e.target.checked)} className="accent-purple-500" />
                            Mirror back
                        </label>
                        <label className="flex items-center gap-2 text-sm text-slate-300 pb-1.5">
                            <input type="checkbox" checked={form.data.is_default} onChange={e => form.setData('is_default', e.target.checked)} className="accent-purple-500" />
                            Default printer
                        </label>
                    </div>

                    <div className="flex items-center gap-2">
                        <button type="submit" disabled={form.processing} className="bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg">
                            {form.processing ? 'Saving…' : 'Save calibration'}
                        </button>
                        <button type="button" onClick={() => setEditing(null)} className="text-sm text-slate-400 hover:text-slate-200 px-3 py-2">Cancel</button>
                    </div>
                </form>
            )}
        </AdminLayout>
    );
}
