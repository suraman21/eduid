import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
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

interface Ref { id: number; name: string; }
interface Student {
    id: number;
    name: string;
    uid: string;
    initials: string;
    score: number | string | null;
    max_score: number | string | null;
    status: string | null;
}
interface Props {
    class: Ref;
    subject: Ref;
    term: Ref;
    assessment_type: string;
    assessment_label: string;
    students: Student[];
    is_locked: boolean;
    weight: number | string | null;
}

const STATUS_COLORS: Record<string, string> = {
    draft:     'bg-amber-50 text-amber-600',
    published: 'bg-green-50 text-green-600',
    submitted: 'bg-blue-50 text-blue-600',
};

export default function Enter({
    class: klass, subject, term, assessment_type, assessment_label, students, is_locked, weight,
}: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const { data, setData, post, processing, errors } = useForm<{
        class_id: number;
        subject_id: number;
        term_id: number;
        assessment_type: string;
        grades: { student_id: number; score: number | string; max_score: number | string }[];
    }>({
        class_id: klass.id,
        subject_id: subject.id,
        term_id: term.id,
        assessment_type,
        grades: students.map(s => ({
            student_id: s.id,
            score: s.score ?? '',
            max_score: s.max_score ?? 100,
        })),
    });

    function setGrade(i: number, field: 'score' | 'max_score', value: string) {
        const next = [...data.grades];
        next[i] = { ...next[i], [field]: value };
        setData('grades', next);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/school/grades/save');
    }

    const backUrl = `/school/grades?class_id=${klass.id}&term_id=${term.id}`;
    const firstError = Object.values(errors)[0];
    const inputCls = 'border border-slate-200 rounded-xl px-3 py-1.5 text-sm text-center focus:outline-none focus:ring-2 transition';
    const ringStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <SchoolLayout>
            <Head title="Enter Grades" />

            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">
                    {subject.name} <span className="text-slate-400 font-bold">— {assessment_label}</span>
                </h1>
                <p className="text-sm text-slate-400 mt-1">{klass.name} · {term.name}</p>
            </div>

            {is_locked && (
                <div className="mb-4 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-2xl text-sm font-medium">
                    These grades have been published and cannot be modified.
                </div>
            )}
            {firstError && (
                <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-sm font-medium">
                    {firstError}
                </div>
            )}

            <form onSubmit={submit}>
                <Card className="overflow-hidden">
                    <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <p className="font-bold text-slate-800">{subject.name} · {assessment_label}</p>
                            <p className="text-xs text-slate-400 mt-0.5">{students.length} students · Max score: 100</p>
                        </div>
                        {weight != null && (
                            <p className="text-xs font-bold px-2.5 py-1 rounded-full" style={{ background: alpha(accent, 0.10), color: accent }}>
                                Weight: {weight}%
                            </p>
                        )}
                    </div>

                    <div className="divide-y divide-slate-50">
                        {students.length === 0 ? (
                            <div className="px-5 py-14 text-center text-slate-400 text-sm">No active students in this class.</div>
                        ) : students.map((s, i) => (
                            <div key={s.id} className="px-5 py-3 flex items-center gap-4">
                                <div className="flex items-center gap-3 flex-1 min-w-0">
                                    <div
                                        className="w-9 h-9 rounded-2xl flex items-center justify-center text-xs font-extrabold flex-shrink-0"
                                        style={{ background: alpha(accent, 0.10), color: accent }}
                                    >
                                        {s.initials}
                                    </div>
                                    <div className="min-w-0">
                                        <p className="font-bold text-slate-800 truncate">{s.name}</p>
                                        <p className="text-xs text-slate-400 font-mono">{s.uid}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 flex-shrink-0">
                                    <div className="flex items-center gap-1">
                                        <input
                                            type="number" min={0} max={100} step={0.5} placeholder="Score" required
                                            value={data.grades[i]?.score ?? ''}
                                            disabled={is_locked}
                                            onChange={e => setGrade(i, 'score', e.target.value)}
                                            className={`w-24 ${inputCls} ${is_locked ? 'bg-slate-50 text-slate-400' : ''}`}
                                            style={ringStyle}
                                        />
                                        <span className="text-slate-400 text-sm">/</span>
                                        <input
                                            type="number" min={1} max={100} step={0.5} required
                                            value={data.grades[i]?.max_score ?? 100}
                                            disabled={is_locked}
                                            onChange={e => setGrade(i, 'max_score', e.target.value)}
                                            className={`w-20 ${inputCls} ${is_locked ? 'bg-slate-50 text-slate-400' : ''}`}
                                            style={ringStyle}
                                        />
                                    </div>
                                    {s.status && (
                                        <span className={`text-xs px-2 py-0.5 rounded-full capitalize font-medium ${STATUS_COLORS[s.status] ?? 'bg-slate-100 text-slate-400'}`}>
                                            {s.status}
                                        </span>
                                    )}
                                    {errors[`grades.${i}.score` as keyof typeof errors] && (
                                        <span className="text-xs text-red-500">!</span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                <div className="flex items-center gap-3 mt-6">
                    {!is_locked && (
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-6 py-2.5 rounded-2xl text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                            style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                        >
                            {processing ? 'Saving…' : 'Save Grades'}
                        </button>
                    )}
                    <Link href={backUrl} className="text-sm font-semibold text-slate-500 hover:text-slate-700">← Back</Link>
                </div>
            </form>
        </SchoolLayout>
    );
}
