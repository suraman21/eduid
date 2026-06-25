import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { Printer, ArrowLeft } from 'lucide-react';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface GradeEntry { score: number; max: number; }
interface SubjectRow {
    subject_name: string;
    ca: GradeEntry | null;
    midterm: GradeEntry | null;
    final: GradeEntry | null;
    total: number | null;
    letter: string | null;
}
interface Props {
    student: {
        id: number; full_name: string; full_name_am: string | null;
        uid: string; gender: string; class_name: string | null; date_of_birth: string | null;
    };
    term: { id: number; name: string; academic_year: string };
    school: { name: string; address: string | null };
    term_avg: number | null;
    rank: number | null;
    class_size: number | null;
    weight_labels: { ca: string; midterm: string; final: string };
    subject_rows: SubjectRow[];
    generated_at: string;
}

function fmt(entry: GradeEntry | null) {
    if (!entry) return <span className="text-slate-300">—</span>;
    return <span>{entry.score.toFixed(1)}/{entry.max.toFixed(0)}</span>;
}

export default function Show({ student, term, school, term_avg, rank, class_size, weight_labels, subject_rows, generated_at }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    return (
        <SchoolLayout>
            <Head title={`${student.full_name} — Report Card`} />

            {/* Controls (hidden when printing) */}
            <div className="flex items-center justify-between mb-6 print:hidden">
                <Link href="/school/report-cards" className="flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-700">
                    <ArrowLeft className="w-4 h-4" />
                    Back to class list
                </Link>
                <button
                    onClick={() => window.print()}
                    className="flex items-center gap-1.5 px-4 py-2.5 text-sm font-bold text-white rounded-2xl transition hover:-translate-y-0.5"
                    style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                >
                    <Printer className="w-4 h-4" />
                    Print
                </button>
            </div>

            {/* Report card body */}
            <div className="bg-white rounded-3xl border border-slate-200/60 p-8 max-w-3xl mx-auto print:shadow-none print:border-none print:rounded-none print:p-4"
                style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>

                {/* Header */}
                <div className="text-center mb-6 pb-5 border-b border-slate-200">
                    <p className="text-lg font-bold text-slate-800">{school.name}</p>
                    {school.address && <p className="text-xs text-slate-400 mt-0.5">{school.address}</p>}
                    <h1 className="mt-3 text-xl font-extrabold tracking-wide text-slate-900 uppercase">Student Report Card</h1>
                    <p className="text-sm text-slate-500 mt-1">{term.name} — {term.academic_year}</p>
                </div>

                {/* Student info */}
                <div className="grid grid-cols-2 gap-4 mb-6 text-sm">
                    {[
                        { label: 'Student Name', value: <>{student.full_name}{student.full_name_am && <span className="block text-xs text-slate-400">{student.full_name_am}</span>}</> },
                        { label: 'Student ID', value: <span className="font-mono">{student.uid}</span> },
                        { label: 'Class', value: student.class_name ?? '—' },
                        { label: 'Gender', value: <span className="capitalize">{student.gender}</span> },
                        ...(student.date_of_birth ? [{ label: 'Date of Birth', value: student.date_of_birth }] : []),
                    ].map(({ label, value }) => (
                        <div key={label}>
                            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">{label}</p>
                            <p className="font-medium text-slate-800">{value}</p>
                        </div>
                    ))}
                </div>

                {/* Grades table */}
                <div className="rounded-2xl border border-slate-200 overflow-hidden mb-6">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50">
                            <tr className="text-left">
                                <th className="px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase">Subject</th>
                                <th className="px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase text-center">{weight_labels.ca}</th>
                                <th className="px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase text-center">{weight_labels.midterm}</th>
                                <th className="px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase text-center">{weight_labels.final}</th>
                                <th className="px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase text-center">Total %</th>
                                <th className="px-4 py-2.5 text-xs font-semibold text-slate-500 uppercase text-center">Grade</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {subject_rows.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-slate-400">No published grades for this term yet.</td>
                                </tr>
                            ) : subject_rows.map((row, i) => (
                                <tr key={row.subject_name} className={i % 2 === 1 ? 'bg-slate-50/50' : ''}>
                                    <td className="px-4 py-2 font-semibold text-slate-800">{row.subject_name}</td>
                                    <td className="px-4 py-2 text-center text-slate-600">{fmt(row.ca)}</td>
                                    <td className="px-4 py-2 text-center text-slate-600">{fmt(row.midterm)}</td>
                                    <td className="px-4 py-2 text-center text-slate-600">{fmt(row.final)}</td>
                                    <td className={`px-4 py-2 text-center font-semibold ${row.total !== null ? (row.total >= 50 ? 'text-green-700' : 'text-red-600') : 'text-slate-300'}`}>
                                        {row.total !== null ? `${row.total.toFixed(1)}%` : '—'}
                                    </td>
                                    <td className="px-4 py-2 text-center">
                                        {row.letter ? (
                                            <span className="inline-block px-2 py-0.5 rounded text-xs font-bold" style={{ background: alpha(accent, 0.10), color: accent }}>
                                                {row.letter}
                                            </span>
                                        ) : (
                                            <span className="text-slate-300">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        {term_avg !== null && (
                            <tfoot style={{ background: alpha(accent, 0.06), borderTop: `2px solid ${alpha(accent, 0.25)}` }}>
                                <tr>
                                    <td colSpan={4} className="px-4 py-2 text-sm font-bold text-right" style={{ color: accent }}>Term Average</td>
                                    <td className="px-4 py-2 text-center text-sm font-extrabold" style={{ color: accent }}>{term_avg.toFixed(1)}%</td>
                                    <td />
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>

                {/* Summary tiles */}
                {term_avg !== null && (
                    <div className="flex flex-wrap gap-4 mb-6">
                        <div className="flex-1 min-w-[100px] rounded-2xl p-3 text-center" style={{ background: alpha(accent, 0.08) }}>
                            <p className="text-xs font-bold uppercase tracking-wider mb-0.5" style={{ color: alpha(accent, 0.70) }}>Term Average</p>
                            <p className="text-2xl font-extrabold" style={{ color: accent }}>{term_avg.toFixed(1)}%</p>
                        </div>
                        {rank !== null && (
                            <div className="flex-1 min-w-[100px] rounded-2xl p-3 text-center bg-emerald-50">
                                <p className="text-xs font-bold uppercase tracking-wider text-emerald-400 mb-0.5">Class Rank</p>
                                <p className="text-2xl font-extrabold text-emerald-700">
                                    {rank}<span className="text-sm font-normal text-emerald-500"> / {class_size}</span>
                                </p>
                            </div>
                        )}
                    </div>
                )}

                {/* Signature lines */}
                <div className="mt-8 pt-4 border-t border-slate-200 grid grid-cols-3 gap-8 text-xs text-slate-400">
                    {['Class Teacher', 'School Principal', 'Parent / Guardian'].map(label => (
                        <div key={label} className="text-center">
                            <div className="border-b border-slate-300 mb-1 h-8" />
                            {label}
                        </div>
                    ))}
                </div>

                <p className="mt-4 text-center text-xs text-slate-300">Generated {generated_at} · EduID</p>
            </div>

            <style>{`
                @media print {
                    .print\\:hidden { display: none !important; }
                    body { background: white; }
                }
            `}</style>
        </SchoolLayout>
    );
}
