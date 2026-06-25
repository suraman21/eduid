import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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

/* ── Card primitive ─────────────────────────────────────────── */
function Card({ children, className = '', style }: { children: React.ReactNode; className?: string; style?: React.CSSProperties }) {
    return (
        <div
            className={`bg-white rounded-3xl border border-slate-200/60 ${className}`}
            style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)', ...style }}
        >
            {children}
        </div>
    );
}

interface SchoolClassEdit {
    id: number;
    grade: string;
    section: string;
    academic_year: string;
    level_order: number;
    is_active: boolean;
    display_name: string;
}

interface Props {
    schoolClass: SchoolClassEdit | null;
    academic_year: string;
}

export default function Form({ schoolClass, academic_year }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const isEdit = schoolClass !== null;

    const { data, setData, post, put, processing, errors } = useForm({
        grade:         schoolClass?.grade ?? '',
        section:       schoolClass?.section ?? '',
        academic_year: schoolClass?.academic_year ?? academic_year ?? '',
        level_order:   String(schoolClass?.level_order ?? 0),
        is_active:     schoolClass?.is_active ?? true,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (isEdit) {
            put(`/school/classes/${schoolClass.id}`);
        } else {
            post('/school/classes');
        }
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 transition bg-white';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };
    const labelCls = 'block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5';

    function FieldError({ name }: { name: keyof typeof errors }) {
        return errors[name] ? <p className="text-red-500 text-xs mt-1">{errors[name]}</p> : null;
    }

    return (
        <SchoolLayout>
            <Head title={isEdit ? `Edit ${schoolClass.display_name}` : 'Add Class'} />

            {/* Top bar */}
            <div className="flex items-center justify-between mb-6">
                <Link
                    href="/school/classes"
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" /> Back to Classes
                </Link>
                <h1 className="text-lg font-extrabold text-slate-900">
                    {isEdit ? 'Edit Class' : 'Add Class'}
                </h1>
            </div>

            <form onSubmit={submit} className="max-w-lg">
                <Card className="p-6 space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className={labelCls}>Grade *</label>
                            <input
                                value={data.grade}
                                onChange={e => setData('grade', e.target.value)}
                                placeholder="e.g. Grade 9, KG1"
                                className={inputCls}
                                style={inputStyle}
                                required
                            />
                            <FieldError name="grade" />
                        </div>
                        <div>
                            <label className={labelCls}>Section *</label>
                            <input
                                value={data.section}
                                onChange={e => setData('section', e.target.value)}
                                placeholder="e.g. A, B, C"
                                className={inputCls}
                                style={inputStyle}
                                required
                            />
                            <FieldError name="section" />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className={labelCls}>Academic Year *</label>
                            <input
                                value={data.academic_year}
                                onChange={e => setData('academic_year', e.target.value)}
                                placeholder="e.g. 2016/2017"
                                className={inputCls}
                                style={inputStyle}
                                required
                            />
                            <FieldError name="academic_year" />
                        </div>
                        <div>
                            <label className={labelCls}>Sort Order</label>
                            <input
                                type="number"
                                min={0}
                                value={data.level_order}
                                onChange={e => setData('level_order', e.target.value)}
                                className={inputCls}
                                style={inputStyle}
                            />
                            <p className="text-xs text-slate-400 mt-1">Lower = appears first</p>
                            <FieldError name="level_order" />
                        </div>
                    </div>

                    {isEdit && (
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={e => setData('is_active', e.target.checked)}
                                className="w-4 h-4 rounded border-slate-300"
                                style={{ accentColor: accent }}
                            />
                            <span className="text-sm text-slate-700">Class is active</span>
                        </label>
                    )}
                </Card>

                <div className="flex items-center justify-end gap-3 mt-5">
                    <Link
                        href="/school/classes"
                        className="px-5 py-2.5 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-all"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-6 py-2.5 rounded-2xl text-sm font-bold text-white transition-all disabled:opacity-50"
                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}
                    >
                        {processing ? 'Saving…' : isEdit ? 'Update Class' : 'Create Class'}
                    </button>
                </div>
            </form>
        </SchoolLayout>
    );
}
