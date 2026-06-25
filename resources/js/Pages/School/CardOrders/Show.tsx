import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { ArrowLeft, Eye } from 'lucide-react';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

const STEPS = ['Pending', 'Confirmed', 'Printing', 'Shipped', 'Delivered'];

interface Order {
    id: number;
    order_code: string;
    order_type: string;
    student_count: number;
    amount_etb: number;
    status: string;
    is_pending: boolean;
    is_cancelled: boolean;
    can_cancel: boolean;
    cancel_reason: string | null;
    tracking_note: string | null;
    ordered_by: string | null;
    created_at: string;
    step_index: number;
}
interface Student { id: number; full_name: string; uid: string; }

interface Props {
    order: Order;
    students: Student[];
}

export default function Show({ order, students }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const [cancelling, setCancelling] = useState(false);

    function handleCancel() {
        if (!window.confirm(`Cancel order ${order.order_code}?`)) return;
        setCancelling(true);
        router.post(`/school/card-orders/${order.id}/cancel`, {}, {
            onFinish: () => setCancelling(false),
        });
    }

    const statLabel = (label: string) => (
        <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">{label}</p>
        </div>
    );

    return (
        <SchoolLayout>
            <Head title={`Order ${order.order_code}`} />

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight font-mono">{order.order_code}</h1>
                    <p className="text-sm text-slate-400 mt-1 capitalize">{order.order_type.replace(/_/g, ' ')} · {order.student_count} cards</p>
                </div>
                <Link href="/school/card-orders" className="flex items-center gap-1.5 px-3.5 py-2.5 text-sm font-semibold text-slate-600 border border-slate-200 rounded-2xl bg-white hover:bg-slate-50 transition">
                    <ArrowLeft className="w-4 h-4" />
                    Back to Orders
                </Link>
            </div>

            <div className="max-w-3xl space-y-5">
                {/* Confirmation code callout */}
                {order.is_pending && (
                    <div
                        className="rounded-3xl border p-5"
                        style={{ background: '#fffbeb', borderColor: '#fde68a' }}
                    >
                        <p className="text-sm text-amber-800 mb-2">Share this confirmation code with Arkeon (WhatsApp / Telegram / phone) to confirm your order:</p>
                        <p className="font-mono text-2xl font-bold text-amber-900 tracking-wider">{order.order_code}</p>
                    </div>
                )}

                {/* Tracking note */}
                {order.tracking_note && (
                    <div
                        className="rounded-3xl border p-4 text-sm"
                        style={{ background: alpha(accent, 0.06), borderColor: alpha(accent, 0.18), color: accent }}
                    >
                        {order.tracking_note}
                    </div>
                )}

                {/* Status timeline */}
                <div className="bg-white rounded-3xl border border-slate-200/60 p-5" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>
                    {order.is_cancelled ? (
                        <div className="text-center py-2">
                            <span className="text-sm px-3 py-1 rounded-full bg-red-50 text-red-500 font-bold">Cancelled</span>
                            {order.cancel_reason && <p className="text-xs text-slate-400 mt-2">{order.cancel_reason}</p>}
                        </div>
                    ) : (
                        <div className="flex items-center">
                            {STEPS.map((label, i) => {
                                const done = i <= order.step_index;
                                const isLast = i === STEPS.length - 1;
                                return (
                                    <React.Fragment key={label}>
                                        <div className="flex flex-col items-center flex-1">
                                            <div
                                                className="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all"
                                                style={done ? { background: accent, color: 'white' } : { background: '#f1f5f9', color: '#94a3b8' }}
                                            >
                                                {i + 1}
                                            </div>
                                            <span className={`text-xs mt-1.5 ${done ? 'text-slate-700 font-semibold' : 'text-slate-400'}`}>{label}</span>
                                        </div>
                                        {!isLast && (
                                            <div
                                                className="h-0.5 flex-1 transition-all"
                                                style={{ background: i < order.step_index ? accent : '#f1f5f9' }}
                                            />
                                        )}
                                    </React.Fragment>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Order details */}
                <div className="bg-white rounded-3xl border border-slate-200/60 p-5 grid grid-cols-2 sm:grid-cols-4 gap-5" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04)' }}>
                    {[
                        { label: 'Cards', value: order.student_count },
                        { label: 'Amount', value: `${order.amount_etb.toLocaleString()} ETB` },
                        { label: 'Ordered By', value: order.ordered_by ?? '—' },
                        { label: 'Placed', value: order.created_at },
                    ].map(({ label, value }) => (
                        <div key={label}>
                            <p className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">{label}</p>
                            <p className="font-semibold text-slate-800">{value}</p>
                        </div>
                    ))}
                </div>

                {/* Students list */}
                <div className="bg-white rounded-3xl border border-slate-200/60 overflow-hidden" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04)' }}>
                    <div className="px-5 py-3.5 border-b border-slate-100">
                        <p className="font-bold text-slate-800">Students ({students.length})</p>
                    </div>
                    <div className="divide-y divide-slate-50 max-h-80 overflow-y-auto">
                        {students.map(s => (
                            <div key={s.id} className="px-5 py-2.5 flex items-center gap-3">
                                <p className="flex-1 text-slate-800 font-medium">{s.full_name}</p>
                                <p className="text-xs text-slate-400 font-mono">{s.uid}</p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Actions */}
                <div className="flex items-center justify-between">
                    <Link href="/school/card-orders" className="text-sm font-semibold text-slate-500 hover:text-slate-700">← Back to Orders</Link>
                    <div className="flex items-center gap-4">
                        <Link
                            href={`/school/card-orders/${order.id}/id-cards`}
                            className="flex items-center gap-1.5 text-sm font-bold"
                            style={{ color: accent }}
                        >
                            <Eye className="w-4 h-4" />
                            Preview ID Cards
                        </Link>
                        {order.can_cancel && (
                            <button
                                onClick={handleCancel}
                                disabled={cancelling}
                                className="text-sm text-red-500 hover:text-red-700 font-semibold disabled:opacity-60"
                            >
                                {cancelling ? 'Cancelling…' : 'Cancel Order'}
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </SchoolLayout>
    );
}
