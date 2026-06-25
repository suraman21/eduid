import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, DollarSign } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { type Tone } from '@/Components/Badge';

interface School { id: number; name: string; }
interface Plan   { id: number; name: string; }

interface Payment {
    id: number;
    school: School | null;
    plan: Plan | null;
    amount_etb: number;
    payment_method: string;
    reference_number: string | null;
    payment_date: string | null;
    status: string;
}

interface Props {
    payments: {
        data: Payment[];
        total: number;
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    schools: School[];
    total_confirmed: number;
    pending_count: number;
    filters: { school_id?: string; status?: string };
}

const statusTone: Record<string, Tone> = { confirmed: 'green', pending: 'amber' };

function fmt(n: number) {
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtDate(d: string | null) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function fmtMethod(m: string) {
    return m.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

export default function Index({ payments, schools, total_confirmed, pending_count, filters }: Props) {
    const [schoolId, setSchoolId] = useState(filters.school_id ?? '');
    const [status, setStatus]     = useState(filters.status ?? '');

    function applyFilters(e: React.FormEvent) {
        e.preventDefault();
        router.get('/admin/payments', { school_id: schoolId, status }, { preserveState: true, replace: true });
    }

    return (
        <AdminLayout>
            <Head title="Payments" />

            <PageHeader
                title="Payments"
                subtitle="School subscription payments"
                actions={
                    <a
                        href="/admin/payments/create"
                        className="inline-flex items-center gap-2 px-4 py-2.5 text-sm rounded-xl font-medium bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white shadow-lg shadow-purple-500/20 transition-all duration-200"
                    >
                        <Plus className="w-4 h-4" strokeWidth={2} />
                        Record Payment
                    </a>
                }
            />

            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div className="bg-white/[0.025] border border-white/10 rounded-xl p-5">
                    <p className="text-xs font-semibold text-slate-500 uppercase mb-1">Total Confirmed (ETB)</p>
                    <span className="text-3xl font-bold text-slate-100 font-mono">{fmt(total_confirmed)}</span>
                </div>
                <div className="bg-white/[0.025] border border-white/10 rounded-xl p-5">
                    <p className="text-xs font-semibold text-slate-500 uppercase mb-1">Pending</p>
                    <div className="flex items-center gap-3">
                        <span className="text-3xl font-bold text-slate-100">{pending_count}</span>
                        <Badge tone="amber">Pending</Badge>
                    </div>
                </div>
            </div>

            {/* Filters */}
            <form onSubmit={applyFilters} className="flex flex-wrap items-center gap-2 mb-6">
                <select
                    value={schoolId}
                    onChange={e => setSchoolId(e.target.value)}
                    className="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-purple-500"
                >
                    <option value="">All Schools</option>
                    {schools.map(s => (
                        <option key={s.id} value={s.id}>{s.name}</option>
                    ))}
                </select>
                <select
                    value={status}
                    onChange={e => setStatus(e.target.value)}
                    className="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-purple-500"
                >
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
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
                                {['School', 'Plan', 'Amount (ETB)', 'Method', 'Reference', 'Date', 'Status', ''].map(h => (
                                    <th key={h} className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {payments.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-5 py-12 text-center text-slate-500">
                                        <DollarSign className="w-10 h-10 mx-auto mb-3 text-slate-700" />
                                        No payments found
                                    </td>
                                </tr>
                            ) : payments.data.map(p => (
                                <tr
                                    key={p.id}
                                    className="hover:bg-white/5 transition cursor-pointer"
                                    onClick={() => router.visit(`/admin/payments/${p.id}`)}
                                >
                                    <td className="px-5 py-3 font-medium text-slate-200">{p.school?.name ?? '—'}</td>
                                    <td className="px-5 py-3 text-slate-400">{p.plan?.name ?? '—'}</td>
                                    <td className="px-5 py-3 text-slate-200 font-mono">{fmt(p.amount_etb)}</td>
                                    <td className="px-5 py-3 text-slate-400">{fmtMethod(p.payment_method)}</td>
                                    <td className="px-5 py-3 text-slate-400 font-mono text-xs">{p.reference_number ?? '—'}</td>
                                    <td className="px-5 py-3 text-slate-400 text-xs">{fmtDate(p.payment_date)}</td>
                                    <td className="px-5 py-3">
                                        <Badge tone={statusTone[p.status] ?? 'slate'}>
                                            {p.status.charAt(0).toUpperCase() + p.status.slice(1)}
                                        </Badge>
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className="text-purple-400 hover:text-purple-300 text-xs font-medium">View</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {payments.last_page > 1 && (
                <div className="flex items-center gap-1 mt-4 flex-wrap">
                    {payments.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                            className={`px-3 py-1.5 text-xs rounded-lg transition ${
                                link.active
                                    ? 'bg-purple-600 text-white'
                                    : link.url
                                        ? 'bg-white/5 text-slate-400 hover:bg-white/10'
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
