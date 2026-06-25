import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, CheckCircle } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { type Tone } from '@/Components/Badge';

interface Order {
    id: number;
    order_code: string;
    school_name: string | null;
    order_type: string;
    student_count: number;
    amount_etb: number;
    status: string;
}

interface Props {
    orders: {
        data: Order[];
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { code?: string; status?: string };
}

const STATUSES = ['pending', 'confirmed', 'printing', 'shipped', 'delivered', 'cancelled'];

const statusTone: Record<string, Tone> = {
    pending:   'amber',
    confirmed: 'blue',
    printing:  'purple',
    shipped:   'purple',
    delivered: 'green',
    cancelled: 'rose',
};

export default function Index({ orders, filters }: Props) {
    const [code, setCode]     = useState(filters.code ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    function apply(e: React.FormEvent) {
        e.preventDefault();
        router.get('/admin/card-orders', { code, status }, { preserveState: true, replace: true });
    }

    return (
        <AdminLayout>
            <Head title="Card Orders" />

            <PageHeader
                title="Card Orders"
                subtitle="All card orders across schools"
                actions={
                    <a
                        href="/admin/card-orders/process"
                        className="inline-flex items-center gap-2 px-4 py-2.5 text-sm rounded-xl font-medium bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white shadow-lg shadow-purple-500/20 transition-all"
                    >
                        <CheckCircle className="w-4 h-4" />
                        Process a Code
                    </a>
                }
            />

            {/* Filters */}
            <form onSubmit={apply} className="flex flex-wrap items-center gap-2 mb-6">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
                    <input
                        type="text"
                        value={code}
                        onChange={e => setCode(e.target.value)}
                        placeholder="Search order code…"
                        className="bg-white/5 border border-white/10 rounded-lg pl-9 pr-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-purple-500 w-56"
                    />
                </div>
                <select
                    value={status}
                    onChange={e => setStatus(e.target.value)}
                    className="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-purple-500"
                >
                    <option value="">All Status</option>
                    {STATUSES.map(s => (
                        <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                    ))}
                </select>
                <button type="submit" className="bg-white/5 hover:bg-white/10 border border-white/10 text-slate-200 px-4 py-2 rounded-lg text-sm transition">
                    Filter
                </button>
            </form>

            {/* Table */}
            <div className="bg-white/[0.025] rounded-xl border border-white/10 overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-white/10 text-left">
                                {['Order Code', 'School', 'Type', 'Cards', 'Amount', 'Status', ''].map(h => (
                                    <th key={h} className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {orders.data.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="px-5 py-12 text-center text-slate-500">No card orders yet.</td>
                                </tr>
                            ) : orders.data.map(o => (
                                <tr
                                    key={o.id}
                                    className="hover:bg-white/5 transition cursor-pointer"
                                    onClick={() => router.visit(`/admin/card-orders/${o.id}`)}
                                >
                                    <td className="px-5 py-3 font-mono text-slate-200">{o.order_code}</td>
                                    <td className="px-5 py-3 text-slate-400">{o.school_name ?? '—'}</td>
                                    <td className="px-5 py-3 text-slate-400 capitalize">{o.order_type.replace(/_/g, ' ')}</td>
                                    <td className="px-5 py-3 text-slate-300 text-center">{o.student_count}</td>
                                    <td className="px-5 py-3 text-slate-300 text-right">{o.amount_etb.toLocaleString()} ETB</td>
                                    <td className="px-5 py-3 text-center">
                                        <Badge tone={statusTone[o.status] ?? 'slate'}>{o.status}</Badge>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <span className="text-purple-400 text-xs font-medium">Manage</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {orders.last_page > 1 && (
                <div className="flex items-center gap-1 mt-4 flex-wrap">
                    {orders.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                            className={`px-3 py-1.5 text-xs rounded-lg transition ${
                                link.active ? 'bg-purple-600 text-white'
                                    : link.url ? 'bg-white/5 text-slate-400 hover:bg-white/10'
                                    : 'bg-white/5 text-slate-600 cursor-not-allowed'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </AdminLayout>
    );
}
