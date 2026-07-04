import React, { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Trash2, Pencil, X, Coffee } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

/* ── colour helpers ──────────────────────────────────────────── */
function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <div
            className={`bg-white rounded-3xl border border-slate-200/60 ${className}`}
            style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}
        >
            {children}
        </div>
    );
}

interface Period { id: number; label: string; start_time: string; end_time: string; is_break: boolean }
interface Entry { period_id: number; day_of_week: number; subject_id: number | null; teacher_id: number | null; room: string | null }
interface Option { id: number; name: string }

interface Props {
    classes: Option[];
    selected_class_id: number | null;
    periods: Period[];
    entries: Entry[];
    subjects: Option[];
    teachers: Option[];
    default_teachers: Record<number, number>; // subject_id → teacher_id
    working_days: number[]; // ISO weekday numbers the school operates
}

const ALL_DAYS = [
    { n: 1, label: 'Monday', short: 'Mon' }, { n: 2, label: 'Tuesday', short: 'Tue' },
    { n: 3, label: 'Wednesday', short: 'Wed' }, { n: 4, label: 'Thursday', short: 'Thu' },
    { n: 5, label: 'Friday', short: 'Fri' }, { n: 6, label: 'Saturday', short: 'Sat' },
    { n: 7, label: 'Sunday', short: 'Sun' },
];

const inputCls = 'w-full px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition bg-white';

export default function Index({
    classes, selected_class_id, periods, entries, subjects, teachers, default_teachers, working_days,
}: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    // Grid columns come from the school's working days, not a hardcoded week.
    const DAYS = useMemo(
        () => ALL_DAYS.filter(d => working_days.includes(d.n)),
        [working_days],
    );

    /* ── working-days editor ── */
    const [editingDays, setEditingDays] = useState(false);
    const [daySel, setDaySel] = useState<number[]>(working_days);
    function toggleDay(n: number) {
        setDaySel(s => s.includes(n) ? s.filter(d => d !== n) : [...s, n].sort());
    }
    function saveWorkingDays() {
        if (daySel.length === 0) return;
        router.patch('/school/timetable/working-days', { working_days: daySel }, {
            preserveScroll: true,
            onSuccess: () => setEditingDays(false),
        });
    }

    const entryMap = useMemo(() => {
        const m = new Map<string, Entry>();
        entries.forEach(e => m.set(`${e.period_id}:${e.day_of_week}`, e));
        return m;
    }, [entries]);

    const subjectName = (id: number | null) => subjects.find(s => s.id === id)?.name ?? null;
    const teacherName = (id: number | null) => teachers.find(t => t.id === id)?.name ?? null;

    /* ── period editor state ── */
    const [addingPeriod, setAddingPeriod] = useState(false);
    const [editPeriodId, setEditPeriodId] = useState<number | null>(null);
    const emptyPeriod = { label: '', start_time: '08:00', end_time: '08:40', is_break: false };
    const [periodForm, setPeriodForm] = useState(emptyPeriod);

    function submitPeriod(e: React.FormEvent) {
        e.preventDefault();
        const opts = {
            preserveScroll: true,
            onSuccess: () => { setAddingPeriod(false); setEditPeriodId(null); setPeriodForm(emptyPeriod); },
        };
        if (editPeriodId) {
            router.patch(`/school/timetable/periods/${editPeriodId}`, periodForm, opts);
        } else {
            router.post('/school/timetable/periods', periodForm, opts);
        }
    }

    /* ── cell editor state ── */
    const [cell, setCell] = useState<{ period: Period; day: number } | null>(null);
    const [cellForm, setCellForm] = useState<{ subject_id: number | ''; teacher_id: number | ''; room: string }>({
        subject_id: '', teacher_id: '', room: '',
    });

    function openCell(period: Period, day: number) {
        const existing = entryMap.get(`${period.id}:${day}`);
        setCellForm({
            subject_id: existing?.subject_id ?? '',
            teacher_id: existing?.teacher_id ?? '',
            room: existing?.room ?? '',
        });
        setCell({ period, day });
    }

    function pickSubject(subjectId: number | '') {
        // Prefill the assigned teacher for this class×subject when known.
        const suggested = subjectId !== '' ? default_teachers[subjectId] : undefined;
        setCellForm(f => ({
            ...f,
            subject_id: subjectId,
            teacher_id: suggested ?? f.teacher_id,
        }));
    }

    function saveCell() {
        if (!cell || !selected_class_id) return;
        router.post('/school/timetable/entries', {
            class_id: selected_class_id,
            period_id: cell.period.id,
            day_of_week: cell.day,
            subject_id: cellForm.subject_id === '' ? null : cellForm.subject_id,
            teacher_id: cellForm.teacher_id === '' ? null : cellForm.teacher_id,
            room: cellForm.room || null,
        }, { preserveScroll: true, onSuccess: () => setCell(null) });
    }

    function clearCell() {
        if (!cell || !selected_class_id) return;
        router.delete('/school/timetable/entries', {
            data: { class_id: selected_class_id, period_id: cell.period.id, day_of_week: cell.day },
            preserveScroll: true,
            onSuccess: () => setCell(null),
        });
    }

    return (
        <SchoolLayout>
            <Head title="Timetable" />

            {/* Header */}
            <div className="flex items-center justify-between gap-3 flex-wrap mb-6">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900">Timetable</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Define the bell schedule once, then fill each class's week. Teachers see
                        their schedule on their dashboard and in the mobile app automatically.
                    </p>
                </div>
                <select
                    value={selected_class_id ?? ''}
                    onChange={e => router.get('/school/timetable', { class_id: e.target.value }, { preserveState: false })}
                    className="px-4 py-2.5 rounded-2xl border border-slate-200 text-sm font-semibold text-slate-700 bg-white shadow-sm focus:outline-none focus:ring-2"
                    style={inputStyle}
                >
                    {classes.length === 0 && <option value="">No classes yet</option>}
                    {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
            </div>

            {/* Working days */}
            <Card className="p-6 mb-6">
                <div className="flex items-center justify-between gap-3 flex-wrap mb-4">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>
                            Working Days
                        </p>
                        <p className="text-xs text-slate-500 mt-1">
                            {DAYS.map(d => d.short).join(' · ')} — the days your school operates. The grid below shows only these.
                        </p>
                    </div>
                    {!editingDays ? (
                        <button
                            onClick={() => { setDaySel(working_days); setEditingDays(true); }}
                            className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 bg-white hover:bg-slate-50"
                        >
                            <Pencil className="w-3.5 h-3.5" /> Edit days
                        </button>
                    ) : (
                        <div className="flex items-center gap-2">
                            <button onClick={() => setEditingDays(false)} className="px-3 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100">Cancel</button>
                            <button
                                onClick={saveWorkingDays}
                                disabled={daySel.length === 0}
                                className="px-3.5 py-2 rounded-xl text-xs font-bold text-white disabled:opacity-40"
                                style={{ background: accent }}
                            >
                                Save days
                            </button>
                        </div>
                    )}
                </div>
                {editingDays && (
                    <div className="flex flex-wrap gap-2">
                        {ALL_DAYS.map(d => {
                            const on = daySel.includes(d.n);
                            return (
                                <button
                                    key={d.n}
                                    onClick={() => toggleDay(d.n)}
                                    className={`px-3.5 py-2 rounded-xl text-xs font-bold border transition ${on ? 'text-white border-transparent' : 'text-slate-600 border-slate-200 bg-white hover:bg-slate-50'}`}
                                    style={on ? { background: accent } : undefined}
                                >
                                    {d.label}
                                </button>
                            );
                        })}
                    </div>
                )}
                {editingDays && (
                    <p className="text-[11px] text-amber-600 mt-3">
                        Removing a day permanently clears any lessons already scheduled on it.
                    </p>
                )}
            </Card>

            {/* Periods (bell schedule) */}
            <Card className="p-6 mb-6">
                <div className="flex items-center justify-between mb-4">
                    <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>
                        Bell Schedule (shared by every class)
                    </p>
                    {!addingPeriod && (
                        <button
                            onClick={() => { setAddingPeriod(true); setEditPeriodId(null); setPeriodForm(emptyPeriod); }}
                            className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white"
                            style={{ background: accent }}
                        >
                            <Plus className="w-3.5 h-3.5" /> Add period
                        </button>
                    )}
                </div>

                {periods.length === 0 && !addingPeriod && (
                    <p className="text-sm text-slate-400">
                        No periods yet — add your first period (e.g. "Period 1", 08:00–08:40) to unlock the grid below.
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    {periods.map(p => (
                        <div
                            key={p.id}
                            className={`flex items-center gap-2 px-3.5 py-2 rounded-xl border text-xs font-semibold ${p.is_break ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-slate-50 border-slate-200 text-slate-700'}`}
                        >
                            {p.is_break && <Coffee className="w-3.5 h-3.5" />}
                            <span className="font-bold">{p.label}</span>
                            <span className="text-slate-400">{p.start_time}–{p.end_time}</span>
                            <button
                                onClick={() => {
                                    setEditPeriodId(p.id); setAddingPeriod(true);
                                    setPeriodForm({ label: p.label, start_time: p.start_time, end_time: p.end_time, is_break: p.is_break });
                                }}
                                className="p-1 rounded-md text-slate-400 hover:text-slate-700 hover:bg-white transition"
                            >
                                <Pencil className="w-3 h-3" />
                            </button>
                            <button
                                onClick={() => {
                                    if (confirm(`Remove "${p.label}"? Scheduled lessons in this period are removed for ALL classes.`)) {
                                        router.delete(`/school/timetable/periods/${p.id}`, { preserveScroll: true });
                                    }
                                }}
                                className="p-1 rounded-md text-slate-400 hover:text-red-500 hover:bg-white transition"
                            >
                                <Trash2 className="w-3 h-3" />
                            </button>
                        </div>
                    ))}
                </div>

                {addingPeriod && (
                    <form onSubmit={submitPeriod} className="mt-4 flex items-end gap-3 flex-wrap p-4 rounded-2xl bg-slate-50 border border-slate-100">
                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Label</label>
                            <input value={periodForm.label} onChange={e => setPeriodForm({ ...periodForm, label: e.target.value })}
                                placeholder="Period 1" required className={inputCls} style={inputStyle} />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Starts</label>
                            <input type="time" value={periodForm.start_time} onChange={e => setPeriodForm({ ...periodForm, start_time: e.target.value })}
                                required className={inputCls} style={inputStyle} />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Ends</label>
                            <input type="time" value={periodForm.end_time} onChange={e => setPeriodForm({ ...periodForm, end_time: e.target.value })}
                                required className={inputCls} style={inputStyle} />
                        </div>
                        <label className="flex items-center gap-2 pb-2.5 cursor-pointer text-sm text-slate-600">
                            <input type="checkbox" checked={periodForm.is_break}
                                onChange={e => setPeriodForm({ ...periodForm, is_break: e.target.checked })}
                                className="w-4 h-4 rounded border-slate-300 accent-indigo-600" />
                            Break
                        </label>
                        <div className="flex items-center gap-2 pb-0.5">
                            <button type="submit" className="px-4 py-2.5 rounded-xl text-xs font-bold text-white" style={{ background: accent }}>
                                {editPeriodId ? 'Save period' : 'Add period'}
                            </button>
                            <button type="button" onClick={() => { setAddingPeriod(false); setEditPeriodId(null); }}
                                className="px-3 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100">
                                Cancel
                            </button>
                        </div>
                    </form>
                )}
            </Card>

            {/* Weekly grid */}
            {periods.length > 0 && selected_class_id && (
                <Card className="overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm border-collapse">
                            <thead>
                                <tr>
                                    <th className="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-100 w-32">Period</th>
                                    {DAYS.map(d => (
                                        <th key={d.n} className="px-3 py-3 text-left text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-100">
                                            {d.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {periods.map(p => (
                                    <tr key={p.id} className="border-b border-slate-50 last:border-0">
                                        <td className="px-4 py-2.5 align-top">
                                            <p className="font-bold text-slate-800 text-xs">{p.label}</p>
                                            <p className="text-[10px] text-slate-400">{p.start_time}–{p.end_time}</p>
                                        </td>
                                        {p.is_break ? (
                                            <td colSpan={DAYS.length} className="px-3 py-2.5">
                                                <div className="flex items-center gap-2 px-3 py-2 rounded-xl bg-amber-50 text-amber-600 text-xs font-bold">
                                                    <Coffee className="w-3.5 h-3.5" /> Break
                                                </div>
                                            </td>
                                        ) : DAYS.map(d => {
                                            const entry = entryMap.get(`${p.id}:${d.n}`);
                                            const filled = entry && (entry.subject_id || entry.teacher_id);
                                            return (
                                                <td key={d.n} className="px-1.5 py-1.5 align-top">
                                                    <button
                                                        onClick={() => openCell(p, d.n)}
                                                        className="w-full min-h-[52px] text-left px-2.5 py-2 rounded-xl border transition text-xs"
                                                        style={filled
                                                            ? { background: alpha(accent, 0.06), borderColor: alpha(accent, 0.25) }
                                                            : { background: '#fff', borderColor: '#f1f5f9' }}
                                                    >
                                                        {filled ? (
                                                            <>
                                                                <p className="font-bold text-slate-800 truncate">{subjectName(entry!.subject_id) ?? '—'}</p>
                                                                <p className="text-[10px] text-slate-500 truncate">
                                                                    {teacherName(entry!.teacher_id) ?? 'No teacher'}
                                                                    {entry!.room ? ` · ${entry!.room}` : ''}
                                                                </p>
                                                            </>
                                                        ) : (
                                                            <span className="text-slate-300 font-bold">+</span>
                                                        )}
                                                    </button>
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Card>
            )}

            {/* Cell editor modal */}
            {cell && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onClick={() => setCell(null)}>
                    <div className="bg-white rounded-3xl p-6 w-full max-w-sm shadow-2xl" onClick={e => e.stopPropagation()}>
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <p className="font-extrabold text-slate-900">
                                    {DAYS.find(d => d.n === cell.day)?.label} · {cell.period.label}
                                </p>
                                <p className="text-xs text-slate-400">{cell.period.start_time}–{cell.period.end_time}</p>
                            </div>
                            <button onClick={() => setCell(null)} className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="space-y-3">
                            <div>
                                <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Subject</label>
                                <select
                                    value={cellForm.subject_id}
                                    onChange={e => pickSubject(e.target.value === '' ? '' : Number(e.target.value))}
                                    className={inputCls} style={inputStyle}
                                >
                                    <option value="">— none —</option>
                                    {subjects.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Teacher</label>
                                <select
                                    value={cellForm.teacher_id}
                                    onChange={e => setCellForm({ ...cellForm, teacher_id: e.target.value === '' ? '' : Number(e.target.value) })}
                                    className={inputCls} style={inputStyle}
                                >
                                    <option value="">— none —</option>
                                    {teachers.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
                                </select>
                                <p className="text-[10px] text-slate-400 mt-1">
                                    Picking a subject suggests its assigned teacher. Double-booking a teacher is blocked on save.
                                </p>
                            </div>
                            <div>
                                <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1">Room (optional)</label>
                                <input value={cellForm.room} onChange={e => setCellForm({ ...cellForm, room: e.target.value })}
                                    placeholder="e.g. B-12" className={inputCls} style={inputStyle} />
                            </div>
                        </div>

                        <div className="flex items-center justify-between mt-5">
                            <button onClick={clearCell} className="px-4 py-2.5 rounded-xl text-xs font-bold text-red-500 hover:bg-red-50 transition">
                                Clear lesson
                            </button>
                            <div className="flex items-center gap-2">
                                <button onClick={() => setCell(null)} className="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100">
                                    Cancel
                                </button>
                                <button onClick={saveCell} className="px-5 py-2.5 rounded-xl text-xs font-bold text-white" style={{ background: accent }}>
                                    Save
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </SchoolLayout>
    );
}
