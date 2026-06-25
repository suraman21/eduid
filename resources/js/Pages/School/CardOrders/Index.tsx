import React from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { Plus, CreditCard } from 'lucide-react';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

const STATUS_COLORS: Record<string, string> = {
    pending:   'bg-amber-50 text-amber-600',
    confirmed: 'bg-blue-50 text-blue-600',
    printing:  'bg-indigo-50 text-indigo-600',
    shipped:   'bg-purple-50 text-purple-600',
    delivered: 'bg-green-50 text-green-600',
    cancelled: 'bg-red-50 text-red-500',
};

interface Order {
    id: number;
    order_code: string;
    order_type: string;
    student_count: number;
    amount_etb: number;
    status: string;
    created_at: string;
}

interface Props {
    orders: {
        data: Order[];
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
}

export default function Index({ orders }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const isAdmin = (props.auth as any)?.user?.role === 'school_admin';

    return (
        <SchoolLayout>
            <Head title="Card Orders" />

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">ID Card Orders</h1>
                    <p className="text-sm text-slate-400 mt-1">Order PVC cards — printed and delivered by Arkeon</p>
                </div>
                {isAdmin && (
                    <Link
                        href="/school/card-orders/create"
                        className="flex items-center gap-1.5 px-4 py-2.5 text-sm font-bold text-white rounded-2xl transition hover:-translate-y-0.5"
                        style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                    >
                        <Plus className="w-4 h-4" />
                        Order Cards
                    </Link>
                )}
            </div>

            <div className="bg-white rounded-3xl border border-slate-200/60 overflow-hidden" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left">
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Order Code</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Type</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Cards</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right">Amount</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider text-center">Status</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                                <th className="px-5 py-3.5" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {orders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-5 py-14 text-center">
                                        <CreditCard className="w-10 h-10 mx-auto mb-3 text-slate-200" />
                                        <p className="text-slate-400 text-sm">No card orders yet</p>
                                        {isAdmin && (
                                            <Link href="/school/card-orders/create" className="text-sm font-semibold mt-1 inline-block" style={{ color: accent }}>
                                                Order cards →
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ) : orders.data.map(order => (
                                <tr key={order.id} className="hover:bg-slate-50/70 transition">
                                    <td className="px-5 py-3 font-mono font-bold text-slate-800">{order.order_code}</td>
                                    <td className="px-5 py-3 text-slate-600 capitalize">{order.order_type.replace(/_/g, ' ')}</td>
                                    <td className="px-5 py-3 text-center text-slate-600">{order.student_count}</td>
                                    <td className="px-5 py-3 text-right text-slate-600">{order.amount_etb.toLocaleString()} ETB</td>
                                    <td className="px-5 py-3 text-center">
                                        <span className={`text-xs px-2.5 py-1 rounded-full font-bold capitalize ${STATUS_COLORS[order.status] ?? 'bg-slate-100 text-slate-500'}`}>
                                            {order.status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-slate-500">{order.created_at}</td>
                                    <td className="px-5 py-3 text-right">
                                        <Link href={`/school/card-orders/${order.id}`} className="text-xs font-bold transition" style={{ color: accent }}>
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {orders.last_page > 1 && (
                    <div className="px-5 py-3 border-t border-slate-100 flex items-center gap-1 flex-wrap">
                        {orders.links.map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url}
                                onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                className={`px-3 py-1.5 text-xs font-semibold rounded-xl transition ${
                                    link.active ? 'text-white' : link.url ? 'bg-white border border-slate-200 text-slate-500 hover:bg-slate-50' : 'bg-white border border-slate-100 text-slate-300 cursor-not-allowed'
                                }`}
                                style={link.active ? { background: accent } : undefined}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>
        </SchoolLayout>
    );
}
