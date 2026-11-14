import React, { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight, RotateCcw, Award, MinusCircle, Users, AlertTriangle,
    CheckCircle2, ChevronRight, GraduationCap,
} from 'lucide-react';
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

interface ClassOption {
    id: number;
    label: string;
    level_number: number | null;
    programme_id: number | null;
    programme: string | null;
    academic_year: string | null;
    current_count: number;
    is_active: boolean;
}

interface StudentRow {
    student_id: number;
    enrollment_id: number;
    name: string;
    uid: string | null;
    gender: string | null;
    enrolled_on: string | null;
    suggested_action: 'promote' | 'graduate';
    suggested_target: number | null;
}

interface Props {
    classes: ClassOption[];
    source_class_id: number | null;
    students: StudentRow[];
    academic_year: string;
    is_admin: boolean;
    flash?: { success?: string; bulk_failures?: string[] };
}

type Action = 'promote' | 'repeat' | 'graduate' | 'skip';

interface PlanRow {
    action: Action;
    target: string;
}

const ACTION_META: Record<Action, { label: string; icon: React.ComponentType<{ className?: string }>; short: string }> = {
    promote:  { label: 'Promote',  icon: ArrowUpRight,  short: 'Promote' },
    repeat:   { label: 'Repeat',   icon: RotateCcw,     short: 'Repeat' },
    graduate: { label: 'Graduate', icon: Award,         short: 'Graduate' },
    skip:     { label: 'Leave',    icon: MinusCircle,   short: 'Leave' },
};

export default function Index({ classes, source_class_id, students, academic_year, is_admin, flash }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const source = classes.find(c => c.id === source_class_id) ?? null;

    // Classic end-of-year default: next level up in the same programme.
    const nextLevel = useMemo(() => {
        if (!source) return null;
        return classes.find(c =>
            c.programme_id === source.programme_id &&
            source.level_number !== null &&
            c.level_number === source.level_number + 1,
        ) ?? null;
    }, [classes, source]);

    const [plan, setPlan] = useState<Record<number, PlanRow>>(() => {
        const init: Record<number, PlanRow> = {};
        for (const s of students) {
            init[s.student_id] = {
                action: s.suggested_action,
                target: s.suggested_target ? String(s.suggested_target) : '',
            };
        }
        return init;
    });

    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);

    function setRow(studentId: number, patch: Partial<PlanRow>) {
        setPlan(p => ({ ...p, [studentId]: { ...p[studentId], ...patch } }));
    }

    function applyToAll(action: Action) {
        const next: Record<number, PlanRow> = {};
        for (const s of students) {
            next[s.student_id] = {
                action,
                target: action === 'promote' && nextLevel
                    ? String(nextLevel.id)
                    : action === 'repeat' && source
                        ? String(source.id)
                        : '',
            };
        }
        setPlan(next);
    }

    function chooseClass(id: string) {
        router.get('/school/promotions', id ? { class_id: id } : {}, { preserveState: false });
    }

    function submit() {
        const actions = students.map(s => ({
            student_id: s.student_id,
            action: plan[s.student_id]?.action ?? 'skip',
            target_class_id: plan[s.student_id]?.target ? Number(plan[s.student_id].target) : null,
        }));

        setBusy(true);
        router.post('/school/promotions', { actions, note } as never, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
        });
    }

    const counts = useMemo(() => {
        const c: Record<string, number> = { promote: 0, repeat: 0, graduate: 0, skip: 0 };
        for (const s of students) {
            const a = plan[s.student_id]?.action ?? 'skip';
            c[a] = (c[a] ?? 0) + 1;
        }
        return c;
    }, [plan, students]);

    const missingTargets = students.filter(s => {
        const row = plan[s.student_id];
        return (row?.action === 'promote' || row?.action === 'repeat') && !row.target;
    }).length;

    const inputCls = 'px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition bg-white';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <SchoolLayout>
            <Head title="Promotions" />

            <div className="mb-6">
                <h1 className="text-xl font-bold text-slate-900">End-of-year promotion</h1>
                <p className="text-xs text-slate-400 mt-0.5">
                    Move a whole class forward at once. Each student keeps their own history — nothing is overwritten.
                </p>
            </div>

            {flash?.success && (
                <div className="mb-5 p-4 rounded-2xl border border-emerald-100 bg-emerald-50 flex items-start gap-3">
                    <CheckCircle2 className="w-5 h-5 text-emerald-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="text-sm font-bold text-emerald-800">{flash.success}</p>
                        {flash.bulk_failures && flash.bulk_failures.length > 0 && (
                            <ul className="mt-2 space-y-0.5">
                                {flash.bulk_failures.map((f, i) => (
                                    <li key={i} className="text-xs text-emerald-700">• {f}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            )}

            {/* Step 1 — pick the class */}
            <div className="bg-white rounded-3xl border border-slate-200 p-5 mb-5">
                <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">
                    Step 1 · Choose the class to move
                </label>
                <select
                    value={source_class_id ?? ''}
                    onChange={e => chooseClass(e.target.value)}
                    className={inputCls + ' w-full max-w-md'}
                    style={inputStyle}
                >
                    <option value="">Select a class…</option>
                    {classes.map(c => (
                        <option key={c.id} value={c.id}>
                            {c.label} — {c.current_count} student{c.current_count === 1 ? '' : 's'}
                        </option>
                    ))}
                </select>

                {source && (
                    <div className="flex flex-wrap items-center gap-3 mt-4 text-xs">
                        <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 font-semibold">
                            <Users className="w-3.5 h-3.5" /> {source.current_count} enrolled
                        </span>
                        {source.programme && (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 font-semibold">
                                <GraduationCap className="w-3.5 h-3.5" /> {source.programme}
                            </span>
                        )}
                        {nextLevel ? (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 font-semibold">
                                <ChevronRight className="w-3.5 h-3.5" /> Next level: {nextLevel.label}
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-50 text-amber-700 font-semibold">
                                <AlertTriangle className="w-3.5 h-3.5" /> No next level — these students graduate
                            </span>
                        )}
                    </div>
                )}
            </div>

            {source && students.length === 0 && (
                <div className="bg-white rounded-3xl border border-slate-200 p-12 text-center">
                    <Users className="w-10 h-10 text-slate-200 mx-auto mb-3" />
                    <p className="text-sm font-semibold text-slate-500">No current students in this class</p>
                    <p className="text-xs text-slate-400 mt-1">Enrol students first from their profile page.</p>
                </div>
            )}

            {students.length > 0 && (
                <>
                    {/* Step 2 — the plan */}
                    <div className="bg-white rounded-3xl border border-slate-200 overflow-hidden mb-5">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between flex-wrap gap-3">
                            <div>
                                <p className="text-xs font-bold text-slate-500 uppercase tracking-widest">
                                    Step 2 · Confirm each student
                                </p>
                                <p className="text-xs text-slate-400 mt-1">
                                    Defaults are suggested from the class level. Override any row.
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <button onClick={() => applyToAll('promote')}
                                    className="text-xs font-bold px-3 py-1.5 rounded-xl"
                                    style={{ color: accent, background: alpha(accent, 0.08) }}>
                                    Promote all
                                </button>
                                <button onClick={() => applyToAll('graduate')}
                                    className="text-xs font-bold px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600">
                                    Graduate all
                                </button>
                                <button onClick={() => applyToAll('skip')}
                                    className="text-xs font-bold px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600">
                                    Clear
                                </button>
                            </div>
                        </div>

                        <div className="divide-y divide-slate-50 max-h-[540px] overflow-y-auto">
                            {students.map(s => {
                                const row = plan[s.student_id] ?? { action: 'skip' as Action, target: '' };
                                const needsTarget = row.action === 'promote' || row.action === 'repeat';

                                return (
                                    <div key={s.student_id} className="px-5 py-3 flex items-center gap-4 flex-wrap">
                                        <div className="flex-1 min-w-[180px]">
                                            <p className="text-sm font-bold text-slate-800">{s.name}</p>
                                            <p className="text-[11px] text-slate-400">
                                                {s.uid} · enrolled {s.enrolled_on}
                                            </p>
                                        </div>

                                        <div className="flex items-center gap-1">
                                            {(Object.keys(ACTION_META) as Action[]).map(a => {
                                                const Icon = ACTION_META[a].icon;
                                                const on = row.action === a;
                                                return (
                                                    <button
                                                        key={a}
                                                        onClick={() => setRow(s.student_id, {
                                                            action: a,
                                                            target: a === 'promote' && nextLevel
                                                                ? String(nextLevel.id)
                                                                : a === 'repeat' && source
                                                                    ? String(source.id)
                                                                    : '',
                                                        })}
                                                        title={ACTION_META[a].label}
                                                        className={`flex items-center gap-1 text-[11px] font-bold px-2.5 py-1.5 rounded-xl transition ${on ? 'text-white' : 'text-slate-500 bg-slate-100 hover:bg-slate-200'}`}
                                                        style={on ? { background: a === 'skip' ? '#94a3b8' : accent } : undefined}
                                                    >
                                                        <Icon className="w-3 h-3" />
                                                        {ACTION_META[a].short}
                                                    </button>
                                                );
                                            })}
                                        </div>

                                        {needsTarget && (
                                            <select
                                                value={row.target}
                                                onChange={e => setRow(s.student_id, { target: e.target.value })}
                                                className={inputCls + ' text-xs max-w-[220px]'}
                                                style={inputStyle}
                                            >
                                                <option value="">Target class…</option>
                                                {classes.map(c => (
                                                    <option key={c.id} value={c.id}>{c.label}</option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* Step 3 — commit */}
                    <div className="bg-white rounded-3xl border border-slate-200 p-5">
                        <div className="flex items-end justify-between flex-wrap gap-4">
                            <div className="flex-1 min-w-[240px]">
                                <label className="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">
                                    Step 3 · Note (optional, saved on every record)
                                </label>
                                <input
                                    value={note}
                                    onChange={e => setNote(e.target.value)}
                                    placeholder={`End of ${academic_year} promotion`}
                                    className={inputCls + ' w-full'}
                                    style={inputStyle}
                                />
                            </div>

                            <div className="text-right">
                                <p className="text-xs text-slate-400 mb-2">
                                    {counts.promote} promote · {counts.repeat} repeat · {counts.graduate} graduate · {counts.skip} leave
                                </p>
                                <button
                                    onClick={submit}
                                    disabled={busy || missingTargets > 0 || counts.skip === students.length}
                                    className="px-6 py-2.5 rounded-2xl text-sm font-bold text-white disabled:opacity-40"
                                    style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.3)}` }}
                                >
                                    {busy ? 'Processing…' : `Apply to ${students.length - counts.skip} student${students.length - counts.skip === 1 ? '' : 's'}`}
                                </button>
                                {missingTargets > 0 && (
                                    <p className="text-[11px] text-amber-600 mt-2">
                                        {missingTargets} row{missingTargets === 1 ? '' : 's'} need a target class
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </>
            )}
        </SchoolLayout>
    );
}
