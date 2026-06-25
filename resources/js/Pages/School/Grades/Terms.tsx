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

interface Term {
    id: number;
    name: string;
    academic_year: string;
    order: number;
    weight: number | string | null;
    is_current: boolean;
}
interface Props {
    terms: Term[];
    next_order: number;
}

export default function Terms({ terms, next_order }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        academic_year: string;
        start_date: string;
        end_date: string;
        order: number | string;
        weight: number | string;
        is_current: boolean;
    }>({
        name: '',
        academic_year: '',
        start_date: '',
        end_date: '',
        order: next_order,
        weight: '',
        is_current: false,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/school/grades/terms');
    }

    const labelCls = 'block text-xs font-bold text-slate-500 mb-1.5';
    const inputCls = 'w-full border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 transition';
    const ringStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <SchoolLayout>
            <Head title="Terms" />

            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Academic Terms</h1>
                <p className="text-sm text-slate-400 mt-1">Manage term definitions and weights</p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Add term form */}
                <Card className="p-6">
                    <h3 className="font-bold text-slate-800 mb-4">Add Term</h3>
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className={labelCls}>Name</label>
                            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                placeholder="e.g. Term 1" className={inputCls} style={ringStyle} required />
                            {errors.name && <p className="text-red-500 text-xs mt-1">{errors.name}</p>}
                        </div>
                        <div>
                            <label className={labelCls}>Academic Year</label>
                            <input type="text" value={data.academic_year} onChange={e => setData('academic_year', e.target.value)}
                                placeholder="e.g. 2018 E.C." className={inputCls} style={ringStyle} required />
                            {errors.academic_year && <p className="text-red-500 text-xs mt-1">{errors.academic_year}</p>}
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelCls}>Start Date</label>
                                <input type="date" value={data.start_date} onChange={e => setData('start_date', e.target.value)}
                                    className={inputCls} style={ringStyle} required />
                                {errors.start_date && <p className="text-red-500 text-xs mt-1">{errors.start_date}</p>}
                            </div>
                            <div>
                                <label className={labelCls}>End Date</label>
                                <input type="date" value={data.end_date} onChange={e => setData('end_date', e.target.value)}
                                    className={inputCls} style={ringStyle} required />
                                {errors.end_date && <p className="text-red-500 text-xs mt-1">{errors.end_date}</p>}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className={labelCls}>Order</label>
                                <input type="number" min={1} max={10} value={data.order} onChange={e => setData('order', e.target.value)}
                                    className={inputCls} style={ringStyle} required />
                                {errors.order && <p className="text-red-500 text-xs mt-1">{errors.order}</p>}
                            </div>
                            <div>
                                <label className={labelCls}>Weight (%)</label>
                                <input type="number" min={0} max={100} step={0.01} value={data.weight} onChange={e => setData('weight', e.target.value)}
                                    placeholder="Optional" className={inputCls} style={ringStyle} />
                                {errors.weight && <p className="text-red-500 text-xs mt-1">{errors.weight}</p>}
                            </div>
                        </div>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={data.is_current} onChange={e => setData('is_current', e.target.checked)}
                                className="rounded border-slate-300" style={{ accentColor: accent }} />
                            <span className="text-sm text-slate-700">Mark as current term</span>
                        </label>
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full px-4 py-2.5 rounded-2xl text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                            style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                        >
                            {processing ? 'Creating…' : 'Create Term'}
                        </button>
                    </form>
                </Card>

                {/* Terms list */}
                <div className="lg:col-span-2">
                    {terms.length === 0 ? (
                        <Card className="p-12 text-center text-slate-400">
                            <p>No terms yet. Create the first one.</p>
                        </Card>
                    ) : (
                        <Card className="overflow-hidden">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left">
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Term</th>
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Year</th>
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Order</th>
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Weight</th>
                                        <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {terms.map(t => (
                                        <tr key={t.id} className="hover:bg-slate-50/70 transition">
                                            <td className="px-5 py-3 font-bold text-slate-800">{t.name}</td>
                                            <td className="px-5 py-3 text-slate-500">{t.academic_year}</td>
                                            <td className="px-5 py-3 text-center text-slate-500">{t.order}</td>
                                            <td className="px-5 py-3 text-center text-slate-500">{t.weight !== null ? `${t.weight}%` : '—'}</td>
                                            <td className="px-5 py-3 text-center">
                                                {t.is_current ? (
                                                    <span className="text-xs px-2.5 py-1 rounded-full bg-green-50 text-green-600 font-bold">Current</span>
                                                ) : (
                                                    <span className="text-xs text-slate-300">—</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </Card>
                    )}
                </div>
            </div>

            <div className="mt-4">
                <Link href="/school/grades" className="text-sm font-semibold text-slate-500 hover:text-slate-700">← Back to Grades</Link>
            </div>
        </SchoolLayout>
    );
}
