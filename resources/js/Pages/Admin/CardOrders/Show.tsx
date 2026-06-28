import { Head, useForm, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, FileText } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { type Tone } from '@/Components/Badge';

interface Student {
    id: number;
    full_name: string;
    uid: string;
    has_photo: boolean;
}

interface Order {
    id: number;
    order_code: string;
    school_name: string | null;
    ordered_by: string | null;
    order_type: string;
    student_count: number;
    amount_etb: number;
    status: string;
    cancel_reason: string | null;
    print_file_path: string | null;
    can_cancel: boolean;
    created_at: string;
}

interface Props { order: Order; students: Student[]; }

const TRANSITIONS: Record<string, string> = {
    confirmed: 'printing',
    printing:  'shipped',
    shipped:   'delivered',
};

const statusTone: Record<string, Tone> = {
    pending: 'amber', confirmed: 'blue', printing: 'purple',
    shipped: 'purple', delivered: 'green', cancelled: 'rose',
};

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function Stat({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs text-slate-500 uppercase">{label}</p>
            <p className="text-slate-200 font-medium mt-0.5">{value}</p>
        </div>
    );
}

export default function Show({ order, students }: Props) {
    const [trackingNote, setTrackingNote] = useState('');

    const confirmForm = useForm({});
    const statusForm  = useForm({ status: TRANSITIONS[order.status] ?? '', tracking_note: trackingNote });
    const cancelForm  = useForm({});

    const nextStatus = TRANSITIONS[order.status];
    const isFulfilled = order.status === 'delivered' || order.status === 'cancelled';
    const showPrintSection = ['confirmed', 'printing', 'shipped', 'delivered'].includes(order.status);

    function confirm(e: React.FormEvent) {
        e.preventDefault();
        confirmForm.post(`/admin/card-orders/${order.id}/confirm`);
    }

    function advance(e: React.FormEvent) {
        e.preventDefault();
        statusForm.setData('tracking_note', trackingNote);
        statusForm.post(`/admin/card-orders/${order.id}/status`);
    }

    function cancel(e: React.FormEvent) {
        e.preventDefault();
        if (!window.confirm('Cancel this order?')) return;
        cancelForm.post(`/admin/card-orders/${order.id}/cancel`);
    }

    return (
        <AdminLayout>
            <Head title={`Order ${order.order_code}`} />

            <div className="mb-4">
                <a href="/admin/card-orders" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-purple-400 transition">
                    <ArrowLeft className="w-4 h-4" />
                    Back to Card Orders
                </a>
            </div>

            <PageHeader
                title={order.order_code}
                subtitle={`${order.school_name ?? '—'} · ${order.student_count} cards`}
                actions={<Badge tone={statusTone[order.status] ?? 'slate'}>{order.status}</Badge>}
            />

            <div className="max-w-3xl space-y-6">
                {/* Order overview */}
                <div className="bg-white/[0.025] border border-white/10 rounded-xl p-6">
                    <p className="text-xs text-slate-500 mb-1">Ordered by {order.ordered_by ?? '—'}</p>
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm mb-5">
                        <Stat label="Cards"  value={order.student_count} />
                        <Stat label="Type"   value={<span className="capitalize">{order.order_type.replace(/_/g, ' ')}</span>} />
                        <Stat label="Amount" value={`${order.amount_etb.toLocaleString()} ETB`} />
                        <Stat label="Placed" value={new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} />
                    </div>

                    {/* Lifecycle actions */}
                    {!isFulfilled && (
                        <div className="flex flex-wrap items-center gap-3 pt-4 border-t border-white/10">
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

                            {nextStatus && order.status !== 'pending' && (
                                <form onSubmit={advance} className="flex items-center gap-2">
                                    <input
                                        type="text"
                                        value={trackingNote}
                                        onChange={e => setTrackingNote(e.target.value)}
                                        placeholder="Tracking note (optional)"
                                        className="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-purple-500 w-56"
                                    />
                                    <button
                                        type="submit"
                                        disabled={statusForm.processing}
                                        className="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white px-5 py-2 rounded-lg text-sm font-medium transition disabled:opacity-50 capitalize"
                                    >
                                        {statusForm.processing ? 'Saving…' : `Mark ${nextStatus}`}
                                    </button>
                                </form>
                            )}

                            {order.can_cancel && (
                                <form onSubmit={cancel}>
                                    <button type="submit" disabled={cancelForm.processing} className="text-rose-400 hover:text-rose-300 text-sm transition">
                                        Cancel
                                    </button>
                                </form>
                            )}
                        </div>
                    )}

                    {order.cancel_reason && (
                        <p className="text-xs text-slate-500 mt-3">Cancellation reason: {order.cancel_reason}</p>
                    )}
                </div>

                {/* Print file */}
                {showPrintSection && (
                    <div className="bg-white/[0.025] border border-white/10 rounded-xl p-6">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <p className="text-slate-200 font-medium flex items-center gap-2">
                                    <FileText className="w-4 h-4 text-purple-400" />
                                    Print-Ready File
                                </p>
                                <p className="text-xs text-slate-500 mt-1">
                                    High-resolution CR80 PDF for the PVC printer. Private — schools never see this.
                                </p>
                            </div>
                            <div className="flex items-center gap-2 flex-shrink-0">
                                {order.print_file_path ? (
                                    <a
                                        href={`/admin/card-orders/${order.id}/print-file`}
                                        className="bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition"
                                    >
                                        Download PDF
                                    </a>
                                ) : (
                                    <span className="text-xs text-amber-400">Not generated</span>
                                )}
                                {['confirmed', 'printing'].includes(order.status) && (
                                    <form method="POST" action={`/admin/card-orders/${order.id}/print-file/regenerate`}>
                                        <input type="hidden" name="_token" value={csrf()} />
                                        <button type="submit" className="border border-white/10 hover:border-white/30 text-slate-300 px-4 py-2 rounded-lg text-sm transition">
                                            Regenerate
                                        </button>
                                    </form>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* Generate cards */}
                {showPrintSection && (
                    <div className="bg-white/[0.025] border border-white/10 rounded-xl p-6">
                        <p className="text-slate-200 font-medium mb-1">Generate Cards</p>
                        <p className="text-xs text-slate-500 mb-4">
                            Render all cards using the school's configured template. High-quality files for the PVC printer — never shared with the school.
                        </p>

                        {/* Recommended: professional multi-up A4 sheet printing (slots, calibration). */}
                        <a
                            href={`/admin/card-orders/${order.id}/sheet-print`}
                            className="inline-flex items-center gap-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white text-sm px-4 py-2 rounded-lg font-medium transition mr-2"
                        >
                            Professional Print (A4 Sheet) →
                        </a>

                        {/* Quick client-side proof print (always live data, no server file). */}
                        <a
                            href={`/admin/card-orders/${order.id}/print`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-sm px-4 py-2 rounded-lg font-medium transition"
                        >
                            Quick Proof (PDF) →
                        </a>
                        <p className="text-[11px] text-slate-500 mt-2 mb-4">
                            <strong className="text-slate-400">Professional Print</strong> composes cards onto an A4 PVC sheet with slot tracking. <strong className="text-slate-400">Quick Proof</strong> opens a single-card print view for a fast check.
                        </p>

                        <p className="text-[11px] text-slate-500 mb-2 uppercase tracking-wider">Server-generated files (fallback)</p>
                        <div className="flex items-center gap-3">
                            <form method="POST" action={`/admin/card-orders/${order.id}/generate`}>
                                <input type="hidden" name="_token" value={csrf()} />
                                <input type="hidden" name="format" value="pdf" />
                                <button type="submit" className="border border-white/10 hover:border-white/30 text-slate-300 text-sm px-4 py-2 rounded-lg transition">
                                    Download PDF
                                </button>
                            </form>
                            <form method="POST" action={`/admin/card-orders/${order.id}/generate`}>
                                <input type="hidden" name="_token" value={csrf()} />
                                <input type="hidden" name="format" value="png" />
                                <button type="submit" className="border border-white/10 hover:border-white/30 text-slate-300 text-sm px-4 py-2 rounded-lg transition">
                                    Download PNG ZIP
                                </button>
                            </form>
                        </div>
                    </div>
                )}

                {/* Students */}
                <div className="bg-white/[0.025] border border-white/10 rounded-xl overflow-hidden">
                    <div className="px-5 py-3 border-b border-white/10">
                        <p className="text-slate-200 font-medium">Students ({students.length})</p>
                    </div>
                    <div className="divide-y divide-white/5 max-h-96 overflow-y-auto">
                        {students.map(s => (
                            <div key={s.id} className="px-5 py-2.5 flex items-center gap-3 text-sm">
                                <p className="flex-1 text-slate-200">{s.full_name}</p>
                                <p className="text-xs text-slate-500 font-mono">{s.uid}</p>
                                {!s.has_photo && (
                                    <Badge tone="amber">No photo</Badge>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
