import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer, FileBadge, AlertCircle, X, CheckCircle2 } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

export interface TranscriptSubject {
    subject_id: number;
    subject: string;
    code: string | null;
    ca: number | null;
    midterm: number | null;
    final: number | null;
    total: number | null;
    letter: string | null;
    gpa_point: number | null;
    complete: boolean;
}

export interface TranscriptTerm {
    term_id: number;
    name: string;
    order: number;
    weight: number | null;
    subjects: TranscriptSubject[];
    average: number | null;
    letter: string | null;
    gpa: number | null;
}

export interface TranscriptYear {
    academic_year: string;
    level_label: string | null;
    status: string | null;
    enrolled_on: string | null;
    ended_on: string | null;
    terms: TranscriptTerm[];
    average: number | null;
    letter: string | null;
    gpa: number | null;
    has_results: boolean;
}

export interface TranscriptData {
    student: {
        id: number;
        uid: string;
        full_name: string;
        full_name_am: string | null;
        gender: string | null;
        date_of_birth: string | null;
        photo_url: string | null;
    };
    years: TranscriptYear[];
    cumulative: {
        average: number | null;
        letter: string | null;
        gpa: number | null;
        years_count: number;
        subjects_count: number;
        marks_count: number;
    };
    scale: { label: string; min_score: number; max_score: number; gpa_point: number }[];
    has_marks: boolean;
    generated_at: string;
}

interface IssuedRow {
    id: number;
    reference: string;
    purpose: string;
    purpose_label: string;
    issued_on: string;
    issued_by: string | null;
    is_revoked: boolean;
}

interface Props {
    transcript: TranscriptData;
    schoolName: string;
    issued: IssuedRow[];
    purposes: { value: string; label: string }[];
    next_reference: string;
    is_admin: boolean;
}

export default function Show({ transcript, schoolName, issued, purposes, next_reference, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const t = transcript;

    const [open, setOpen] = useState(false);
    const [purpose, setPurpose] = useState('records');
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);

    function issue() {
        setBusy(true);
        router.post(`/school/students/${t.student.id}/transcripts`,
            { purpose, note } as never,
            { onFinish: () => setBusy(false) });
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition bg-white';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <SchoolLayout>
            <Head title={`Transcript · ${t.student.full_name}`} />

            {/* Toolbar — hidden when printing */}
            <div className="print:hidden flex items-center justify-between mb-6 flex-wrap gap-3">
                <Link href={`/school/students/${t.student.id}`}
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800">
                    <ArrowLeft className="w-4 h-4" /> Back to student
                </Link>
                <div className="flex items-center gap-2">
                    <button onClick={() => window.print()}
                        className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-slate-600 bg-slate-100 hover:bg-slate-200">
                        <Printer className="w-4 h-4" /> Print
                    </button>
                    {is_admin && (
                        <button onClick={() => setOpen(true)}
                            className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-white"
                            style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}>
                            <FileBadge className="w-4 h-4" /> Issue transcript
                        </button>
                    )}
                </div>
            </div>

            {/* Issue panel */}
            {open && is_admin && (
                <div className="print:hidden mb-5 p-5 rounded-3xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between mb-3">
                        <div>
                            <h2 className="text-sm font-bold text-slate-800">Issue {next_reference}</h2>
                            <p className="text-[11px] text-slate-400">
                                Freezes the record exactly as it reads now. Later mark changes will not alter it.
                            </p>
                        </div>
                        <button onClick={() => setOpen(false)} className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100">
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Purpose</label>
                            <select value={purpose} onChange={e => setPurpose(e.target.value)}
                                className={inputCls} style={inputStyle}>
                                {purposes.map(p => <option key={p.value} value={p.value}>{p.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Note (optional)</label>
                            <input value={note} onChange={e => setNote(e.target.value)}
                                className={inputCls} style={inputStyle} placeholder="Requested by the family…" />
                        </div>
                    </div>

                    <button onClick={issue} disabled={busy}
                        className="mt-3 px-5 py-2.5 rounded-2xl text-sm font-bold text-white disabled:opacity-50"
                        style={{ background: accent }}>
                        {busy ? 'Issuing…' : 'Issue and freeze this record'}
                    </button>
                </div>
            )}

            {/* No marks warning */}
            {!t.has_marks && (
                <div className="print:hidden mb-5 p-4 rounded-2xl border border-amber-200 bg-amber-50 flex items-start gap-3">
                    <AlertCircle className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="text-sm font-bold text-amber-800">No published results yet</p>
                        <p className="text-xs text-amber-700 mt-0.5">
                            The years below are real — taken from enrolment history — but no marks have been published,
                            so averages and grades are blank. Issue this only as a record of study.
                        </p>
                    </div>
                </div>
            )}

            {/* ── The document ── */}
            <div className="bg-white rounded-3xl border border-slate-200 p-8 print:border-0 print:p-0 print:rounded-none max-w-4xl mx-auto">

                {/* Letterhead */}
                <div className="text-center border-b-2 pb-5 mb-6" style={{ borderColor: accent }}>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">{schoolName}</h1>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-slate-400 mt-1">
                        Official academic transcript
                    </p>
                </div>

                {/* Student block */}
                <div className="flex items-start justify-between gap-6 mb-7 flex-wrap">
                    <div className="space-y-1">
                        <h2 className="text-xl font-extrabold text-slate-900">{t.student.full_name}</h2>
                        {t.student.full_name_am && (
                            <p className="text-sm text-slate-500" dir="auto">{t.student.full_name_am}</p>
                        )}
                        <p className="text-xs font-mono text-slate-400">{t.student.uid}</p>
                    </div>
                    <div className="text-right text-xs text-slate-500 space-y-1">
                        {t.student.gender && <p>Gender: <span className="font-semibold text-slate-700 capitalize">{t.student.gender}</span></p>}
                        {t.student.date_of_birth && <p>Date of birth: <span className="font-semibold text-slate-700">{t.student.date_of_birth}</span></p>}
                        <p>Generated: <span className="font-semibold text-slate-700">{t.generated_at}</span></p>
                    </div>
                </div>

                {/* Years */}
                {t.years.length === 0 ? (
                    <p className="text-sm text-slate-400 text-center py-12">No academic history recorded.</p>
                ) : (
                    t.years.map((y, yi) => (
                        <div key={`${y.academic_year}-${yi}`} className="mb-8 break-inside-avoid">
                            <div className="flex items-center justify-between mb-2">
                                <h3 className="text-sm font-extrabold text-slate-800">
                                    {y.academic_year}
                                    {y.level_label && <span className="font-semibold text-slate-500"> · {y.level_label}</span>}
                                </h3>
                                {y.has_results && (
                                    <p className="text-xs text-slate-500">
                                        Year average <span className="font-bold text-slate-800">{y.average ?? '—'}</span>
                                        {y.letter && <span className="ml-1.5 font-bold" style={{ color: accent }}>({y.letter})</span>}
                                        {y.gpa !== null && <span className="ml-2 text-slate-400">GPA {y.gpa.toFixed(2)}</span>}
                                    </p>
                                )}
                            </div>

                            {y.terms.length === 0 ? (
                                <p className="text-xs text-slate-400 italic py-2">Enrolled — no results published for this year.</p>
                            ) : (
                                y.terms.map(term => (
                                    <div key={term.term_id} className="mb-4">
                                        <table className="w-full text-xs border border-slate-200">
                                            <thead>
                                                <tr className="bg-slate-50 text-slate-500">
                                                    <th className="text-left px-3 py-2 font-bold">{term.name}</th>
                                                    <th className="px-2 py-2 font-bold w-16">CA</th>
                                                    <th className="px-2 py-2 font-bold w-16">Mid</th>
                                                    <th className="px-2 py-2 font-bold w-16">Final</th>
                                                    <th className="px-2 py-2 font-bold w-20">Total</th>
                                                    <th className="px-2 py-2 font-bold w-14">Grade</th>
                                                    <th className="px-2 py-2 font-bold w-14">Point</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100">
                                                {term.subjects.map(s => (
                                                    <tr key={s.subject_id}>
                                                        <td className="px-3 py-1.5 font-semibold text-slate-800">
                                                            {s.subject}
                                                            {s.code && <span className="ml-1.5 text-[10px] font-normal text-slate-400">{s.code}</span>}
                                                        </td>
                                                        <td className="px-2 py-1.5 text-center text-slate-600">{s.ca ?? '—'}</td>
                                                        <td className="px-2 py-1.5 text-center text-slate-600">{s.midterm ?? '—'}</td>
                                                        <td className="px-2 py-1.5 text-center text-slate-600">{s.final ?? '—'}</td>
                                                        <td className="px-2 py-1.5 text-center font-bold text-slate-900">{s.total ?? '—'}</td>
                                                        <td className="px-2 py-1.5 text-center font-bold" style={{ color: accent }}>{s.letter ?? '—'}</td>
                                                        <td className="px-2 py-1.5 text-center text-slate-500">
                                                            {s.gpa_point !== null ? s.gpa_point.toFixed(2) : '—'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot>
                                                <tr className="bg-slate-50/70">
                                                    <td className="px-3 py-1.5 font-bold text-slate-600" colSpan={4}>
                                                        Term average
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center font-extrabold text-slate-900">
                                                        {term.average ?? '—'}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center font-bold" style={{ color: accent }}>
                                                        {term.letter ?? '—'}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-center font-bold text-slate-500">
                                                        {term.gpa !== null ? term.gpa.toFixed(2) : '—'}
                                                    </td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                ))
                            )}
                        </div>
                    ))
                )}

                {/* Cumulative summary */}
                <div className="mt-8 pt-5 border-t-2 grid grid-cols-2 sm:grid-cols-4 gap-4" style={{ borderColor: alpha(accent, 0.3) }}>
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Cumulative average</p>
                        <p className="text-xl font-extrabold text-slate-900 mt-0.5">
                            {t.cumulative.average ?? '—'}
                            {t.cumulative.letter && <span className="text-sm ml-1" style={{ color: accent }}>({t.cumulative.letter})</span>}
                        </p>
                    </div>
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Cumulative GPA</p>
                        <p className="text-xl font-extrabold text-slate-900 mt-0.5">
                            {t.cumulative.gpa !== null ? t.cumulative.gpa.toFixed(2) : '—'}
                        </p>
                    </div>
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Years of study</p>
                        <p className="text-xl font-extrabold text-slate-900 mt-0.5">{t.cumulative.years_count}</p>
                    </div>
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Subjects graded</p>
                        <p className="text-xl font-extrabold text-slate-900 mt-0.5">{t.cumulative.subjects_count}</p>
                    </div>
                </div>

                {/* Grading key */}
                {t.scale.length > 0 && (
                    <div className="mt-6 pt-4 border-t border-slate-100">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Grading scale</p>
                        <div className="flex flex-wrap gap-x-4 gap-y-1">
                            {t.scale.map(s => (
                                <span key={s.label} className="text-[11px] text-slate-500">
                                    <span className="font-bold text-slate-700">{s.label}</span> {s.min_score}–{s.max_score}
                                    <span className="text-slate-400"> · {s.gpa_point.toFixed(1)}</span>
                                </span>
                            ))}
                        </div>
                    </div>
                )}

                {/* Signature line */}
                <div className="mt-10 flex items-end justify-between gap-8">
                    <div className="flex-1">
                        <div className="border-b border-slate-300 h-8" />
                        <p className="text-[10px] text-slate-400 mt-1 uppercase tracking-widest">Registrar signature</p>
                    </div>
                    <div className="flex-1">
                        <div className="border-b border-slate-300 h-8" />
                        <p className="text-[10px] text-slate-400 mt-1 uppercase tracking-widest">School stamp / date</p>
                    </div>
                </div>
            </div>

            {/* Issued list */}
            {issued.length > 0 && (
                <div className="print:hidden mt-6 max-w-4xl mx-auto">
                    <h2 className="text-sm font-bold text-slate-800 mb-2">Previously issued</h2>
                    <div className="bg-white rounded-3xl border border-slate-200 divide-y divide-slate-50">
                        {issued.map(i => (
                            <Link key={i.id} href={`/school/students/${t.student.id}/transcripts/${i.id}`}
                                className="flex items-center justify-between px-5 py-3 hover:bg-slate-50 transition">
                                <div>
                                    <p className="text-sm font-bold text-slate-800 flex items-center gap-2">
                                        {i.reference}
                                        {i.is_revoked && (
                                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-50 text-red-600">
                                                Revoked
                                            </span>
                                        )}
                                    </p>
                                    <p className="text-[11px] text-slate-400">
                                        {i.purpose_label} · {i.issued_on}{i.issued_by ? ` · ${i.issued_by}` : ''}
                                    </p>
                                </div>
                                <CheckCircle2 className="w-4 h-4 text-slate-300" />
                            </Link>
                        ))}
                    </div>
                </div>
            )}
        </SchoolLayout>
    );
}
