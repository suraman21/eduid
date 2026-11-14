import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Search, X, Printer, Loader2, AlertCircle } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import CertificateLetter, { normHex, type CertSnapshot } from '@/Components/CertificateLetter';
import axios from 'axios';

interface StudentLite {
    id: number;
    uid: string;
    name: string;
    status: string | null;
    class?: string | null;
}

interface TypeOption {
    value: string;
    label: string;
    hint: string | null;
}

interface Props {
    student: StudentLite | null;
    type: string;
    types: TypeOption[];
    preview: CertSnapshot | null;
    suggested_note: string | null;
    next_reference: string;
    schoolName: string;
    is_admin: boolean;
}

const NOTE_TYPES = ['character', 'custom'];

export default function Create({ student: initialStudent, type: initialType, types, preview: initialPreview, suggested_note, next_reference, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const [student, setStudent] = useState<StudentLite | null>(initialStudent);
    const [type, setType] = useState(initialType);
    const [addressedTo, setAddressedTo] = useState('');
    const [note, setNote] = useState(initialType === 'character' ? (suggested_note || '') : '');
    const [snapshot, setSnapshot] = useState<CertSnapshot | null>(initialPreview);
    const [loading, setLoading] = useState(false);
    const [busy, setBusy] = useState(false);

    // Student picker
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<StudentLite[]>([]);
    const [searching, setSearching] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(!initialStudent);

    // Keep the latest preview request only — stale responses must not win.
    const reqId = useRef(0);

    const refresh = useCallback(async (sid: number, t: string, n: string, addr: string) => {
        const mine = ++reqId.current;
        setLoading(true);
        try {
            const { data } = await axios.post('/school/certificates/preview', {
                student_id: sid,
                type: t,
                body_note: NOTE_TYPES.includes(t) ? (n || null) : null,
                addressed_to: addr || null,
            });
            if (mine === reqId.current) {
                setSnapshot(data.preview);
            }
        } finally {
            if (mine === reqId.current) setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!student) return;
        const timer = setTimeout(() => refresh(student.id, type, note, addressedTo), 320);
        return () => clearTimeout(timer);
    }, [student, type, note, addressedTo, refresh]);

    useEffect(() => {
        if (!pickerOpen) return;
        const timer = setTimeout(async () => {
            setSearching(true);
            try {
                const { data } = await axios.get('/school/certificates/students', { params: { q: query } });
                setResults(data.students);
            } finally {
                setSearching(false);
            }
        }, 250);
        return () => clearTimeout(timer);
    }, [query, pickerOpen]);

    function pick(s: StudentLite) {
        setStudent(s);
        setPickerOpen(false);
        setQuery('');
        setNote(type === 'character' ? (suggested_note || '') : '');
    }

    function issue() {
        if (!student) return;
        setBusy(true);
        router.post('/school/certificates', {
            student_id: student.id,
            type,
            addressed_to: addressedTo || null,
            body_note: NOTE_TYPES.includes(type) ? (note || null) : null,
        } as never, { onFinish: () => setBusy(false) });
    }

    const selectedType = types.find(t => t.value === type);

    return (
        <SchoolLayout>
            <Head title="New certificate" />

            <div className="grid grid-cols-1 lg:grid-cols-[340px_1fr] gap-6 items-start">

                {/* ── Left: the form ── */}
                <div className="print:hidden space-y-4 lg:sticky lg:top-6">
                    <div className="bg-white rounded-3xl border border-slate-200 p-5">
                        <h1 className="text-lg font-extrabold text-slate-900 tracking-tight mb-1">New certificate</h1>
                        <p className="text-[11px] text-slate-400 mb-4">
                            {next_reference} · composed from this student's own record
                        </p>

                        {/* Student */}
                        <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Student</label>
                        {student && !pickerOpen ? (
                            <div className="flex items-center justify-between gap-2 px-3.5 py-2.5 rounded-2xl border border-slate-200 bg-slate-50">
                                <div className="min-w-0">
                                    <p className="text-sm font-bold text-slate-800 truncate">{student.name}</p>
                                    <p className="text-[11px] text-slate-400">
                                        {student.uid}{student.class ? ` · ${student.class}` : ''}
                                    </p>
                                </div>
                                <button onClick={() => setPickerOpen(true)}
                                    className="text-[11px] font-bold text-slate-500 hover:text-slate-800 flex-shrink-0">
                                    Change
                                </button>
                            </div>
                        ) : (
                            <div>
                                <div className="relative">
                                    <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                                    {searching && <Loader2 className="w-3.5 h-3.5 text-slate-300 absolute right-3 top-1/2 -translate-y-1/2 animate-spin" />}
                                    <input autoFocus value={query} onChange={e => setQuery(e.target.value)}
                                        placeholder="Search by name or student ID…"
                                        className="w-full pl-10 pr-3 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:ring-2"
                                        style={{ ['--tw-ring-color' as string]: `${accent}59` }} />
                                </div>
                                <div className="mt-2 max-h-56 overflow-y-auto rounded-2xl border border-slate-100 divide-y divide-slate-50">
                                    {results.length === 0 && !searching && (
                                        <p className="text-[11px] text-slate-400 px-3.5 py-3">No students found.</p>
                                    )}
                                    {results.map(s => (
                                        <button key={s.id} onClick={() => pick(s)}
                                            className="w-full text-left px-3.5 py-2 hover:bg-slate-50 transition">
                                            <p className="text-xs font-bold text-slate-700">{s.name}</p>
                                            <p className="text-[10px] text-slate-400">
                                                {s.uid}{s.status ? ` · ${s.status.replace('_', ' ')}` : ''}
                                            </p>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Type */}
                        <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-4 mb-1.5">Type</label>
                        <div className="space-y-1.5">
                            {types.map(t => (
                                <button key={t.value} onClick={() => {
                                    setType(t.value);
                                    if (t.value === 'character' && !note) setNote(suggested_note || '');
                                }}
                                    className={`w-full text-left px-3.5 py-2 rounded-2xl border transition ${type === t.value ? 'border-transparent' : 'border-slate-200 hover:border-slate-300'}`}
                                    style={type === t.value ? { background: `${accent}12`, boxShadow: `inset 0 0 0 1.5px ${accent}` } : undefined}>
                                    <p className={`text-xs font-bold ${type === t.value ? '' : 'text-slate-700'}`}
                                        style={type === t.value ? { color: accent } : undefined}>{t.label}</p>
                                    {t.hint && <p className="text-[10px] text-slate-400 mt-0.5 leading-snug">{t.hint}</p>}
                                </button>
                            ))}
                        </div>

                        {/* Addressed to */}
                        <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mt-4 mb-1.5">Addressed to (optional)</label>
                        <input value={addressedTo} onChange={e => setAddressedTo(e.target.value)}
                            placeholder="e.g. Ministry of Education, Kenya"
                            className="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:ring-2"
                            style={{ ['--tw-ring-color' as string]: `${accent}59` }} />

                        {/* Body note — only where it is actually used */}
                        {NOTE_TYPES.includes(type) && (
                            <>
                                <div className="flex items-center justify-between mt-4 mb-1.5">
                                    <label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">
                                        {type === 'custom' ? 'Letter body' : 'Conduct wording'}
                                    </label>
                                    {suggested_note && (
                                        <button onClick={() => setNote(suggested_note)}
                                            className="text-[10px] font-bold text-slate-400 hover:text-slate-700">
                                            Use suggested
                                        </button>
                                    )}
                                </div>
                                <textarea value={note} onChange={e => setNote(e.target.value)} rows={5}
                                    className="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:ring-2 resize-y"
                                    style={{ ['--tw-ring-color' as string]: `${accent}59` }} />
                                <p className="text-[10px] text-slate-400 mt-1 flex items-start gap-1">
                                    <AlertCircle className="w-3 h-3 flex-shrink-0 mt-0.5" />
                                    {type === 'character'
                                        ? 'This sentence becomes the school\'s statement about the student. Read it before you sign.'
                                        : 'This is the whole letter — everything else is the letterhead and signature.'}
                                </p>
                            </>
                        )}

                        {is_admin ? (
                            <button onClick={issue} disabled={!student || busy}
                                className="w-full mt-5 px-5 py-3 rounded-2xl text-sm font-bold text-white disabled:opacity-40"
                                style={{ background: accent }}>
                                {busy ? 'Issuing…' : 'Issue certificate'}
                            </button>
                        ) : (
                            <p className="mt-5 text-[11px] text-slate-400 text-center">
                                Only school administrators can issue certificates.
                            </p>
                        )}
                    </div>
                </div>

                {/* ── Right: live preview ── */}
                <div className="min-w-0">
                    <div className="print:hidden flex items-center justify-between mb-3">
                        <p className="text-[11px] font-bold uppercase tracking-widest text-slate-400">
                            Preview {loading && <span className="text-slate-300">· updating…</span>}
                        </p>
                        <button onClick={() => window.print()}
                            className="flex items-center gap-1.5 text-[11px] font-bold text-slate-500 hover:text-slate-800">
                            <Printer className="w-3.5 h-3.5" /> Print preview
                        </button>
                    </div>

                    {snapshot ? (
                        <CertificateLetter snapshot={snapshot} accent={accent} reference={next_reference} addressedTo={addressedTo || null} />
                    ) : (
                        <div className="bg-white rounded-3xl border border-slate-200 py-24 text-center">
                            <p className="text-sm font-bold text-slate-700">Pick a student to begin</p>
                            <p className="text-xs text-slate-400 mt-1">The letter fills itself in from their record.</p>
                        </div>
                    )}

                    {selectedType?.hint && (
                        <p className="print:hidden text-[11px] text-slate-400 text-center mt-3">{selectedType.hint}</p>
                    )}
                </div>
            </div>
        </SchoolLayout>
    );
}
