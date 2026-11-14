import React, { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Plus, GraduationCap, Users, BookOpen, X, Pencil, Trash2 } from 'lucide-react';
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

interface Programme {
    id: number;
    code: string;
    name: string;
    level_type: string;
    award: string | null;
    duration_months: number | null;
    total_levels: number | null;
    description: string | null;
    is_active: boolean;
    classes_count: number;
    students_count: number;
}

interface LevelType {
    value: string;
    label: string;
}

interface Props {
    programmes: Programme[];
    level_types: LevelType[];
    is_admin: boolean;
}

const LEVEL_COLORS: Record<string, { bg: string; text: string }> = {
    primary:       { bg: '#dbeafe', text: '#1d4ed8' },
    secondary:     { bg: '#e0e7ff', text: '#4338ca' },
    college:       { bg: '#fae8ff', text: '#a21caf' },
    course_center: { bg: '#fef3c7', text: '#b45309' },
    other:         { bg: '#f1f5f9', text: '#475569' },
};

interface FormState {
    code: string;
    name: string;
    level_type: string;
    award: string;
    duration_months: string;
    total_levels: string;
    description: string;
    is_active: boolean;
}

const EMPTY: FormState = {
    code: '',
    name: '',
    level_type: 'primary',
    award: '',
    duration_months: '',
    total_levels: '',
    description: '',
    is_active: true,
};

export default function Index({ programmes, level_types, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<Programme | null>(null);

    const { data, setData, processing, errors, reset } = useForm<FormState>(EMPTY);

    function startCreate() {
        reset();
        setEditing(null);
        setOpen(true);
    }

    function startEdit(p: Programme) {
        setEditing(p);
        setData({
            code: p.code,
            name: p.name,
            level_type: p.level_type,
            award: p.award ?? '',
            duration_months: p.duration_months ? String(p.duration_months) : '',
            total_levels: p.total_levels ? String(p.total_levels) : '',
            description: p.description ?? '',
            is_active: p.is_active,
        });
        setOpen(true);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();

        const payload = {
            ...data,
            duration_months: data.duration_months ? Number(data.duration_months) : null,
            total_levels: data.total_levels ? Number(data.total_levels) : null,
        };

        if (editing) {
            router.patch(`/school/programmes/${editing.id}`, payload as never, {
                preserveScroll: true,
                onSuccess: () => { setOpen(false); setEditing(null); reset(); },
            });
        } else {
            router.post('/school/programmes', payload as never, {
                preserveScroll: true,
                onSuccess: () => { setOpen(false); reset(); },
            });
        }
    }

    function destroy(p: Programme) {
        if (!confirm(`Delete "${p.name}"?`)) return;
        router.delete(`/school/programmes/${p.id}`, { preserveScroll: true });
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <SchoolLayout>
            <Head title="Programmes" />

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Programmes</h1>
                    <p className="text-xs text-slate-400 mt-0.5">
                        What students study — a grade stream, a diploma, or a course. Classes sit inside a programme.
                    </p>
                </div>
                {is_admin && (
                    <button
                        onClick={startCreate}
                        className="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-2xl transition"
                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}
                    >
                        <Plus className="w-4 h-4" /> Add Programme
                    </button>
                )}
            </div>

            {open && (
                <form onSubmit={submit} className="mb-5 p-5 rounded-3xl border border-slate-200 bg-white space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-bold text-slate-800">
                            {editing ? `Edit ${editing.name}` : 'New programme'}
                        </h2>
                        <button type="button" onClick={() => { setOpen(false); setEditing(null); }}
                            className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100">
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Code *</label>
                            <input value={data.code} onChange={e => setData('code', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle}
                                placeholder="PRIM, DIP-ICT…" required />
                            {errors.code && <p className="text-red-500 text-xs mt-1">{errors.code}</p>}
                        </div>
                        <div className="sm:col-span-2">
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Name *</label>
                            <input value={data.name} onChange={e => setData('name', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle}
                                placeholder="Primary, Diploma in ICT…" required />
                            {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Type *</label>
                            <select value={data.level_type} onChange={e => setData('level_type', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle}>
                                {level_types.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Award</label>
                            <input value={data.award} onChange={e => setData('award', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle}
                                placeholder="Certificate, Diploma, Degree…" />
                        </div>
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Duration (months)</label>
                            <input type="number" min={1} max={96} value={data.duration_months}
                                onChange={e => setData('duration_months', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle} placeholder="36" />
                        </div>
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Levels</label>
                            <input type="number" min={1} max={20} value={data.total_levels}
                                onChange={e => setData('total_levels', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle} placeholder="12" />
                        </div>
                        <div className="sm:col-span-2">
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Description</label>
                            <input value={data.description} onChange={e => setData('description', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle} />
                        </div>
                    </div>

                    <div className="flex items-center gap-2 pt-1">
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 rounded-2xl text-sm font-bold text-white disabled:opacity-50"
                            style={{ background: accent }}>
                            {processing ? 'Saving…' : editing ? 'Save changes' : 'Create programme'}
                        </button>
                        <button type="button" onClick={() => { setOpen(false); setEditing(null); reset(); }}
                            className="px-4 py-2 rounded-2xl text-sm font-semibold text-slate-500 hover:bg-slate-100">
                            Cancel
                        </button>
                    </div>
                </form>
            )}

            {programmes.length === 0 ? (
                <div className="bg-white rounded-3xl border border-slate-200 p-12 text-center">
                    <GraduationCap className="w-10 h-10 text-slate-200 mx-auto mb-3" />
                    <p className="text-sm font-semibold text-slate-500">No programmes yet</p>
                    <p className="text-xs text-slate-400 mt-1">Create one to start organising classes.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    {programmes.map(p => {
                        const lv = LEVEL_COLORS[p.level_type] ?? LEVEL_COLORS.other;
                        return (
                            <div key={p.id} className="bg-white rounded-3xl border border-slate-200 p-5 flex flex-col">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <h3 className="text-base font-extrabold text-slate-900 truncate">{p.name}</h3>
                                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                style={{ background: lv.bg, color: lv.text }}>
                                                {level_types.find(t => t.value === p.level_type)?.label ?? p.level_type}
                                            </span>
                                        </div>
                                        <p className="text-[11px] font-mono text-slate-400 mt-0.5">{p.code}</p>
                                    </div>
                                    {is_admin && (
                                        <div className="flex items-center gap-1 flex-shrink-0">
                                            <button onClick={() => startEdit(p)}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-slate-600 hover:bg-slate-50">
                                                <Pencil className="w-3.5 h-3.5" />
                                            </button>
                                            <button onClick={() => destroy(p)}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50">
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {p.award && (
                                    <p className="text-xs text-slate-500 mt-2">Award: <span className="font-semibold">{p.award}</span></p>
                                )}
                                {p.duration_months && (
                                    <p className="text-xs text-slate-500">Duration: <span className="font-semibold">{p.duration_months} months</span></p>
                                )}

                                <div className="flex items-center gap-4 mt-4 pt-4 border-t border-slate-50">
                                    <div className="flex items-center gap-1.5 text-xs text-slate-500">
                                        <BookOpen className="w-3.5 h-3.5" />
                                        <span className="font-bold text-slate-700">{p.classes_count}</span> classes
                                    </div>
                                    <div className="flex items-center gap-1.5 text-xs text-slate-500">
                                        <Users className="w-3.5 h-3.5" />
                                        <span className="font-bold text-slate-700">{p.students_count}</span> students
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </SchoolLayout>
    );
}
