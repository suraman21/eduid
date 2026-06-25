import React, { useState } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

interface SubjectRow {
    id: number;
    name: string;
    code: string;
    color: string;
    is_active: boolean;
}

interface Props extends PageProps {
    subjects: SubjectRow[];
    is_admin: boolean;
}

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

function AddForm({ accent }: { accent: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        code: '',
        color: '#6366f1',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/school/subjects', { onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Subject Name</label>
                <input
                    type="text"
                    value={data.name}
                    onChange={e => setData('name', e.target.value)}
                    className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    placeholder="e.g. Mathematics"
                    required
                />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Code</label>
                <input
                    type="text"
                    value={data.code}
                    onChange={e => setData('code', e.target.value)}
                    className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    placeholder="e.g. MATH"
                    required
                />
                {errors.code && <p className="text-red-500 text-xs mt-1">{errors.code}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Color</label>
                <div className="flex items-center gap-3">
                    <input
                        type="color"
                        value={data.color}
                        onChange={e => setData('color', e.target.value)}
                        className="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer"
                    />
                    <span className="text-sm font-mono text-slate-500">{data.color}</span>
                </div>
            </div>
            <button
                type="submit"
                disabled={processing}
                className="w-full py-2.5 rounded-xl text-sm font-semibold text-white transition-opacity disabled:opacity-60"
                style={{ backgroundColor: accent }}
            >
                {processing ? 'Adding…' : 'Add Subject'}
            </button>
        </form>
    );
}

function EditForm({ subject, accent, onCancel }: { subject: SubjectRow; accent: string; onCancel: () => void }) {
    const { data, setData, put, processing, errors } = useForm({
        name: subject.name,
        code: subject.code,
        color: normHex(subject.color),
        is_active: subject.is_active,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/school/subjects/${subject.id}`, { onSuccess: onCancel });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Subject Name</label>
                <input
                    type="text"
                    value={data.name}
                    onChange={e => setData('name', e.target.value)}
                    className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    required
                />
                {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Code</label>
                <input
                    type="text"
                    value={data.code}
                    onChange={e => setData('code', e.target.value)}
                    className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    required
                />
                {errors.code && <p className="text-red-500 text-xs mt-1">{errors.code}</p>}
            </div>
            <div>
                <label className="block text-sm font-medium text-slate-700 mb-1">Color</label>
                <div className="flex items-center gap-3">
                    <input
                        type="color"
                        value={data.color}
                        onChange={e => setData('color', e.target.value)}
                        className="w-10 h-10 rounded-lg border border-slate-200 cursor-pointer"
                    />
                    <span className="text-sm font-mono text-slate-500">{data.color}</span>
                </div>
            </div>
            <div className="flex items-center gap-3">
                <input
                    type="checkbox"
                    id="is_active"
                    checked={data.is_active}
                    onChange={e => setData('is_active', e.target.checked)}
                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-300"
                />
                <label htmlFor="is_active" className="text-sm font-medium text-slate-700">Active</label>
            </div>
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={processing}
                    className="flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition-opacity disabled:opacity-60"
                    style={{ backgroundColor: accent }}
                >
                    {processing ? 'Updating…' : 'Update Subject'}
                </button>
                <button
                    type="button"
                    onClick={onCancel}
                    className="flex-1 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors"
                >
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function Index({ subjects, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex((props as any).school?.accent_color);
    const [editingSubject, setEditingSubject] = useState<SubjectRow | null>(null);

    return (
        <SchoolLayout>
            <div className="p-6 lg:p-8">
                <div className="mb-6">
                    <h1 className="text-2xl font-bold text-slate-800">Subjects</h1>
                    <p className="text-slate-500 text-sm mt-1">Manage school subjects</p>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left: Subject List */}
                    <div className="lg:col-span-2">
                        <Card className="overflow-hidden">
                            <div className="px-6 py-4 border-b border-slate-100">
                                <h2 className="font-semibold text-slate-700">All Subjects</h2>
                            </div>
                            {subjects.length === 0 ? (
                                <div className="py-16 text-center text-slate-400">
                                    <p className="text-lg font-medium">No subjects yet</p>
                                    <p className="text-sm mt-1">Add your first subject using the form.</p>
                                </div>
                            ) : (
                                <ul className="divide-y divide-slate-100">
                                    {subjects.map(subject => (
                                        <li key={subject.id} className="flex items-center gap-4 px-6 py-4">
                                            <span
                                                className="w-3 h-3 rounded-full flex-shrink-0"
                                                style={{ backgroundColor: normHex(subject.color) }}
                                            />
                                            <div className="flex-1 min-w-0">
                                                <p className="font-bold text-slate-800 truncate">{subject.name}</p>
                                                <p className="font-mono text-xs text-slate-500 mt-0.5">{subject.code}</p>
                                            </div>
                                            <span
                                                className={`px-2.5 py-1 rounded-full text-xs font-semibold ${
                                                    subject.is_active
                                                        ? 'bg-green-100 text-green-700'
                                                        : 'bg-slate-100 text-slate-500'
                                                }`}
                                            >
                                                {subject.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                            {is_admin && (
                                                <button
                                                    onClick={() => setEditingSubject(subject)}
                                                    className="text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
                                                    style={{
                                                        backgroundColor: alpha(accent, 0.1),
                                                        color: accent,
                                                    }}
                                                >
                                                    Edit
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    </div>

                    {/* Right: Add/Edit Panel */}
                    {is_admin && (
                        <div className="lg:col-span-1">
                            <Card className="p-6">
                                <h2 className="font-semibold text-slate-700 mb-4">
                                    {editingSubject ? 'Edit Subject' : 'Add Subject'}
                                </h2>
                                {editingSubject ? (
                                    <EditForm
                                        key={editingSubject.id}
                                        subject={editingSubject}
                                        accent={accent}
                                        onCancel={() => setEditingSubject(null)}
                                    />
                                ) : (
                                    <AddForm accent={accent} />
                                )}
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </SchoolLayout>
    );
}
