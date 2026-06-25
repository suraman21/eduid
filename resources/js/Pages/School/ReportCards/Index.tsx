import React from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { FileText } from 'lucide-react';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface Ref { id: number; name: string; }
interface Term extends Ref { academic_year: string; }
interface StudentRow {
    id: number;
    full_name: string;
    full_name_am: string | null;
    uid: string;
    photo_url: string | null;
    initials: string;
}
interface Props {
    classes: Ref[];
    terms: Term[];
    filters: { class_id: string | null; term_id: string | null };
    class: Ref | null;
    term: Term | null;
    students: StudentRow[];
}

export default function Index({ classes, terms, filters, class: klass, term, students }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const ringStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };
    const inputCls = 'border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 bg-white transition';

    function applyFilters(classId: string, termId: string) {
        if (classId && termId) {
            router.get('/school/report-cards', { class_id: classId, term_id: termId }, { preserveState: false });
        }
    }

    function onClassChange(e: React.ChangeEvent<HTMLSelectElement>) {
        const termId = filters.term_id ?? '';
        applyFilters(e.target.value, termId);
    }

    function onTermChange(e: React.ChangeEvent<HTMLSelectElement>) {
        const classId = filters.class_id ?? '';
        applyFilters(classId, e.target.value);
    }

    return (
        <SchoolLayout>
            <Head title="Report Cards" />

            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Report Cards</h1>
                <p className="text-sm text-slate-400 mt-1">Select a class and term to view student report cards</p>
            </div>

            {/* Filters */}
            <div className="bg-white rounded-3xl border border-slate-200/60 p-4 mb-5 flex flex-wrap items-center gap-3" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04)' }}>
                <select
                    value={filters.class_id ?? ''}
                    onChange={onClassChange}
                    className={inputCls}
                    style={ringStyle}
                >
                    <option value="">Select Class…</option>
                    {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <select
                    value={filters.term_id ?? ''}
                    onChange={onTermChange}
                    className={inputCls}
                    style={ringStyle}
                >
                    <option value="">Select Term…</option>
                    {terms.map(t => <option key={t.id} value={t.id}>{t.name} ({t.academic_year})</option>)}
                </select>
                {(filters.class_id || filters.term_id) && (
                    <Link href="/school/report-cards" className="text-sm text-slate-400 hover:text-slate-600">Clear</Link>
                )}
            </div>

            {klass && term ? (
                <>
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-base font-bold text-slate-700">{klass.name} — {term.name} ({term.academic_year})</h2>
                        <span className="text-xs text-slate-400">{students.length} active student{students.length !== 1 ? 's' : ''}</span>
                    </div>

                    {students.length === 0 ? (
                        <div className="bg-white rounded-3xl border border-slate-200/60 p-12 text-center text-slate-400" style={{ boxShadow: '0 12px 32px rgba(15,23,42,0.05)' }}>
                            <p>No active students in this class.</p>
                        </div>
                    ) : (
                        <div className="bg-white rounded-3xl border border-slate-200/60 overflow-hidden" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left">
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">#</th>
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Student</th>
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">UID</th>
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {students.map((s, i) => (
                                        <tr key={s.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-5 py-3 text-slate-400 text-xs">{i + 1}</td>
                                            <td className="px-5 py-3">
                                                <div className="flex items-center gap-3">
                                                    {s.photo_url ? (
                                                        <img src={s.photo_url} className="w-8 h-8 rounded-2xl object-cover" alt="" style={{ border: `2px solid ${alpha(accent, 0.18)}` }} />
                                                    ) : (
                                                        <div className="w-8 h-8 rounded-2xl flex items-center justify-center text-xs font-extrabold" style={{ background: alpha(accent, 0.10), color: accent }}>
                                                            {s.initials}
                                                        </div>
                                                    )}
                                                    <div>
                                                        <p className="font-bold text-slate-800">{s.full_name}</p>
                                                        {s.full_name_am && <p className="text-xs text-slate-400">{s.full_name_am}</p>}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-5 py-3 font-mono text-xs text-slate-500">{s.uid}</td>
                                            <td className="px-5 py-3 text-right">
                                                <Link
                                                    href={`/school/report-cards/${s.id}?term_id=${term.id}`}
                                                    className="inline-flex items-center gap-1 text-xs font-bold"
                                                    style={{ color: accent }}
                                                >
                                                    View Report Card →
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </>
            ) : (
                <div className="bg-white rounded-3xl border border-slate-200/60 p-12 text-center" style={{ boxShadow: '0 12px 32px rgba(15,23,42,0.05)' }}>
                    <FileText className="w-10 h-10 mx-auto mb-3 text-slate-200" />
                    <p className="text-slate-400">Select a class and term to view report cards</p>
                </div>
            )}
        </SchoolLayout>
    );
}
