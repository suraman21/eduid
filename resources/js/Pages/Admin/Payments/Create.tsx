import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';

interface School { id: number; name: string; code: string; }
interface Plan   { id: number; name: string; tier: string; price: number; }

interface Props { schools: School[]; plans: Plan[]; }

const inputClass =
    'w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition';
const labelClass = 'block text-xs font-medium text-slate-400 mb-1';

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className="mb-4">
            <label className={labelClass}>{label}</label>
            {children}
            {error && <p className="text-rose-400 text-xs mt-1">{error}</p>}
        </div>
    );
}

function fmt(n: number) {
    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export default function Create({ schools, plans }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        school_id:        '',
        plan_id:          '',
        amount_etb:       '',
        payment_method:   'bank_transfer',
        bank_name:        '',
        reference_number: '',
        payment_date:     new Date().toISOString().split('T')[0],
        period_months:    '1',
        notes:            '',
        status:           'pending',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/admin/payments');
    }

    return (
        <AdminLayout>
            <Head title="Record Payment" />

            <PageHeader
                title="Record Payment"
                subtitle="Manually record a school subscription payment"
            />

            <div className="max-w-2xl">
                <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                    <form onSubmit={submit}>
                        <Field label="School *" error={errors.school_id}>
                            <select value={data.school_id} onChange={e => setData('school_id', e.target.value)} required className={inputClass}>
                                <option value="">Select school…</option>
                                {schools.map(s => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Plan" error={errors.plan_id}>
                            <select value={data.plan_id} onChange={e => setData('plan_id', e.target.value)} className={inputClass}>
                                <option value="">No specific plan</option>
                                {plans.map(p => (
                                    <option key={p.id} value={p.id}>
                                        {p.tier.charAt(0).toUpperCase() + p.tier.slice(1)} — {p.name} (ETB {fmt(p.price)})
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Amount (ETB) *" error={errors.amount_etb}>
                            <input
                                type="number"
                                value={data.amount_etb}
                                onChange={e => setData('amount_etb', e.target.value)}
                                required min="0" step="0.01"
                                className={inputClass}
                            />
                        </Field>

                        <Field label="Payment Method" error={errors.payment_method}>
                            <select value={data.payment_method} onChange={e => setData('payment_method', e.target.value)} className={inputClass}>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cash">Cash</option>
                                <option value="chapa">Chapa</option>
                                <option value="other">Other</option>
                            </select>
                        </Field>

                        {data.payment_method === 'bank_transfer' && (
                            <Field label="Bank Name" error={errors.bank_name}>
                                <input
                                    type="text"
                                    value={data.bank_name}
                                    onChange={e => setData('bank_name', e.target.value)}
                                    placeholder="e.g. Commercial Bank of Ethiopia"
                                    className={inputClass}
                                />
                            </Field>
                        )}

                        <Field label="Reference Number" error={errors.reference_number}>
                            <input type="text" value={data.reference_number} onChange={e => setData('reference_number', e.target.value)} className={inputClass} />
                        </Field>

                        <Field label="Payment Date" error={errors.payment_date}>
                            <input type="date" value={data.payment_date} onChange={e => setData('payment_date', e.target.value)} className={inputClass} />
                        </Field>

                        <Field label="Period (Months)" error={errors.period_months}>
                            <input type="number" value={data.period_months} onChange={e => setData('period_months', e.target.value)} min="1" max="24" className={inputClass} />
                        </Field>

                        <Field label="Notes" error={errors.notes}>
                            <textarea value={data.notes} onChange={e => setData('notes', e.target.value)} rows={3} className={`${inputClass} resize-none`} />
                        </Field>

                        <Field label="Status" error={errors.status}>
                            <select value={data.status} onChange={e => setData('status', e.target.value)} className={inputClass}>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                            </select>
                        </Field>

                        <div className="flex items-center gap-3 mt-2">
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center justify-center px-6 py-2.5 text-sm rounded-xl font-medium bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white shadow-lg shadow-purple-500/20 transition-all duration-200 disabled:opacity-50"
                            >
                                {processing ? 'Saving…' : 'Record Payment'}
                            </button>
                            <a href="/admin/payments" className="text-slate-400 hover:text-slate-200 px-4 py-2.5 text-sm transition">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </AdminLayout>
    );
}
