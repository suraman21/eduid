import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Check } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge from '@/Components/Badge';

interface Feature { key: string; label: string; category: string; }

interface PlanMatrix {
    [featureKey: string]: { starter: boolean; standard: boolean; premium: boolean };
}

interface Override {
    feature_key: string;
    is_enabled: boolean;
}

interface School { id: number; name: string; plan_tier: string; }

interface Props {
    features: Feature[];
    plan_matrix: PlanMatrix;
    schools: School[];
    selected_school: School | null;
    overrides: Override[];
    filters: { school_id?: string };
}

function groupBy<T>(arr: T[], key: keyof T): Record<string, T[]> {
    return arr.reduce((acc, item) => {
        const k = String(item[key]);
        (acc[k] = acc[k] || []).push(item);
        return acc;
    }, {} as Record<string, T[]>);
}

function Tick({ on }: { on: boolean }) {
    return on
        ? <Check className="w-5 h-5 text-emerald-400 mx-auto" />
        : <span className="text-slate-700 block text-center">—</span>;
}

function OverrideRow({ schoolId, feature, planDefault, override }: {
    schoolId: number;
    feature: Feature;
    planDefault: boolean;
    override: Override | undefined;
}) {
    const setForm    = useForm({ feature_key: feature.key, is_enabled: '1' });
    const removeForm = useForm({});

    function set(e: React.FormEvent) {
        e.preventDefault();
        setForm.post(`/admin/features/override/${schoolId}`);
    }

    function remove(e: React.FormEvent) {
        e.preventDefault();
        removeForm.delete(`/admin/features/override/${schoolId}/${feature.key}`);
    }

    return (
        <tr className="hover:bg-white/5 transition">
            <td className="px-5 py-3">
                <p className="text-slate-200">{feature.label}</p>
                <p className="text-xs text-slate-500 font-mono">{feature.key}</p>
            </td>
            <td className="px-5 py-3">
                <Badge tone={planDefault ? 'green' : 'slate'}>{planDefault ? 'Enabled' : 'Disabled'}</Badge>
            </td>
            <td className="px-5 py-3">
                {override ? (
                    <div className="flex items-center gap-3">
                        <Badge tone={override.is_enabled ? 'green' : 'rose'}>
                            {override.is_enabled ? 'Enabled' : 'Disabled'}
                        </Badge>
                        <form onSubmit={remove}>
                            <button type="submit" disabled={removeForm.processing} className="text-xs text-slate-500 hover:text-rose-400 transition">
                                Remove
                            </button>
                        </form>
                    </div>
                ) : (
                    <form onSubmit={set} className="flex items-center gap-2">
                        <input type="hidden" value={feature.key} />
                        <select
                            value={setForm.data.is_enabled}
                            onChange={e => setForm.setData('is_enabled', e.target.value)}
                            className="bg-white/5 border border-white/10 rounded-lg px-2 py-1 text-xs text-slate-300 focus:outline-none focus:border-purple-500"
                        >
                            <option value="1">Enable</option>
                            <option value="0">Disable</option>
                        </select>
                        <button type="submit" disabled={setForm.processing} className="text-xs bg-white/5 hover:bg-white/10 text-slate-200 px-2 py-1 rounded-lg transition">
                            Set
                        </button>
                    </form>
                )}
            </td>
        </tr>
    );
}

export default function Index({ features, plan_matrix, schools, selected_school, overrides, filters }: Props) {
    const [schoolId, setSchoolId] = useState(filters.school_id ?? '');
    const grouped = groupBy(features, 'category');

    function viewSchool(e: React.FormEvent) {
        e.preventDefault();
        router.get('/admin/features', { school_id: schoolId }, { preserveState: true, replace: true });
    }

    const overrideMap = Object.fromEntries(overrides.map(o => [o.feature_key, o]));

    return (
        <AdminLayout>
            <Head title="Feature Flags" />
            <PageHeader title="Feature Flags" subtitle="Plan defaults and per-school overrides" />

            {/* Plan matrix */}
            <div className="bg-white/[0.025] rounded-xl border border-white/10 overflow-hidden mb-8">
                <div className="px-5 py-4 border-b border-white/10">
                    <h3 className="text-sm font-semibold text-slate-100">Plan Feature Matrix</h3>
                </div>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-white/10 text-left">
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Feature</th>
                                {(['starter', 'standard', 'premium'] as const).map(t => (
                                    <th key={t} className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase text-center capitalize">{t}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {Object.entries(grouped).map(([cat, feats]) => (
                                <>
                                    <tr key={cat} className="bg-white/[0.015]">
                                        <td colSpan={4} className="px-5 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">{cat}</td>
                                    </tr>
                                    {feats.map(f => (
                                        <tr key={f.key} className="hover:bg-white/5 transition">
                                            <td className="px-5 py-3">
                                                <p className="text-slate-200">{f.label}</p>
                                                <p className="text-xs text-slate-500 font-mono">{f.key}</p>
                                            </td>
                                            {(['starter', 'standard', 'premium'] as const).map(t => (
                                                <td key={t} className="px-5 py-3">
                                                    <Tick on={plan_matrix[f.key]?.[t] ?? false} />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Per-school overrides */}
            <div className="bg-white/[0.025] rounded-xl border border-white/10 overflow-hidden">
                <div className="px-5 py-4 border-b border-white/10 flex items-center justify-between gap-4 flex-wrap">
                    <h3 className="text-sm font-semibold text-slate-100">Per-School Overrides</h3>
                    <form onSubmit={viewSchool} className="flex items-center gap-2">
                        <select
                            value={schoolId}
                            onChange={e => setSchoolId(e.target.value)}
                            className="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-purple-500"
                        >
                            <option value="">Select a school…</option>
                            {schools.map(s => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                        <button type="submit" className="bg-white/5 hover:bg-white/10 border border-white/10 text-slate-200 px-4 py-2 rounded-lg text-sm transition">
                            View
                        </button>
                    </form>
                </div>

                {selected_school ? (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-white/10 text-left">
                                    <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Feature</th>
                                    <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Plan Default</th>
                                    <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Override</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-white/5">
                                {features.map(f => (
                                    <OverrideRow
                                        key={f.key}
                                        schoolId={selected_school.id}
                                        feature={f}
                                        planDefault={plan_matrix[f.key]?.[selected_school.plan_tier as 'starter' | 'standard' | 'premium'] ?? false}
                                        override={overrideMap[f.key]}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="px-5 py-12 text-center text-slate-500">
                        <p className="text-sm">Select a school above to view and manage overrides.</p>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}
