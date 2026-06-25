import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { type Tone } from '@/Components/Badge';

interface Student { id: number; full_name: string; uid: string; has_photo: boolean; }

interface Order {
    id: number;
    order_code: string;
    school_name: string | null;
    ordered_by: string | null;
    order_type: string;
    student_count: number;
    amount_etb: number;
    status: string;
}

interface Props {
    code: string;
    order: Order | null;
    students: Student[];
    no_photo_count: number;
}

const statusTone: Record<string, Tone> = {
    pending: 'amber', confirmed: 'blue', printing: 'purple',
    shipped: 'purple', delivered: 'green', cancelled: 'rose',
};

function Stat({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs text-slate-500 uppercase">{label}</p>
            <p className="text-slate-200 font-medium mt-0.5">{value}</p>
        </div>
    );
}

export default function Process({ code: initialCode, order, students, no_photo_count }: Props) {
    const [code, setCode] = useState(initialCode);

    const confirmForm = useForm({});

    function lookup(e: React.FormEvent) {
        e.preventDefault();
        router.get('/admin/card-orders/process', { code }, { preserveState: false });
    }

    function confirm(e: React.FormEvent) {
        e.preventDefault();
        if (order) confirmForm.post(`/admin/card-orders/${order.id}/confirm`);
    }

    return (
        <AdminLayout>
            <Head title="Process Card Order" />

            <div className="mb-4">
                <a href="/admin/card-orders" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-purple-400 transition">
                    <ArrowLeft className="w-4 h-4" />
                    Back to Card Orders
                </a>
            </div>

            <PageHeader title="Process Card Order" subtitle="Enter the confirmation code a school shared" />

            <div className="max-w-2xl space-y-6">
                {/* Code lookup */}
                <form onSubmit={lookup} className="bg-white/[0.025] border border-white/10 rounded-xl p-5">
                    <label className="block text-sm font-medium text-slate-300 mb-2">Confirmation Code</label>
                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={code}
                            onChange={e => setCode(e.target.value)}
                            placeholder="ORD-AIA-2026-0001"
                            autoFocus
                            className="flex-1 bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-sm text-slate-200 font-mono placeholder-slate-500 focus:outline-none focus:border-purple-500"
                        />
                        <button type="submit" className="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white px-6 py-2 rounded-lg text-sm font-medium transition">
                            Look Up
                        </button>
                    </div>
                </form>

                {/* Not found */}
                {initialCode !== '' && !order && (
                    <div className="bg-rose-500/10 border border-rose-500/30 text-rose-400 px-4 py-3 rounded-lg text-sm">
                        No order found for code <span className="font-mono">{initialCode}</span>.
                    </div>
                )}

                {/* Order preview */}
                {order && (
                    <div className="bg-white/[0.025] border border-white/10 rounded-xl p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <p className="font-mono text-xl text-slate-100">{order.order_code}</p>
                                <p className="text-sm text-slate-400">{order.school_name ?? '—'}</p>
                            </div>
                            <Badge tone={statusTone[order.status] ?? 'slate'}>{order.status}</Badge>
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm mb-5">
                            <Stat label="Cards"      value={order.student_count} />
                            <Stat label="Type"       value={<span className="capitalize">{order.order_type.replace(/_/g, ' ')}</span>} />
                            <Stat label="Amount Due" value={`${order.amount_etb.toLocaleString()} ETB`} />
                            <Stat label="Ordered By" value={order.ordered_by ?? '—'} />
                        </div>

                        {no_photo_count > 0 && (
                            <div className="bg-amber-500/10 border border-amber-500/30 text-amber-400 px-4 py-2 rounded-lg text-sm mb-4">
                                {no_photo_count} student{no_photo_count !== 1 ? 's' : ''} {no_photo_count !== 1 ? 'have' : 'has'} no photo — {no_photo_count !== 1 ? 'they' : 'it'} will print with a placeholder silhouette.
                            </div>
                        )}

                        <div className="flex items-center gap-3">
                            {order.status === 'pending' && (
                                <form onSubmit={confirm}>
                                    <button
                                        type="submit"
                                        disabled={confirmForm.processing}
                                        className="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50"
                                    >
                                        {confirmForm.processing ? 'Confirming…' : 'Confirm Order'}
                                    </button>
                                </form>
                            )}
                            <a href={`/admin/card-orders/${order.id}`} className="text-purple-400 hover:text-purple-300 text-sm transition">
                                Open full order →
                            </a>
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
