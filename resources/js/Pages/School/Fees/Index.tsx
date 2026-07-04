import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Plus, Trash2, Wallet, HandCoins, Ban, Users2, ReceiptText } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import { EmptyState } from '@/Components/States';
import type { PageProps } from '@/types';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
const etb = (n: number) => n.toLocaleString(undefined, { maximumFractionDigits: 2 });

function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return <div className={`bg-white rounded-3xl border border-slate-200/60 ${className}`} style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>{children}</div>;
}

interface Invoice { id: number; title: string; student: string | null; class: string | null; amount: number; paid: number; balance: number; status: string; due_date: string | null }
interface Structure { id: number; name: string; amount: number; frequency: string; applies_to: string; target_class: string | null; target_class_id: number | null; is_active: boolean }
interface Option { id: number; name: string }

interface Props {
    invoices: Invoice[];
    summary: { invoiced: number; collected: number; outstanding: number };
    structures: Structure[];
    classes: Option[];
    students: Option[];
    filters: { status: string | null; class_id: number | null };
}

const STATUS_STYLES: Record<string, string> = {
    unpaid: 'bg-red-50 text-red-600', partial: 'bg-amber-50 text-amber-600',
    paid: 'bg-emerald-50 text-emerald-600', waived: 'bg-slate-100 text-slate-500', void: 'bg-slate-100 text-slate-400',
};

export default function Index({ invoices, summary, structures, classes, students, filters }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent as string);
    const inputCls = 'w-full px-3 py-2 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 bg-white';

    /* fee-structure form */
    const emptyStruct = { name: '', amount_etb: '', frequency: 'annual', applies_to: 'all', target_class_id: '', academic_year: '', is_active: true };
    const [addingStruct, setAddingStruct] = useState(false);
    const [struct, setStruct] = useState<Record<string, string | boolean>>(emptyStruct);
    function submitStruct(e: React.FormEvent) {
        e.preventDefault();
        router.post('/school/fees/structures', struct, { preserveScroll: true, onSuccess: () => { setAddingStruct(false); setStruct(emptyStruct); } });
    }
    function generate(id: number) {
        if (!confirm('Generate invoices for every matching active student? Students who already have one are skipped.')) return;
        router.post(`/school/fees/structures/${id}/generate`, {}, { preserveScroll: true });
    }

    /* payment modal */
    const [payFor, setPayFor] = useState<Invoice | null>(null);
    const [pay, setPay] = useState({ amount_etb: '', method: 'cash', reference: '', payment_date: new Date().toISOString().slice(0, 10), note: '' });
    function submitPay(e: React.FormEvent) {
        e.preventDefault();
        if (!payFor) return;
        router.post(`/school/fees/invoices/${payFor.id}/payments`, pay, {
            preserveScroll: true,
            onSuccess: () => { setPayFor(null); setPay({ amount_etb: '', method: 'cash', reference: '', payment_date: new Date().toISOString().slice(0, 10), note: '' }); },
        });
    }

    /* ad-hoc invoice */
    const emptyInv = { student_id: '', title: '', amount_etb: '', due_date: '', academic_year: '', note: '' };
    const [addingInv, setAddingInv] = useState(false);
    const [inv, setInv] = useState<Record<string, string>>(emptyInv);
    function submitInv(e: React.FormEvent) {
        e.preventDefault();
        router.post('/school/fees/invoices', inv, { preserveScroll: true, onSuccess: () => { setAddingInv(false); setInv(emptyInv); } });
    }

    const setFilter = (key: string, val: string) => router.get('/school/fees', { ...filters, [key]: val || undefined }, { preserveState: false });

    return (
        <SchoolLayout>
            <Head title="Fees" />

            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900">Fees</h1>
                <p className="text-sm text-slate-500 mt-1">Define fee types, bill students, and record payments. Balances update automatically.</p>
            </div>

            {/* Summary */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                {[
                    { label: 'Total Invoiced', value: summary.invoiced, icon: Wallet, tone: 'text-slate-800' },
                    { label: 'Collected', value: summary.collected, icon: HandCoins, tone: 'text-emerald-600' },
                    { label: 'Outstanding', value: summary.outstanding, icon: Ban, tone: 'text-red-600' },
                ].map(s => (
                    <Card key={s.label} className="p-5 flex items-center gap-4">
                        <div className="w-11 h-11 rounded-2xl flex items-center justify-center" style={{ background: `${accent}14`, color: accent }}>
                            <s.icon className="w-5 h-5" />
                        </div>
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400">{s.label}</p>
                            <p className={`text-xl font-extrabold ${s.tone}`}>{etb(s.value)} <span className="text-xs font-semibold text-slate-400">ETB</span></p>
                        </div>
                    </Card>
                ))}
            </div>

            {/* Fee types */}
            <Card className="p-6 mb-6">
                <div className="flex items-center justify-between mb-4">
                    <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>Fee Types</p>
                    {!addingStruct && (
                        <button onClick={() => setAddingStruct(true)} className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-white" style={{ background: accent }}>
                            <Plus className="w-3.5 h-3.5" /> Add fee type
                        </button>
                    )}
                </div>

                {addingStruct && (
                    <form onSubmit={submitStruct} className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-5 p-4 rounded-2xl bg-slate-50">
                        <input required placeholder="Name (e.g. Grade 1 Tuition)" value={struct.name as string} onChange={e => setStruct(s => ({ ...s, name: e.target.value }))} className={inputCls} />
                        <input required type="number" min="0" step="0.01" placeholder="Amount ETB" value={struct.amount_etb as string} onChange={e => setStruct(s => ({ ...s, amount_etb: e.target.value }))} className={inputCls} />
                        <select value={struct.frequency as string} onChange={e => setStruct(s => ({ ...s, frequency: e.target.value }))} className={inputCls}>
                            <option value="one_time">One-time</option><option value="monthly">Monthly</option><option value="term">Per term</option><option value="annual">Annual</option>
                        </select>
                        <select value={struct.applies_to as string} onChange={e => setStruct(s => ({ ...s, applies_to: e.target.value }))} className={inputCls}>
                            <option value="all">All students</option><option value="class">One class</option>
                        </select>
                        {struct.applies_to === 'class' && (
                            <select required value={struct.target_class_id as string} onChange={e => setStruct(s => ({ ...s, target_class_id: e.target.value }))} className={inputCls}>
                                <option value="">Class…</option>
                                {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        )}
                        <input placeholder="Academic year (optional)" value={struct.academic_year as string} onChange={e => setStruct(s => ({ ...s, academic_year: e.target.value }))} className={inputCls} />
                        <div className="col-span-2 md:col-span-3 flex gap-2">
                            <button type="submit" className="px-3.5 py-2 rounded-xl text-xs font-bold text-white" style={{ background: accent }}>Save</button>
                            <button type="button" onClick={() => setAddingStruct(false)} className="px-3 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100">Cancel</button>
                        </div>
                    </form>
                )}

                {structures.length === 0 ? (
                    <EmptyState
                        icon={Wallet} accent={accent}
                        title="No fee types yet"
                        description="Create a fee type (like tuition or registration), then generate invoices for your students in one click."
                        actionLabel="Add fee type"
                        onAction={() => setAddingStruct(true)}
                    />
                ) : (
                    <div className="space-y-2">
                        {structures.map(f => (
                            <div key={f.id} className="flex items-center justify-between gap-3 px-4 py-2.5 rounded-xl border border-slate-100 text-sm">
                                <div className="min-w-0">
                                    <span className="font-bold text-slate-800">{f.name}</span>
                                    <span className="text-slate-500"> · {etb(f.amount)} ETB · {f.frequency.replace('_', '-')} · {f.applies_to === 'class' ? (f.target_class ?? 'class') : 'all students'}</span>
                                </div>
                                <div className="flex items-center gap-2 flex-shrink-0">
                                    <button onClick={() => generate(f.id)} className="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-[11px] font-bold text-white" style={{ background: accent }}>
                                        <Users2 className="w-3.5 h-3.5" /> Generate invoices
                                    </button>
                                    <button onClick={() => router.delete(`/school/fees/structures/${f.id}`, { preserveScroll: true })} className="p-1.5 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50">
                                        <Trash2 className="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            {/* Invoices */}
            <Card className="p-6">
                <div className="flex items-center justify-between gap-3 flex-wrap mb-4">
                    <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>Invoices</p>
                    <div className="flex items-center gap-2 flex-wrap">
                        <select value={filters.status ?? ''} onChange={e => setFilter('status', e.target.value)} className="px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 bg-white">
                            <option value="">All statuses</option>
                            <option value="unpaid">Unpaid</option><option value="partial">Partial</option><option value="paid">Paid</option><option value="waived">Waived</option>
                        </select>
                        <select value={filters.class_id ?? ''} onChange={e => setFilter('class_id', e.target.value)} className="px-3 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-600 bg-white">
                            <option value="">All classes</option>
                            {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                        {!addingInv && (
                            <button onClick={() => setAddingInv(true)} className="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold text-white" style={{ background: accent }}>
                                <Plus className="w-3.5 h-3.5" /> Add invoice
                            </button>
                        )}
                    </div>
                </div>

                {addingInv && (
                    <form onSubmit={submitInv} className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 p-4 rounded-2xl bg-slate-50">
                        <select required value={inv.student_id} onChange={e => setInv(f => ({ ...f, student_id: e.target.value }))} className={inputCls}>
                            <option value="">Student…</option>
                            {students.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                        </select>
                        <input required placeholder="Title" value={inv.title} onChange={e => setInv(f => ({ ...f, title: e.target.value }))} className={inputCls} />
                        <input required type="number" min="0.01" step="0.01" placeholder="Amount ETB" value={inv.amount_etb} onChange={e => setInv(f => ({ ...f, amount_etb: e.target.value }))} className={inputCls} />
                        <input type="date" value={inv.due_date} onChange={e => setInv(f => ({ ...f, due_date: e.target.value }))} className={inputCls} />
                        <div className="col-span-2 md:col-span-4 flex gap-2">
                            <button type="submit" className="px-3.5 py-2 rounded-xl text-xs font-bold text-white" style={{ background: accent }}>Save</button>
                            <button type="button" onClick={() => setAddingInv(false)} className="px-3 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100">Cancel</button>
                        </div>
                    </form>
                )}

                {invoices.length === 0 ? (
                    <EmptyState
                        icon={ReceiptText} accent={accent}
                        title={filters.status || filters.class_id ? 'No invoices match this filter' : 'No invoices yet'}
                        description={filters.status || filters.class_id
                            ? 'Try clearing the filters above to see all invoices.'
                            : 'Generate invoices from a fee type above, or add a one-off invoice for a single student.'}
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[11px] uppercase tracking-wider text-slate-400 border-b border-slate-100">
                                    <th className="py-2 pr-3">Student</th><th className="py-2 pr-3">Title</th>
                                    <th className="py-2 pr-3 text-right">Amount</th><th className="py-2 pr-3 text-right">Paid</th>
                                    <th className="py-2 pr-3 text-right">Balance</th><th className="py-2 pr-3">Status</th><th className="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.map(i => (
                                    <tr key={i.id} className="border-b border-slate-50">
                                        <td className="py-2.5 pr-3">
                                            <p className="font-semibold text-slate-800">{i.student ?? '—'}</p>
                                            <p className="text-[11px] text-slate-400">{i.class ?? ''}</p>
                                        </td>
                                        <td className="py-2.5 pr-3 text-slate-600">{i.title}</td>
                                        <td className="py-2.5 pr-3 text-right text-slate-700">{etb(i.amount)}</td>
                                        <td className="py-2.5 pr-3 text-right text-emerald-600">{etb(i.paid)}</td>
                                        <td className="py-2.5 pr-3 text-right font-bold text-slate-800">{etb(i.balance)}</td>
                                        <td className="py-2.5 pr-3">
                                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${STATUS_STYLES[i.status] ?? 'bg-slate-100 text-slate-500'}`}>{i.status}</span>
                                        </td>
                                        <td className="py-2.5 text-right whitespace-nowrap">
                                            {['unpaid', 'partial'].includes(i.status) && (
                                                <button onClick={() => { setPayFor(i); setPay(p => ({ ...p, amount_etb: String(i.balance) })); }} className="px-2.5 py-1.5 rounded-lg text-[11px] font-bold text-white" style={{ background: accent }}>Record payment</button>
                                            )}
                                            {i.status !== 'paid' && i.status !== 'waived' && (
                                                <button onClick={() => { if (confirm('Waive (forgive) this invoice?')) router.patch(`/school/fees/invoices/${i.id}/waive`, {}, { preserveScroll: true }); }} className="ml-1 px-2 py-1.5 rounded-lg text-[11px] font-bold text-slate-500 hover:bg-slate-100">Waive</button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </Card>

            {/* Payment modal */}
            {payFor && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onClick={() => setPayFor(null)}>
                    <div className="bg-white rounded-3xl p-6 w-full max-w-sm shadow-2xl" onClick={e => e.stopPropagation()}>
                        <h3 className="font-extrabold text-slate-900 mb-1">Record payment</h3>
                        <p className="text-xs text-slate-500 mb-4">{payFor.student} · {payFor.title} · balance {etb(payFor.balance)} ETB</p>
                        <form onSubmit={submitPay} className="space-y-3">
                            <input required type="number" min="0.01" max={payFor.balance} step="0.01" placeholder="Amount ETB" value={pay.amount_etb} onChange={e => setPay(p => ({ ...p, amount_etb: e.target.value }))} className={inputCls} />
                            <select value={pay.method} onChange={e => setPay(p => ({ ...p, method: e.target.value }))} className={inputCls}>
                                <option value="cash">Cash</option><option value="bank_transfer">Bank transfer</option><option value="telebirr">Telebirr</option><option value="other">Other</option>
                            </select>
                            <input placeholder="Reference (optional)" value={pay.reference} onChange={e => setPay(p => ({ ...p, reference: e.target.value }))} className={inputCls} />
                            <input required type="date" value={pay.payment_date} onChange={e => setPay(p => ({ ...p, payment_date: e.target.value }))} className={inputCls} />
                            <div className="flex gap-2 pt-1">
                                <button type="submit" className="flex-1 py-2.5 rounded-xl text-sm font-bold text-white" style={{ background: accent }}>Save payment</button>
                                <button type="button" onClick={() => setPayFor(null)} className="px-4 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:bg-slate-100">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </SchoolLayout>
    );
}
