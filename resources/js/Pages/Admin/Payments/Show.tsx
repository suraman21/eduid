import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { type Tone } from '@/Components/Badge';

interface Payment {
    id: number;
    school_name: string;
    plan_name: string | null;
    amount_etb: number;
    payment_method: string;
    bank_name: string | null;
    reference_number: string | null;
    payment_date: string | null;
    period_months: number;
    status: string;
    notes: string | null;
    recorded_by: string | null;
    confirmed_by: string | null;
}

interface Props { payment: Payment; }

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

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="py-3 grid grid-cols-3 gap-4 border-b border-white/5 last:border-0">
            <dt className="text-xs font-medium text-slate-500 uppercase">{label}</dt>
            <dd className="col-span-2 text-sm text-slate-200">{value ?? '—'}</dd>
        </div>
    );
}

export default function Show({ payment }: Props) {
    const { post, processing } = useForm({});

    function confirm(e: React.FormEvent) {
        e.preventDefault();
        post(`/admin/payments/${payment.id}/confirm`);
    }

    return (
        <AdminLayout>
            <Head title={`Payment #${payment.id}`} />

            <div className="mb-4">
                <a href="/admin/payments" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-purple-400 transition">
                    <ArrowLeft className="w-4 h-4" />
                    Back to Payments
                </a>
            </div>

            <PageHeader
                title={`Payment #${payment.id}`}
                subtitle={payment.school_name}
                actions={<Badge tone={statusTone[payment.status] ?? 'slate'}>{payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}</Badge>}
            />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Details */}
                <div className="lg:col-span-2">
                    <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">Payment Details</h3>
                        <dl>
                            <Row label="School"       value={payment.school_name} />
                            <Row label="Plan"         value={payment.plan_name} />
                            <Row label="Amount (ETB)" value={<span className="font-mono">{fmt(payment.amount_etb)}</span>} />
                            <Row label="Method"       value={fmtMethod(payment.payment_method)} />
                            {payment.bank_name && <Row label="Bank" value={payment.bank_name} />}
                            <Row label="Reference"    value={<span className="font-mono">{payment.reference_number}</span>} />
                            <Row label="Payment Date" value={fmtDate(payment.payment_date)} />
                            <Row label="Period"       value={`${payment.period_months} month${payment.period_months !== 1 ? 's' : ''}`} />
                            <Row label="Status"       value={<Badge tone={statusTone[payment.status] ?? 'slate'}>{payment.status.charAt(0).toUpperCase() + payment.status.slice(1)}</Badge>} />
                            {payment.notes && <Row label="Notes" value={<span className="whitespace-pre-wrap">{payment.notes}</span>} />}
                            <Row label="Recorded By"  value={payment.recorded_by} />
                            {payment.confirmed_by && <Row label="Confirmed By" value={payment.confirmed_by} />}
                        </dl>
                    </div>
                </div>

                {/* Actions */}
                <div>
                    <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">Actions</h3>
                        {payment.status === 'pending' ? (
                            <form onSubmit={confirm}>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm rounded-xl font-medium bg-emerald-600 hover:bg-emerald-500 text-white transition disabled:opacity-50"
                                >
                                    {processing ? 'Confirming…' : 'Confirm Payment'}
                                </button>
                            </form>
                        ) : (
                            <div className="text-center">
                                <Badge tone="green">Payment Confirmed</Badge>
                            </div>
                        )}
                        <a href="/admin/payments" className="block text-center text-sm text-slate-500 hover:text-slate-300 transition mt-4">
                            Back to Payments
                        </a>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
