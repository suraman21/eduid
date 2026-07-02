import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Check, Pencil, X, Save, Users, GraduationCap } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge from '@/Components/Badge';

interface Plan {
    id: number;
    name: string;
    tier: 'starter' | 'standard' | 'premium';
    price_monthly_etb: number;
    price_annual_etb: number;
    max_students: number;
    max_teachers: number;
    max_school_admins: number;
    description: string | null;
    is_active: boolean;
    school_count: number;
}

interface Feature { key: string; label: string; category: string }
interface Matrix { [key: string]: { starter: boolean; standard: boolean; premium: boolean } }

interface SchoolRow {
    id: number;
    name: string;
    code: string;
    plan_tier: string;
    max_students: number;
    max_teachers: number;
    students_used: number;
    teachers_used: number;
    subscription_status: string;
    subscription_expires_at: string | null;
    override_count: number;
}

interface Props {
    plans: Plan[];
    features: Feature[];
    matrix: Matrix;
    schools: SchoolRow[];
}

const TIERS = ['starter', 'standard', 'premium'] as const;
const TIER_TONES: Record<string, string> = { starter: 'slate', standard: 'blue', premium: 'purple' };
const STATUS_TONES: Record<string, 'green' | 'amber' | 'rose' | 'slate'> = {
    active: 'green', grace: 'amber', expired: 'rose', suspended: 'rose',
};

const inputCls = 'w-full px-3 py-2 rounded-xl bg-slate-900/70 border border-white/10 text-sm text-slate-200 focus:outline-none focus:border-indigo-500/60 transition';

function fmtEtb(n: number) {
    return new Intl.NumberFormat('en-US').format(n) + ' ETB';
}

/* ── Plan card (inline editable) ─────────────────────────────── */
function PlanCard({ plan }: { plan: Plan }) {
    const [editing, setEditing] = useState(false);
    const [form, setForm] = useState({
        price_monthly_etb: plan.price_monthly_etb,
        price_annual_etb: plan.price_annual_etb,
        max_students: plan.max_students,
        max_teachers: plan.max_teachers,
        max_school_admins: plan.max_school_admins,
        description: plan.description ?? '',
    });
    const [saving, setSaving] = useState(false);

    function save() {
        setSaving(true);
        router.patch(`/admin/packages/plans/${plan.id}`, form, {
            preserveScroll: true,
            onFinish: () => { setSaving(false); setEditing(false); },
        });
    }

    const num = (k: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
        setForm({ ...form, [k]: Number(e.target.value) });

    return (
        <div className="rounded-2xl bg-white/[0.03] border border-white/10 p-5 flex flex-col">
            <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                    <p className="font-bold text-slate-100">{plan.name}</p>
                    <Badge tone={TIER_TONES[plan.tier] as never}>{plan.tier}</Badge>
                </div>
                <button
                    onClick={() => setEditing(!editing)}
                    className="p-1.5 rounded-lg text-slate-500 hover:text-slate-200 hover:bg-white/10 transition"
                    title={editing ? 'Cancel' : 'Edit plan'}
                >
                    {editing ? <X className="w-4 h-4" /> : <Pencil className="w-4 h-4" />}
                </button>
            </div>

            {editing ? (
                <div className="space-y-2.5 text-sm">
                    <div className="grid grid-cols-2 gap-2.5">
                        <label className="block">
                            <span className="text-[10px] uppercase tracking-wider text-slate-500">Setup (ETB)</span>
                            <input type="number" value={form.price_monthly_etb} onChange={num('price_monthly_etb')} className={inputCls} />
                        </label>
                        <label className="block">
                            <span className="text-[10px] uppercase tracking-wider text-slate-500">Annual (ETB)</span>
                            <input type="number" value={form.price_annual_etb} onChange={num('price_annual_etb')} className={inputCls} />
                        </label>
                        <label className="block">
                            <span className="text-[10px] uppercase tracking-wider text-slate-500">Max students</span>
                            <input type="number" value={form.max_students} onChange={num('max_students')} className={inputCls} />
                        </label>
                        <label className="block">
                            <span className="text-[10px] uppercase tracking-wider text-slate-500">Max teachers</span>
                            <input type="number" value={form.max_teachers} onChange={num('max_teachers')} className={inputCls} />
                        </label>
                        <label className="block">
                            <span className="text-[10px] uppercase tracking-wider text-slate-500">Max admins</span>
                            <input type="number" value={form.max_school_admins} onChange={num('max_school_admins')} className={inputCls} />
                        </label>
                    </div>
                    <label className="block">
                        <span className="text-[10px] uppercase tracking-wider text-slate-500">Description</span>
                        <input value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} className={inputCls} />
                    </label>
                    <button
                        onClick={save}
                        disabled={saving}
                        className="w-full mt-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-bold transition disabled:opacity-50"
                    >
                        <Save className="w-4 h-4" /> {saving ? 'Saving…' : 'Save plan'}
                    </button>
                </div>
            ) : (
                <>
                    <p className="text-2xl font-extrabold text-white">{fmtEtb(plan.price_monthly_etb)}</p>
                    <p className="text-xs text-slate-500 mb-4">setup · {fmtEtb(plan.price_annual_etb)} / year</p>
                    <div className="space-y-1.5 text-sm text-slate-300">
                        <p className="flex items-center gap-2"><GraduationCap className="w-4 h-4 text-slate-500" /> {plan.max_students >= 999999 ? 'Unlimited' : plan.max_students} students</p>
                        <p className="flex items-center gap-2"><Users className="w-4 h-4 text-slate-500" /> {plan.max_teachers >= 999 ? 'Unlimited' : plan.max_teachers} teachers · {plan.max_school_admins} admin{plan.max_school_admins > 1 ? 's' : ''}</p>
                    </div>
                    {plan.description && <p className="text-xs text-slate-500 mt-3">{plan.description}</p>}
                    <p className="text-[11px] text-slate-600 mt-auto pt-4">{plan.school_count} school{plan.school_count === 1 ? '' : 's'} on this plan</p>
                </>
            )}
        </div>
    );
}

/* ── Per-school package row ──────────────────────────────────── */
function SchoolPackageRow({ s }: { s: SchoolRow }) {
    const [form, setForm] = useState({
        plan_tier: s.plan_tier,
        apply_plan_limits: false,
        max_students: s.max_students,
        max_teachers: s.max_teachers,
        subscription_status: s.subscription_status,
        subscription_expires_at: s.subscription_expires_at ?? '',
    });
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);

    const set = (patch: Partial<typeof form>) => { setForm({ ...form, ...patch }); setDirty(true); };

    function save() {
        setSaving(true);
        router.patch(`/admin/packages/schools/${s.id}`, {
            ...form,
            subscription_expires_at: form.subscription_expires_at || null,
        }, {
            preserveScroll: true,
            onFinish: () => { setSaving(false); setDirty(false); },
        });
    }

    const seatsTone = (used: number, max: number) =>
        used >= max ? 'text-rose-400' : used / Math.max(max, 1) > 0.85 ? 'text-amber-400' : 'text-slate-400';

    return (
        <tr className="hover:bg-white/5 transition align-middle">
            <td className="px-5 py-3">
                <p className="text-slate-200 font-semibold">{s.name}</p>
                <p className="text-xs text-slate-500 font-mono">{s.code}</p>
            </td>
            <td className="px-3 py-3">
                <select value={form.plan_tier} onChange={e => set({ plan_tier: e.target.value, apply_plan_limits: true })} className={inputCls}>
                    {TIERS.map(t => <option key={t} value={t}>{t}</option>)}
                </select>
            </td>
            <td className="px-3 py-3 whitespace-nowrap">
                <div className="flex items-center gap-1.5">
                    <input
                        type="number"
                        value={form.max_students}
                        onChange={e => set({ max_students: Number(e.target.value), apply_plan_limits: false })}
                        className={`${inputCls} w-20`}
                    />
                    <span className={`text-xs ${seatsTone(s.students_used, form.max_students)}`}>{s.students_used} used</span>
                </div>
            </td>
            <td className="px-3 py-3 whitespace-nowrap">
                <div className="flex items-center gap-1.5">
                    <input
                        type="number"
                        value={form.max_teachers}
                        onChange={e => set({ max_teachers: Number(e.target.value), apply_plan_limits: false })}
                        className={`${inputCls} w-16`}
                    />
                    <span className={`text-xs ${seatsTone(s.teachers_used, form.max_teachers)}`}>{s.teachers_used} used</span>
                </div>
            </td>
            <td className="px-3 py-3">
                <select value={form.subscription_status} onChange={e => set({ subscription_status: e.target.value })} className={inputCls}>
                    {['active', 'grace', 'expired', 'suspended'].map(st => <option key={st} value={st}>{st}</option>)}
                </select>
            </td>
            <td className="px-3 py-3">
                <input
                    type="date"
                    value={form.subscription_expires_at}
                    onChange={e => set({ subscription_expires_at: e.target.value })}
                    className={inputCls}
                />
            </td>
            <td className="px-3 py-3 text-center">
                <Link
                    href={`/admin/features?school_id=${s.id}`}
                    className="text-xs text-indigo-400 hover:text-indigo-300 transition"
                    title="Per-school feature overrides"
                >
                    {s.override_count > 0 ? `${s.override_count} override${s.override_count > 1 ? 's' : ''}` : 'overrides'}
                </Link>
            </td>
            <td className="px-5 py-3 text-right">
                {dirty ? (
                    <button
                        onClick={save}
                        disabled={saving}
                        className="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition disabled:opacity-50"
                    >
                        {saving ? 'Saving…' : 'Save'}
                    </button>
                ) : (
                    <Badge tone={STATUS_TONES[s.subscription_status] ?? 'slate'}>{s.subscription_status}</Badge>
                )}
            </td>
        </tr>
    );
}

/* ── Page ────────────────────────────────────────────────────── */
export default function Index({ plans, features, matrix, schools }: Props) {
    const byCategory = features.reduce<Record<string, Feature[]>>((acc, f) => {
        (acc[f.category] = acc[f.category] || []).push(f);
        return acc;
    }, {});

    function toggle(tier: string, key: string, current: boolean) {
        router.post('/admin/packages/plan-features', {
            plan_tier: tier,
            feature_key: key,
            is_enabled: !current,
        }, { preserveScroll: true });
    }

    return (
        <AdminLayout>
            <Head title="Packages" />
            <PageHeader
                title="Packages"
                subtitle="Plan tiers, included feature modules, and every school's package — the revenue engine."
            />

            {/* Plans */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                {plans.map(p => <PlanCard key={p.id} plan={p} />)}
            </div>

            {/* Plan × feature matrix */}
            <div className="rounded-2xl bg-white/[0.03] border border-white/10 overflow-hidden mb-8">
                <div className="px-5 py-4 border-b border-white/10 flex items-center justify-between">
                    <div>
                        <p className="font-bold text-slate-100">Included modules per plan</p>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Click a cell to include/exclude a module. Changes apply to every school on the tier immediately
                            (per-school overrides on the Features page still win).
                        </p>
                    </div>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wider text-slate-500 border-b border-white/10">
                                <th className="px-5 py-3">Module</th>
                                {TIERS.map(t => <th key={t} className="px-5 py-3 text-center capitalize">{t}</th>)}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {Object.entries(byCategory).map(([cat, feats]) => (
                                <React.Fragment key={cat}>
                                    <tr className="bg-white/[0.02]">
                                        <td colSpan={4} className="px-5 py-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">{cat}</td>
                                    </tr>
                                    {feats.map(f => (
                                        <tr key={f.key} className="hover:bg-white/5 transition">
                                            <td className="px-5 py-3">
                                                <p className="text-slate-200">{f.label}</p>
                                                <p className="text-xs text-slate-600 font-mono">{f.key}</p>
                                            </td>
                                            {TIERS.map(t => {
                                                const on = matrix[f.key]?.[t] ?? false;
                                                return (
                                                    <td key={t} className="px-5 py-3 text-center">
                                                        <button
                                                            onClick={() => toggle(t, f.key, on)}
                                                            className={`w-9 h-9 rounded-xl border transition inline-flex items-center justify-center ${
                                                                on
                                                                    ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400 hover:bg-emerald-500/25'
                                                                    : 'bg-white/[0.03] border-white/10 text-slate-600 hover:border-white/25'
                                                            }`}
                                                            title={`${on ? 'Remove from' : 'Add to'} ${t}`}
                                                        >
                                                            {on ? <Check className="w-4 h-4" /> : <X className="w-4 h-4" />}
                                                        </button>
                                                    </td>
                                                );
                                            })}
                                        </tr>
                                    ))}
                                </React.Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Per-school packages */}
            <div className="rounded-2xl bg-white/[0.03] border border-white/10 overflow-hidden">
                <div className="px-5 py-4 border-b border-white/10">
                    <p className="font-bold text-slate-100">School packages</p>
                    <p className="text-xs text-slate-500 mt-0.5">
                        Changing the plan re-applies that plan's seat limits; edit a seat number to customise it instead.
                        Limits can never go below current usage.
                    </p>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-xs uppercase tracking-wider text-slate-500 border-b border-white/10">
                                <th className="px-5 py-3">School</th>
                                <th className="px-3 py-3">Plan</th>
                                <th className="px-3 py-3">Student seats</th>
                                <th className="px-3 py-3">Teacher seats</th>
                                <th className="px-3 py-3">Status</th>
                                <th className="px-3 py-3">Expires</th>
                                <th className="px-3 py-3 text-center">Features</th>
                                <th className="px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {schools.map(s => <SchoolPackageRow key={s.id} s={s} />)}
                            {schools.length === 0 && (
                                <tr><td colSpan={8} className="px-5 py-10 text-center text-slate-500">No schools yet.</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AdminLayout>
    );
}
