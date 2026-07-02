import React, { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { ArrowRight, ArrowLeft, GraduationCap, Repeat2, TrendingUp, AlertTriangle, QrCode } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

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

interface ClassRow {
    id: number;
    name: string;
    grade: string;
    section: string;
    level_order: number;
    active_students: number;
    suggested_grade: string;
    suggested_action: 'promote' | 'graduate';
}

interface Props {
    current_year: string;
    suggested_year: string;
    classes: ClassRow[];
}

type Action = 'promote' | 'stay' | 'graduate';

const ACTION_META: Record<Action, { label: string; icon: React.ElementType; tone: string }> = {
    promote:  { label: 'Promote',  icon: TrendingUp,    tone: '#059669' },
    stay:     { label: 'Repeat',   icon: Repeat2,       tone: '#0284c7' },
    graduate: { label: 'Graduate', icon: GraduationCap, tone: '#7c3aed' },
};

export default function Index({ current_year, suggested_year, classes }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };
    const inputCls = 'px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition bg-white';

    const [step, setStep] = useState(0);
    const [newYear, setNewYear] = useState(suggested_year);
    const [confirmText, setConfirmText] = useState('');
    const [processing, setProcessing] = useState(false);
    const [mappings, setMappings] = useState<Record<number, { action: Action; target_grade: string }>>(
        () => Object.fromEntries(classes.map(c => [c.id, {
            action: c.suggested_action as Action,
            target_grade: c.suggested_grade,
        }]))
    );

    const setMap = (id: number, patch: Partial<{ action: Action; target_grade: string }>) =>
        setMappings(m => ({ ...m, [id]: { ...m[id], ...patch } }));

    const totals = useMemo(() => {
        const t = { promote: 0, stay: 0, graduate: 0, newClasses: 0, tokens: 0 };
        classes.forEach(c => {
            const a = mappings[c.id]?.action ?? 'promote';
            t[a] += c.active_students;
            if (a !== 'graduate') t.newClasses++;
        });
        t.tokens = t.promote + t.stay; // active students after transition
        return t;
    }, [classes, mappings]);

    function execute() {
        setProcessing(true);
        router.post('/school/year-transition', {
            new_year: newYear,
            confirmed: true,
            mappings: classes.map(c => ({
                class_id: c.id,
                action: mappings[c.id].action,
                target_grade: mappings[c.id].action === 'promote' ? mappings[c.id].target_grade : null,
            })),
        }, { onFinish: () => setProcessing(false) });
    }

    const steps = ['New year', 'Class mapping', 'Review & confirm'];

    return (
        <SchoolLayout>
            <Head title="Academic Year Promotion" />

            <div className="max-w-4xl">
                <h1 className="text-2xl font-extrabold text-slate-900">Academic Year Promotion</h1>
                <p className="text-sm text-slate-500 mt-1 mb-6">
                    Move the whole school from <b>{current_year}</b> to the next academic year in one guided, all-or-nothing step.
                </p>

                {/* Stepper */}
                <div className="flex items-center gap-2 mb-6">
                    {steps.map((s, i) => (
                        <React.Fragment key={s}>
                            <div
                                className="flex items-center gap-2 px-3.5 py-2 rounded-2xl text-xs font-bold"
                                style={i === step
                                    ? { background: accent, color: '#fff' }
                                    : i < step
                                        ? { background: alpha(accent, 0.10), color: accent }
                                        : { background: '#fff', color: '#94a3b8', border: '1px solid #e2e8f0' }}
                            >
                                <span className="w-5 h-5 rounded-full flex items-center justify-center text-[10px]"
                                    style={i === step ? { background: 'rgba(255,255,255,0.25)' } : { background: alpha(accent, 0.12), color: accent }}>
                                    {i + 1}
                                </span>
                                {s}
                            </div>
                            {i < steps.length - 1 && <div className="w-6 h-px bg-slate-200" />}
                        </React.Fragment>
                    ))}
                </div>

                {/* Step 1 — new year */}
                {step === 0 && (
                    <Card className="p-6">
                        <p className="text-[10px] font-bold uppercase tracking-widest mb-4" style={{ color: accent }}>
                            What is the new academic year?
                        </p>
                        <div className="flex items-end gap-4 flex-wrap">
                            <div>
                                <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">Current</label>
                                <div className="px-4 py-2.5 rounded-2xl bg-slate-50 border border-slate-100 text-sm font-bold text-slate-500">
                                    {current_year}
                                </div>
                            </div>
                            <ArrowRight className="w-5 h-5 text-slate-300 mb-2.5" />
                            <div>
                                <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">New year *</label>
                                <input value={newYear} onChange={e => setNewYear(e.target.value)}
                                    className={`${inputCls} font-bold`} style={inputStyle} />
                            </div>
                        </div>
                        <p className="text-xs text-slate-400 mt-4">
                            Use the same format your school already uses (e.g. "{suggested_year}").
                        </p>
                        <div className="flex justify-end mt-6">
                            <button
                                onClick={() => setStep(1)}
                                disabled={!newYear.trim() || newYear.trim() === current_year}
                                className="flex items-center gap-2 px-6 py-2.5 rounded-2xl text-sm font-bold text-white disabled:opacity-40"
                                style={{ background: accent }}
                            >
                                Continue <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    </Card>
                )}

                {/* Step 2 — mapping */}
                {step === 1 && (
                    <Card className="overflow-hidden">
                        <div className="px-6 pt-5 pb-3">
                            <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>
                                Where does each class go in {newYear}?
                            </p>
                        </div>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-100">
                                    <th className="px-6 py-2.5">Class ({current_year})</th>
                                    <th className="px-3 py-2.5">Students</th>
                                    <th className="px-3 py-2.5">Action</th>
                                    <th className="px-6 py-2.5">Becomes ({newYear})</th>
                                </tr>
                            </thead>
                            <tbody>
                                {classes.map(c => {
                                    const m = mappings[c.id];
                                    const meta = ACTION_META[m.action];
                                    return (
                                        <tr key={c.id} className="border-b border-slate-50 last:border-0">
                                            <td className="px-6 py-3 font-bold text-slate-800">{c.name}</td>
                                            <td className="px-3 py-3 text-slate-500">{c.active_students}</td>
                                            <td className="px-3 py-3">
                                                <div className="flex items-center gap-1.5">
                                                    {(Object.keys(ACTION_META) as Action[]).map(a => {
                                                        const am = ACTION_META[a];
                                                        const on = m.action === a;
                                                        return (
                                                            <button key={a} onClick={() => setMap(c.id, { action: a })}
                                                                className="flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[11px] font-bold border transition"
                                                                style={on
                                                                    ? { background: alpha(am.tone, 0.12), borderColor: alpha(am.tone, 0.4), color: am.tone }
                                                                    : { background: '#fff', borderColor: '#e2e8f0', color: '#94a3b8' }}
                                                            >
                                                                <am.icon className="w-3 h-3" /> {am.label}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </td>
                                            <td className="px-6 py-3">
                                                {m.action === 'promote' && (
                                                    <div className="flex items-center gap-2">
                                                        <input value={m.target_grade}
                                                            onChange={e => setMap(c.id, { target_grade: e.target.value })}
                                                            className={`${inputCls} w-32 py-1.5 text-xs font-bold`} style={inputStyle} />
                                                        <span className="text-xs text-slate-400">– {c.section}</span>
                                                    </div>
                                                )}
                                                {m.action === 'stay' && (
                                                    <span className="text-xs font-bold text-slate-500">{c.grade} – {c.section} (repeats)</span>
                                                )}
                                                {m.action === 'graduate' && (
                                                    <span className="text-xs font-bold" style={{ color: meta.tone }}>
                                                        Leaves the school 🎓
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                        <div className="flex justify-between px-6 py-4 border-t border-slate-100">
                            <button onClick={() => setStep(0)} className="flex items-center gap-2 px-4 py-2.5 rounded-2xl text-sm font-bold text-slate-500 hover:bg-slate-50">
                                <ArrowLeft className="w-4 h-4" /> Back
                            </button>
                            <button onClick={() => setStep(2)} className="flex items-center gap-2 px-6 py-2.5 rounded-2xl text-sm font-bold text-white" style={{ background: accent }}>
                                Review <ArrowRight className="w-4 h-4" />
                            </button>
                        </div>
                    </Card>
                )}

                {/* Step 3 — review + confirm */}
                {step === 2 && (
                    <div className="space-y-5">
                        <Card className="p-6">
                            <p className="text-[10px] font-bold uppercase tracking-widest mb-5" style={{ color: accent }}>
                                {current_year} → {newYear}
                            </p>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                {[
                                    { label: 'Promoted', value: totals.promote, tone: '#059669' },
                                    { label: 'Repeating', value: totals.stay, tone: '#0284c7' },
                                    { label: 'Graduating', value: totals.graduate, tone: '#7c3aed' },
                                    { label: 'New classes', value: totals.newClasses, tone: normHex(accent) },
                                ].map(({ label, value, tone }) => (
                                    <div key={label} className="p-4 rounded-2xl border border-slate-100 text-center">
                                        <p className="text-2xl font-extrabold" style={{ color: tone }}>{value}</p>
                                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-1">{label}</p>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        <Card className="p-6 border-amber-200 bg-amber-50/60">
                            <div className="flex gap-3">
                                <AlertTriangle className="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" />
                                <div className="text-sm text-amber-800 space-y-1.5">
                                    <p className="font-bold">This cannot be undone. When you confirm:</p>
                                    <p className="flex items-center gap-2"><QrCode className="w-3.5 h-3.5" />
                                        All <b>{totals.tokens}</b> active students get new QR tokens — <b>every printed ID card stops scanning immediately</b>. Order new cards right after.
                                    </p>
                                    <p>· {current_year} classes are archived; teachers must be re-assigned to the new classes (Staff → edit teacher).</p>
                                    <p>· Graduating students ({totals.graduate}) leave the active roster and free up plan seats.</p>
                                </div>
                            </div>
                        </Card>

                        <Card className="p-6">
                            <label className="block text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-1.5">
                                Type the new year to confirm
                            </label>
                            <input value={confirmText} onChange={e => setConfirmText(e.target.value)}
                                placeholder={newYear} className={`${inputCls} w-64 font-bold`} style={inputStyle} />
                            <div className="flex justify-between mt-6">
                                <button onClick={() => setStep(1)} className="flex items-center gap-2 px-4 py-2.5 rounded-2xl text-sm font-bold text-slate-500 hover:bg-slate-50">
                                    <ArrowLeft className="w-4 h-4" /> Back
                                </button>
                                <button
                                    onClick={execute}
                                    disabled={processing || confirmText.trim() !== newYear.trim()}
                                    className="px-6 py-2.5 rounded-2xl text-sm font-bold text-white disabled:opacity-40"
                                    style={{ background: '#dc2626', boxShadow: '0 4px 14px rgba(220,38,38,0.3)' }}
                                >
                                    {processing ? 'Promoting…' : `Start ${newYear}`}
                                </button>
                            </div>
                        </Card>
                    </div>
                )}
            </div>
        </SchoolLayout>
    );
}
