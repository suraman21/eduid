import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface Ref { id: number; name: string; }
interface Subject { id: number; code: string; name: string; }
interface SubjectTotal { subject_id: number; total: number | null; published: boolean; }
interface Row {
    student_name: string;
    student_uid: string;
    rank: number | null;
    term_avg: number | null;
    subject_totals: SubjectTotal[];
}
interface Props {
    class: Ref;
    term: Ref;
    subjects: Subject[];
    rows: Row[];
}

export default function Gradebook({ class: klass, term, subjects, rows }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const backUrl = `/school/grades?class_id=${klass.id}&term_id=${term.id}`;
    const avgBg = alpha(accent, 0.08);

    return (
        <SchoolLayout>
            <Head title="Gradebook" />

            <div className="mb-5">
                <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Gradebook</h1>
                <p className="text-sm text-slate-400 mt-1">{klass.name} · {term.name}</p>
            </div>

            <div className="flex items-center justify-between mb-4">
                <Link href={backUrl} className="text-sm font-semibold text-slate-500 hover:text-slate-700">← Back to grade entry</Link>
                <p className="text-sm text-slate-400">{rows.length} students · {subjects.length} subjects</p>
            </div>

            {rows.length === 0 ? (
                <div className="bg-white rounded-3xl border border-slate-200/60 p-12 text-center text-slate-400" style={{ boxShadow: '0 12px 32px rgba(15,23,42,0.05)' }}>
                    <p>No active students in this class.</p>
                </div>
            ) : (
                <>
                    <div className="bg-white rounded-3xl border border-slate-200/60 overflow-hidden" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm whitespace-nowrap">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left">
                                        <th className="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider sticky left-0 bg-white z-10">Rank</th>
                                        <th className="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider sticky left-12 bg-white z-10 min-w-[170px]">Student</th>
                                        {subjects.map(s => (
                                            <th key={s.id} className="px-3 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center min-w-[80px]" title={s.name}>{s.code}</th>
                                        ))}
                                        <th className="px-4 py-3.5 text-xs font-semibold uppercase tracking-wider text-center sticky right-0" style={{ background: avgBg, color: accent }}>Average</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {rows.map((row, ri) => {
                                        const totalsById = new Map(row.subject_totals.map(st => [Number(st.subject_id), st]));
                                        return (
                                            <tr key={ri} className="hover:bg-slate-50/70 transition">
                                                <td className="px-4 py-2.5 text-slate-400 text-xs sticky left-0 bg-white font-semibold">
                                                    {row.rank !== null ? `#${row.rank}` : '—'}
                                                </td>
                                                <td className="px-4 py-2.5 sticky left-12 bg-white min-w-[170px]">
                                                    <p className="font-bold text-slate-800">{row.student_name}</p>
                                                    <p className="text-xs text-slate-400 font-mono">{row.student_uid}</p>
                                                </td>
                                                {subjects.map(s => {
                                                    const st = totalsById.get(s.id);
                                                    return (
                                                        <td key={s.id} className="px-3 py-2.5 text-center">
                                                            {st && st.total !== null ? (
                                                                <>
                                                                    <span className={`font-bold ${st.total >= 50 ? 'text-slate-900' : 'text-red-600'}`}>
                                                                        {st.total.toFixed(1)}
                                                                    </span>
                                                                    {!st.published && <span className="block text-[10px] text-amber-500 font-semibold">draft</span>}
                                                                </>
                                                            ) : (
                                                                <span className="text-slate-300">—</span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                                <td className="px-4 py-2.5 text-center sticky right-0 font-extrabold" style={{ background: avgBg }}>
                                                    {row.term_avg !== null ? (
                                                        <span style={{ color: row.term_avg >= 50 ? accent : '#dc2626' }}>{row.term_avg.toFixed(1)}</span>
                                                    ) : (
                                                        <span className="text-slate-300">—</span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="mt-3 flex flex-wrap gap-4 text-xs text-slate-400">
                        <span className="flex items-center gap-1"><span className="text-amber-500 font-semibold">draft</span> = grades saved but not yet published</span>
                        <span className="flex items-center gap-1"><span className="text-red-600 font-semibold">red score</span> = below 50%</span>
                        <span className="flex items-center gap-1"><span className="text-slate-300">—</span> = not all assessments entered</span>
                    </div>
                </>
            )}
        </SchoolLayout>
    );
}
