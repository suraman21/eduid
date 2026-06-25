import React, { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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

type AssessmentType = 'ca' | 'midterm' | 'final';
type CellStatus = 'not_started' | 'draft' | 'partial' | 'published';

interface CellData {
    status: CellStatus;
    entered: number;
    total: number;
}

interface MatrixRow {
    subject_id: number;
    subject_name: string;
    teacher_name: string | null;
    types: Record<AssessmentType, CellData>;
}

interface Option {
    id: number;
    name: string;
    academic_year?: string;
}

interface Props {
    classes: Option[];
    terms: Option[];
    selected_class: { id: number; name: string } | null;
    selected_term: { id: number; name: string } | null;
    matrix: MatrixRow[];
    filters: { class_id?: number | string | null; term_id?: number | string | null };
    is_admin: boolean;
}

const STATUS_COLORS: Record<CellStatus, string> = {
    not_started: 'bg-slate-100 text-slate-400',
    draft: 'bg-amber-50 text-amber-600',
    partial: 'bg-blue-50 text-blue-600',
    published: 'bg-green-50 text-green-600',
};

const TYPES: AssessmentType[] = ['ca', 'midterm', 'final'];

function PublishButton({
    classId,
    subjectId,
    termId,
    type,
}: {
    classId: number;
    subjectId: number;
    termId: number;
    type: AssessmentType;
}) {
    const { post, processing } = useForm({
        class_id: classId,
        subject_id: subjectId,
        term_id: termId,
        assessment_type: type,
    });

    return (
        <button
            type="button"
            disabled={processing}
            onClick={() => post('/school/grades/publish')}
            className="text-xs font-semibold text-green-600 hover:text-green-800 disabled:opacity-50"
        >
            Publish
        </button>
    );
}

export default function Index({
    classes,
    terms,
    selected_class,
    selected_term,
    matrix,
    filters,
    is_admin,
}: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const [classId, setClassId] = useState(String(filters.class_id ?? ''));
    const [termId, setTermId] = useState(String(filters.term_id ?? ''));

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/school/grades', { class_id: classId, term_id: termId }, { preserveState: true });
    };

    const hasSelection = selected_class && selected_term;

    return (
        <SchoolLayout>
            <Head title="Grades" />
            <div className="p-6 lg:p-8">
                <div className="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-800">Grades</h1>
                        <p className="text-slate-500 text-sm mt-1">
                            {hasSelection
                                ? `${selected_class!.name} · ${selected_term!.name}`
                                : 'Select a class and term to manage grades'}
                        </p>
                    </div>
                    {is_admin && (
                        <Link
                            href="/school/grades/terms"
                            className="text-sm font-semibold hover:opacity-80 transition-opacity"
                            style={{ color: accent }}
                        >
                            Manage Terms
                        </Link>
                    )}
                </div>

                {/* Filter form */}
                <Card className="p-5 mb-6">
                    <form onSubmit={submit} className="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Class</label>
                            <select
                                value={classId}
                                onChange={e => setClassId(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2"
                                style={{ ['--tw-ring-color' as string]: alpha(accent, 0.35) }}
                            >
                                <option value="">Select class…</option>
                                {classes.map(c => (
                                    <option key={c.id} value={String(c.id)}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Term</label>
                            <select
                                value={termId}
                                onChange={e => setTermId(e.target.value)}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2"
                                style={{ ['--tw-ring-color' as string]: alpha(accent, 0.35) }}
                            >
                                <option value="">Select term…</option>
                                {terms.map(t => (
                                    <option key={t.id} value={String(t.id)}>
                                        {t.name} ({t.academic_year})
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <button
                                type="submit"
                                className="w-full py-2 rounded-xl text-sm font-semibold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                            >
                                Load
                            </button>
                        </div>
                        {hasSelection && (
                            <div className="flex justify-end">
                                <Link
                                    href={`/school/grades/gradebook?class_id=${selected_class!.id}&term_id=${selected_term!.id}`}
                                    className="w-full sm:w-auto py-2 px-4 rounded-xl text-sm font-semibold text-white text-center transition-opacity hover:opacity-90"
                                    style={{ backgroundColor: accent }}
                                >
                                    Gradebook
                                </Link>
                            </div>
                        )}
                    </form>
                </Card>

                {terms.length === 0 ? (
                    <Card className="p-12 text-center text-slate-400">
                        <p className="font-medium text-slate-500 mb-1">No terms set up yet</p>
                        {is_admin && (
                            <Link href="/school/grades/terms" className="text-sm mt-2 inline-block" style={{ color: accent }}>
                                Manage terms &rarr;
                            </Link>
                        )}
                    </Card>
                ) : hasSelection ? (
                    matrix.length === 0 ? (
                        <Card className="p-12 text-center text-slate-400">
                            <p>No subjects assigned to {selected_class!.name}.</p>
                            <Link href="/school/subjects" className="text-sm mt-1 inline-block" style={{ color: accent }}>
                                Manage subjects &rarr;
                            </Link>
                        </Card>
                    ) : (
                        <Card className="overflow-hidden">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 bg-slate-50 text-left">
                                        <th className="px-5 py-3 text-xs font-semibold text-slate-400 uppercase">Subject</th>
                                        <th className="px-5 py-3 text-xs font-semibold text-slate-400 uppercase text-center">CA</th>
                                        <th className="px-5 py-3 text-xs font-semibold text-slate-400 uppercase text-center">Midterm</th>
                                        <th className="px-5 py-3 text-xs font-semibold text-slate-400 uppercase text-center">Final</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {matrix.map(row => (
                                        <tr key={row.subject_id} className="hover:bg-slate-50 transition">
                                            <td className="px-5 py-3">
                                                <p className="font-medium text-slate-900">{row.subject_name}</p>
                                                {row.teacher_name && (
                                                    <p className="text-xs text-slate-400">{row.teacher_name}</p>
                                                )}
                                            </td>
                                            {TYPES.map(type => {
                                                const td = row.types[type];
                                                const label =
                                                    td.status === 'not_started'
                                                        ? '—'
                                                        : td.status === 'published'
                                                          ? 'Published'
                                                          : `${td.entered}/${td.total}`;
                                                const canEdit = td.status !== 'published';
                                                const canPublish =
                                                    (td.status === 'draft' || td.status === 'partial') && is_admin;
                                                return (
                                                    <td key={type} className="px-5 py-3 text-center">
                                                        <span className={`text-xs px-2 py-0.5 rounded-full ${STATUS_COLORS[td.status]}`}>
                                                            {label}
                                                        </span>
                                                        <div className="flex justify-center gap-2 mt-1">
                                                            {canEdit && (
                                                                <Link
                                                                    href={`/school/grades/enter?class_id=${selected_class!.id}&subject_id=${row.subject_id}&term_id=${selected_term!.id}&assessment_type=${type}`}
                                                                    className="text-xs font-semibold hover:opacity-80"
                                                                    style={{ color: accent }}
                                                                >
                                                                    {td.status === 'not_started' ? 'Enter' : 'Edit'}
                                                                </Link>
                                                            )}
                                                            {canPublish && (
                                                                <PublishButton
                                                                    classId={selected_class!.id}
                                                                    subjectId={row.subject_id}
                                                                    termId={selected_term!.id}
                                                                    type={type}
                                                                />
                                                            )}
                                                        </div>
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>
                    )
                ) : null}
            </div>
        </SchoolLayout>
    );
}
