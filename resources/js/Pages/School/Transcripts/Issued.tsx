import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer, ShieldOff, AlertTriangle, X } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import type { TranscriptData } from './Show';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface IssuedTranscript {
    id: number;
    reference: string;
    purpose: string;
    purpose_label: string;
    issued_on: string;
    issued_by: string | null;
    note: string | null;
    is_revoked: boolean;
    revoked_reason: string | null;
    snapshot: TranscriptData;
}

interface Props {
    transcript: IssuedTranscript;
    schoolName: string;
    student: { id: number; name: string; uid: string };
    is_admin: boolean;
}

export default function Issued({ transcript, schoolName, student, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const t = transcript.snapshot;

    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');

    function revoke() {
        router.post(`/school/students/${student.id}/transcripts/${transcript.id}/revoke`,
            { reason } as never, { onFinish: () => setOpen(false) });
    }

    return (
        <SchoolLayout>
            <Head title={`${transcript.reference} · ${student.name}`} />

            <div className="print:hidden flex items-center justify-between mb-6 flex-wrap gap-3">
                <Link href={`/school/students/${student.id}/transcripts`}
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800">
                    <ArrowLeft className="w-4 h-4" /> Back to transcript
                </Link>
                <div className="flex items-center gap-2">
                    <button onClick={() => window.print()}
                        className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-slate-600 bg-slate-100 hover:bg-slate-200">
                        <Printer className="w-4 h-4" /> Print
                    </button>
                    {is_admin && !transcript.is_revoked && (
                        <button onClick={() => setOpen(true)}
                            className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-red-600 bg-red-50 hover:bg-red-100">
                            <ShieldOff className="w-4 h-4" /> Revoke
                        </button>
                    )}
                </div>
            </div>

            {transcript.is_revoked && (
                <div className="print:hidden mb-5 p-4 rounded-2xl border border-red-200 bg-red-50 flex items-start gap-3">
                    <AlertTriangle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <div>
                        <p className="text-sm font-bold text-red-800">This transcript has been revoked</p>
                        <p className="text-xs text-red-700 mt-0.5">{transcript.revoked_reason}</p>
                    </div>
                </div>
            )}

            {open && is_admin && (
                <div className="print:hidden mb-5 p-5 rounded-3xl border border-slate-200 bg-white">
                    <div className="flex items-center justify-between mb-3">
                        <p className="text-sm font-bold text-slate-800">Revoke {transcript.reference}</p>
                        <button onClick={() => setOpen(false)} className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100">
                            <X className="w-4 h-4" />
                        </button>
                    </div>
                    <input value={reason} onChange={e => setReason(e.target.value)}
                        placeholder="Why is this being revoked?"
                        className="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm mb-3" />
                    <button onClick={revoke}
                        className="px-5 py-2.5 rounded-2xl text-sm font-bold text-white bg-red-600">
                        Revoke transcript
                    </button>
                </div>
            )}

            {transcript.is_revoked && (
                <p className="print:block hidden text-red-600 font-bold text-center mb-4">
                    REVOKED — {transcript.revoked_reason}
                </p>
            )}

            <div className="bg-white rounded-3xl border border-slate-200 p-8 print:border-0 print:p-0 print:rounded-none max-w-4xl mx-auto">

                <div className="text-center border-b-2 pb-5 mb-6" style={{ borderColor: accent }}>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">{schoolName}</h1>
                    <p className="text-xs font-bold uppercase tracking-[0.2em] text-slate-400 mt-1">
                        Official academic transcript
                    </p>
                    <p className="text-[11px] font-mono text-slate-400 mt-2">{transcript.reference}</p>
                </div>

                <div className="flex items-start justify-between gap-6 mb-7 flex-wrap">
                    <div className="space-y-1">
                        <h2 className="text-xl font-extrabold text-slate-900">{t.student.full_name}</h2>
                        {t.student.full_name_am && (
                            <p className="text-sm text-slate-500" dir="auto">{t.student.full_name_am}</p>
                        )}
                        <p className="text-xs font-mono text-slate-400">{t.student.uid}</p>
                    </div>
                    <div className="text-right text-xs text-slate-500 space-y-1">
                        <p>Issued: <span className="font-semibold text-slate-700">{transcript.issued_on}</span></p>
                        <p>Purpose: <span className="font-semibold text-slate-700">{transcript.purpose_label}</span></p>
                        {transcript.issued_by && <p>By: <span className="font-semibold text-slate-700">{transcript.issued_by}</span></p>}
                    </div>
                </div>

                {t.years.map((y, yi) => (
                    <div key={`${y.academic_year}-${yi}`} className="mb-8 break-inside-avoid">
                        <div className="flex items-center justify-between mb-2">
                            <h3 className="text-sm font-extrabold text-slate-800">
                                {y.academic_year}
                                {y.level_label && <span className="font-semibold text-slate-500"> · {y.level_label}</span>}
                            </h3>
                            {y.has_results && (
                                <p className="text-xs text-slate-500">
                                    Average <span className="font-bold text-slate-800">{y.average ?? '—'}</span>
                                    {y.letter && <span className="ml-1.5 font-bold" style={{ color: accent }}>({y.letter})</span>}
                                    {y.gpa !== null && <span className="ml-2 text-slate-400">GPA {y.gpa.toFixed(2)}</span>}
                                </p>
                            )}
                        </div>

                        {y.terms.length === 0 ? (
                            <p className="text-xs text-slate-400 italic py-2">Enrolled — no results published.</p>
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
                                                    <td className="px-3 py-1.5 font-semibold text-slate-800">{s.subject}</td>
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
                                                <td className="px-3 py-1.5 font-bold text-slate-600" colSpan={4}>Term average</td>
                                                <td className="px-2 py-1.5 text-center font-extrabold text-slate-900">{term.average ?? '—'}</td>
                                                <td className="px-2 py-1.5 text-center font-bold" style={{ color: accent }}>{term.letter ?? '—'}</td>
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
                ))}

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
        </SchoolLayout>
    );
}
