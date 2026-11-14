import React, { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    FileText, Upload, Trash2, ShieldCheck, ShieldOff, X, CalendarClock, AlertTriangle, ArrowRight,
} from 'lucide-react';

export interface StaffDoc {
    id: number;
    type: string;
    type_label: string;
    title: string;
    description: string | null;
    file_name: string | null;
    file_size: string;
    is_pdf: boolean;
    is_image: boolean;
    issued_on: string | null;
    expires_on: string | null;
    expiry_state: 'expired' | 'expiring' | 'valid' | 'none';
    days_left: number | null;
    is_expired: boolean;
    is_verified: boolean;
    uploaded_by: string | null;
}

interface Props {
    staffId: number;
    documents: StaffDoc[];
    types: { value: string; label: string }[];
    isAdmin: boolean;
    accent: string;
    warnDays: number;
}

const STATE_STYLES: Record<string, { bg: string; text: string; label: (d: number | null) => string }> = {
    expired:  { bg: '#fee2e2', text: '#b91c1c', label: () => 'Expired' },
    expiring: { bg: '#fef3c7', text: '#b45309', label: d => d === 0 ? 'Expires today' : `${d} days left` },
    valid:    { bg: '#dcfce7', text: '#15803d', label: () => 'Valid' },
    none:     { bg: '#f1f5f9', text: '#64748b', label: () => 'No expiry' },
};

export default function StaffDocumentPanel({ staffId, documents, types, isAdmin, accent, warnDays }: Props) {
    const fileRef = useRef<HTMLInputElement>(null);

    const [open, setOpen] = useState(false);
    const [type, setType] = useState('contract');
    const [title, setTitle] = useState('');
    const [issuedOn, setIssuedOn] = useState('');
    const [expiresOn, setExpiresOn] = useState('');
    const [note, setNote] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [busy, setBusy] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    function reset() {
        setType('contract'); setTitle(''); setIssuedOn(''); setExpiresOn('');
        setNote(''); setFile(null); setErrors({});
        if (fileRef.current) fileRef.current.value = '';
    }

    function upload() {
        if (!file) {
            setErrors({ file: 'Choose a file first.' });
            return;
        }
        setBusy(true);

        const data = new FormData();
        data.append('type', type);
        data.append('title', title || file.name);
        if (issuedOn) data.append('issued_on', issuedOn);
        if (expiresOn) data.append('expires_on', expiresOn);
        if (note) data.append('description', note);
        data.append('file', file);

        router.post(`/school/staff/${staffId}/documents`, data as never, {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => setErrors(e as Record<string, string>),
            onSuccess: () => { setOpen(false); reset(); },
            onFinish: () => setBusy(false),
        });
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:ring-2 bg-white';
    const ring = { ['--tw-ring-color' as string]: `${accent}59` };

    const urgent = documents.filter(d => d.expiry_state === 'expired' || d.expiry_state === 'expiring');

    return (
        <div className="bg-white rounded-3xl border border-slate-200/60 p-6"
            style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>

            <div className="flex items-center justify-between mb-4">
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>Documents</p>
                    <p className="text-xs text-slate-400 mt-0.5">
                        {documents.length === 0
                            ? 'No paperwork on file'
                            : `${documents.length} on file${urgent.length ? ` · ${urgent.length} need attention` : ''}`}
                    </p>
                </div>
                {isAdmin && (
                    <button onClick={() => setOpen(v => !v)}
                        className="flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl text-white"
                        style={{ background: accent }}>
                        <Upload className="w-3.5 h-3.5" /> Upload
                    </button>
                )}
            </div>

            {/* Upload form */}
            {open && isAdmin && (
                <div className="mb-5 p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <div className="flex items-center justify-between mb-3">
                        <p className="text-xs font-bold text-slate-700">File a document</p>
                        <button onClick={() => { setOpen(false); reset(); }} className="text-slate-400 hover:text-slate-700">
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div>
                            <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Type</label>
                            <select value={type} onChange={e => setType(e.target.value)} className={inputCls} style={ring}>
                                {types.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Title</label>
                            <input value={title} onChange={e => setTitle(e.target.value)}
                                placeholder="e.g. Contract 2026–2027" className={inputCls} style={ring} />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Issued on</label>
                            <input type="date" value={issuedOn} onChange={e => setIssuedOn(e.target.value)} className={inputCls} style={ring} />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">
                                Expires on <span className="text-slate-300 normal-case font-normal">(optional)</span>
                            </label>
                            <input type="date" value={expiresOn} onChange={e => setExpiresOn(e.target.value)} className={inputCls} style={ring} />
                        </div>
                    </div>

                    <div className="mt-2.5">
                        <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">File</label>
                        <input ref={fileRef} type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                            onChange={e => setFile(e.target.files?.[0] ?? null)}
                            className="w-full text-xs file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:text-white file:cursor-pointer"
                            style={{ ['--tw-file-bg' as string]: accent, color: '#64748b' }} />
                        {errors.file && <p className="text-[11px] text-red-600 mt-1">{errors.file}</p>}
                        <p className="text-[10px] text-slate-400 mt-1">PDF, JPG, PNG or WEBP · up to 10 MB</p>
                    </div>

                    <div className="mt-2.5">
                        <label className="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Note (optional)</label>
                        <input value={note} onChange={e => setNote(e.target.value)} className={inputCls} style={ring} />
                    </div>

                    <button onClick={upload} disabled={busy}
                        className="mt-3 px-4 py-2 rounded-2xl text-xs font-bold text-white disabled:opacity-50"
                        style={{ background: accent }}>
                        {busy ? 'Uploading…' : 'Save document'}
                    </button>
                </div>
            )}

            {/* List */}
            {documents.length === 0 ? (
                <div className="py-8 text-center">
                    <FileText className="w-8 h-8 text-slate-200 mx-auto mb-2" />
                    <p className="text-xs text-slate-500 font-semibold">Nothing on file</p>
                    <p className="text-[11px] text-slate-400 mt-0.5">
                        Contracts, licences and clearances live here.
                    </p>
                </div>
            ) : (
                <div className="divide-y divide-slate-50">
                    {documents.map(d => {
                        const st = STATE_STYLES[d.expiry_state] ?? STATE_STYLES.none;
                        return (
                            <div key={d.id} className="flex items-start gap-3 py-3">
                                <div className="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0 bg-slate-100 text-slate-400">
                                    <FileText className="w-4 h-4" />
                                </div>

                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <p className="text-sm font-bold text-slate-800 truncate">{d.title}</p>
                                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                            style={{ background: st.bg, color: st.text }}>
                                            {st.label(d.days_left)}
                                        </span>
                                        {d.is_verified && (
                                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-50 text-blue-600">
                                                Verified
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-[11px] text-slate-400 truncate">
                                        {d.type_label} · {d.file_size}
                                        {d.expires_on ? ` · expires ${d.expires_on}` : ''}
                                    </p>
                                </div>

                                <div className="flex items-center gap-1 flex-shrink-0">
                                    <a href={`/school/staff/${staffId}/documents/${d.id}/download`}
                                        className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100"
                                        title="Download">
                                        <ArrowRight className="w-3.5 h-3.5" />
                                    </a>
                                    {isAdmin && (
                                        <>
                                            <button onClick={() => router.post(`/school/staff/${staffId}/documents/${d.id}/verify`, {} as never, { preserveScroll: true })}
                                                className="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50"
                                                title={d.is_verified ? 'Remove verification' : 'Mark verified'}>
                                                {d.is_verified ? <ShieldOff className="w-3.5 h-3.5" /> : <ShieldCheck className="w-3.5 h-3.5" />}
                                            </button>
                                            <button onClick={() => {
                                                if (confirm(`Remove "${d.title}"? The file is deleted; the audit entry stays.`)) {
                                                    router.delete(`/school/staff/${staffId}/documents/${d.id}`, { preserveScroll: true });
                                                }
                                            }}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-red-600 hover:bg-red-50"
                                                title="Delete">
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </button>
                                        </>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
