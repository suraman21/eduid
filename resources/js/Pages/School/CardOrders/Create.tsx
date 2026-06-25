import React, { useState, useMemo } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { AlertTriangle } from 'lucide-react';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '00')}`;
}

interface RosterStudent {
    id: number;
    full_name: string;
    uid: string;
    initials: string;
    class_name: string | null;
    has_photo: boolean;
    in_open: boolean;
}
interface SchoolClass { id: number; name: string; }
interface OrderType { value: string; label: string; price: number; }

interface Props {
    roster: RosterStudent[];
    classes: SchoolClass[];
    class_id: number | null;
    order_types: OrderType[];
}

export default function Create({ roster, classes, class_id, order_types }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const ringStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [orderType, setOrderType] = useState(order_types[0]?.value ?? '');

    const unitPrice = order_types.find(t => t.value === orderType)?.price ?? 0;
    const estimatedTotal = selectedIds.size * unitPrice;

    const allChecked = roster.length > 0 && selectedIds.size === roster.length;
    const someChecked = selectedIds.size > 0 && !allChecked;

    function toggleAll(checked: boolean) {
        setSelectedIds(checked ? new Set(roster.map(s => s.id)) : new Set());
    }

    function toggleOne(id: number) {
        setSelectedIds(prev => {
            const next = new Set(prev);
            next.has(id) ? next.delete(id) : next.add(id);
            return next;
        });
    }

    const { post, processing, errors } = useForm({});
    const firstError = Object.values(errors)[0] as string | undefined;

    function submit(e: React.FormEvent) {
        e.preventDefault();
        router.post('/school/card-orders', {
            order_type: orderType,
            student_ids: Array.from(selectedIds),
        });
    }

    function filterByClass(id: string) {
        router.get('/school/card-orders/create', id ? { class_id: id } : {}, { preserveState: false });
    }

    const inputCls = 'border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 bg-white transition';

    return (
        <SchoolLayout>
            <Head title="Order ID Cards" />

            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Order ID Cards</h1>
                <p className="text-sm text-slate-400 mt-1">Select students — Arkeon prints and delivers</p>
            </div>

            {firstError && (
                <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-sm font-medium">
                    {firstError}
                </div>
            )}

            {/* Class filter */}
            <div className="bg-white rounded-3xl border border-slate-200/60 p-4 mb-5 flex flex-wrap items-center gap-3" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04)' }}>
                <label className="text-sm text-slate-600 font-medium">Filter by class:</label>
                <select
                    value={class_id ?? ''}
                    onChange={e => filterByClass(e.target.value)}
                    className={inputCls}
                    style={ringStyle}
                >
                    <option value="">All classes</option>
                    {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                {class_id && (
                    <button onClick={() => filterByClass('')} className="text-sm text-slate-400 hover:text-slate-600">Clear</button>
                )}
            </div>

            {roster.length === 0 ? (
                <div className="bg-white rounded-3xl border border-slate-200/60 p-12 text-center text-slate-400" style={{ boxShadow: '0 12px 32px rgba(15,23,42,0.05)' }}>
                    <p>No active students{class_id ? ' in this class' : ''}.</p>
                </div>
            ) : (
                <form onSubmit={submit}>
                    {/* Order type + summary bar */}
                    <div className="bg-white rounded-3xl border border-slate-200/60 p-5 mb-5" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04)' }}>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
                            <div>
                                <label className="block text-xs font-bold text-slate-500 mb-1.5">Order Type</label>
                                <select
                                    value={orderType}
                                    onChange={e => setOrderType(e.target.value)}
                                    className={`w-full ${inputCls}`}
                                    style={ringStyle}
                                >
                                    {order_types.map(t => (
                                        <option key={t.value} value={t.value}>
                                            {t.label} — {t.price} ETB/card
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="text-sm text-slate-500">
                                <span className="font-bold text-slate-800">{selectedIds.size}</span> selected ·
                                Estimated total:{' '}
                                <span className="font-bold text-slate-800">{estimatedTotal.toLocaleString()} ETB</span>
                            </div>
                        </div>
                    </div>

                    {/* Roster */}
                    <div className="bg-white rounded-3xl border border-slate-200/60 overflow-hidden mb-6" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>
                        <div className="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                            <p className="font-bold text-slate-800">{roster.length} students</p>
                            <label className="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={allChecked}
                                    ref={el => { if (el) el.indeterminate = someChecked; }}
                                    onChange={e => toggleAll(e.target.checked)}
                                    className="rounded border-slate-300"
                                    style={{ accentColor: accent }}
                                />
                                Select all
                            </label>
                        </div>
                        <div className="divide-y divide-slate-50 max-h-[28rem] overflow-y-auto">
                            {roster.map(s => (
                                <label key={s.id} className="px-5 py-3 flex items-center gap-3 cursor-pointer hover:bg-slate-50/70 transition">
                                    <input
                                        type="checkbox"
                                        checked={selectedIds.has(s.id)}
                                        onChange={() => toggleOne(s.id)}
                                        className="rounded border-slate-300 flex-shrink-0"
                                        style={{ accentColor: accent }}
                                    />
                                    <div
                                        className="w-9 h-9 rounded-2xl flex items-center justify-center text-xs font-extrabold flex-shrink-0"
                                        style={{ background: alpha(accent, 0.10), color: accent }}
                                    >
                                        {s.initials}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <p className="font-bold text-slate-800 truncate">{s.full_name}</p>
                                        <p className="text-xs text-slate-400 font-mono">{s.uid} · {s.class_name ?? 'No class'}</p>
                                    </div>
                                    <div className="flex items-center gap-2 flex-shrink-0">
                                        {!s.has_photo && (
                                            <span className="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 font-medium" title="No photo — card prints with a placeholder">
                                                No photo
                                            </span>
                                        )}
                                        {s.in_open && (
                                            <span className="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-500 font-medium" title="Already in an open order">
                                                In open order
                                            </span>
                                        )}
                                    </div>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            type="submit"
                            disabled={processing || selectedIds.size === 0}
                            className="px-6 py-2.5 rounded-2xl text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                            style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                        >
                            {processing ? 'Creating…' : `Create Order (${selectedIds.size} card${selectedIds.size !== 1 ? 's' : ''})`}
                        </button>
                        <Link href="/school/card-orders" className="text-sm font-semibold text-slate-500 hover:text-slate-700">Cancel</Link>
                    </div>
                </form>
            )}
        </SchoolLayout>
    );
}
