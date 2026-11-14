import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Plus, Search, UserPlus, Phone, ArrowRight, Inbox, GraduationCap } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

/* ── colour helpers ──────────────────────────────────────────── */
function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

const STATUS_COLORS: Record<string, [string, string]> = {
    enquiry:   ['#f1f5f9', '#475569'],
    applied:   ['#dbeafe', '#1d4ed8'],
    screening: ['#fef3c7', '#b45309'],
    offered:   ['#e0e7ff', '#4338ca'],
    accepted:  ['#d1fae5', '#047857'],
    rejected:  ['#fee2e2', '#b91c1c'],
    withdrawn: ['#f3f4f6', '#6b7280'],
    converted: ['#dcfce7', '#15803d'],
};

interface ApplicationRow {
    id: number;
    reference: string;
    name: string;
    gender: string | null;
    status: string;
    status_label: string;
    source: string;
    source_label: string;
    guardian_name: string | null;
    guardian_phone: string | null;
    programme: string | null;
    class_name: string | null;
    intended_level: string | null;
    student_id: number | null;
    student_uid: string | null;
    age_days: number;
    created_at: string;
}

interface Paginated {
    data: ApplicationRow[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    applications: Paginated;
    counts: Record<string, number>;
    statuses: { value: string; label: string }[];
    filters: { status?: string; search?: string };
    is_admin: boolean;
}

const PIPELINE = ['applied', 'screening', 'offered', 'accepted'] as const;

export default function Index({ applications, counts, statuses, filters, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const [search, setSearch] = useState(filters.search ?? '');

    function applyFilter(status: string) {
        router.get('/school/admissions', { status: status || undefined, search: search || undefined },
            { preserveState: true, preserveScroll: true });
    }

    function submitSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get('/school/admissions', { status: filters.status, search: search || undefined },
            { preserveState: true });
    }

    const open = (counts.applied ?? 0) + (counts.screening ?? 0) + (counts.offered ?? 0) + (counts.accepted ?? 0);

    return (
        <SchoolLayout>
            <Head title="Admissions" />

            <div className="flex items-center justify-between mb-6 flex-wrap gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Admissions</h1>
                    <p className="text-xs text-slate-400 mt-0.5">
                        From first enquiry to enrolled student
                    </p>
                </div>
                {is_admin && (
                    <Link
                        href="/school/admissions/create"
                        className="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-2xl transition"
                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}
                    >
                        <UserPlus className="w-4 h-4" /> New application
                    </Link>
                )}
            </div>

            {/* Pipeline counters */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                {PIPELINE.map(key => {
                    const [bg, fg] = STATUS_COLORS[key];
                    const label = statuses.find(s => s.value === key)?.label ?? key;
                    const active = filters.status === key;
                    return (
                        <button
                            key={key}
                            onClick={() => applyFilter(active ? '' : key)}
                            className="text-left p-4 rounded-3xl border transition"
                            style={{
                                background: active ? bg : '#fff',
                                borderColor: active ? fg : 'rgb(226 232 240 / 0.6)',
                            }}
                        >
                            <p className="text-2xl font-extrabold" style={{ color: fg }}>
                                {counts[key] ?? 0}
                            </p>
                            <p className="text-xs font-semibold text-slate-500 mt-0.5">{label}</p>
                        </button>
                    );
                })}
            </div>

            {/* Filters */}
            <div className="bg-white rounded-3xl border border-slate-200 p-4 mb-5 flex items-center gap-3 flex-wrap">
                <form onSubmit={submitSearch} className="flex-1 min-w-[220px] flex items-center gap-2">
                    <div className="relative flex-1">
                        <Search className="w-4 h-4 text-slate-300 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            placeholder="Name, reference or phone…"
                            className="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 text-sm focus:outline-none focus:ring-2"
                            style={{ ['--tw-ring-color' as string]: alpha(accent, 0.35) }}
                        />
                    </div>
                    <button type="submit"
                        className="px-4 py-2 rounded-xl text-sm font-bold text-white"
                        style={{ background: accent }}>
                        Search
                    </button>
                </form>

                <select
                    value={filters.status ?? ''}
                    onChange={e => applyFilter(e.target.value)}
                    className="px-3 py-2 rounded-xl border border-slate-200 text-sm bg-white"
                >
                    <option value="">All applications</option>
                    <option value="open">Open only ({open})</option>
                    {statuses.map(s => (
                        <option key={s.value} value={s.value}>
                            {s.label}{counts[s.value] ? ` (${counts[s.value]})` : ''}
                        </option>
                    ))}
                </select>
            </div>

            {/* Table */}
            {applications.data.length === 0 ? (
                <div className="bg-white rounded-3xl border border-slate-200 p-12 text-center">
                    <Inbox className="w-10 h-10 text-slate-200 mx-auto mb-3" />
                    <p className="text-sm font-semibold text-slate-500">No applications yet</p>
                    <p className="text-xs text-slate-400 mt-1">
                        Record a walk-in or phone enquiry and it will appear here.
                    </p>
                </div>
            ) : (
                <div className="bg-white rounded-3xl border border-slate-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left">
                                    <th className="px-5 py-3 text-[11px] font-bold uppercase tracking-widest text-slate-400">Applicant</th>
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-widest text-slate-400">Applied for</th>
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-widest text-slate-400">Contact</th>
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-widest text-slate-400">Status</th>
                                    <th className="px-4 py-3 text-[11px] font-bold uppercase tracking-widest text-slate-400">Age</th>
                                    <th className="px-4 py-3"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {applications.data.map(a => {
                                    const [bg, fg] = STATUS_COLORS[a.status] ?? ['#f1f5f9', '#475569'];
                                    return (
                                        <tr key={a.id} className="hover:bg-slate-50/60 transition">
                                            <td className="px-5 py-3">
                                                <Link href={`/school/admissions/${a.id}`} className="block">
                                                    <p className="font-bold text-slate-800">{a.name}</p>
                                                    <p className="text-[11px] text-slate-400 font-mono">{a.reference}</p>
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3">
                                                <p className="text-xs text-slate-600">
                                                    {a.class_name ?? a.intended_level ?? a.programme ?? <span className="text-slate-300">—</span>}
                                                </p>
                                                {a.programme && a.class_name && (
                                                    <p className="text-[11px] text-slate-400">{a.programme}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {a.guardian_phone ? (
                                                    <a href={`tel:${a.guardian_phone}`}
                                                        className="flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900">
                                                        <Phone className="w-3 h-3" /> {a.guardian_phone}
                                                    </a>
                                                ) : <span className="text-xs text-slate-300">—</span>}
                                                {a.guardian_name && (
                                                    <p className="text-[11px] text-slate-400 mt-0.5">{a.guardian_name}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold"
                                                    style={{ background: bg, color: fg }}>
                                                    {a.status_label}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span className={`text-xs font-semibold ${a.age_days > 14 && a.status !== 'converted' ? 'text-amber-600' : 'text-slate-400'}`}>
                                                    {a.age_days}d
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {a.student_id ? (
                                                    <Link href={`/school/students/${a.student_id}`}
                                                        className="inline-flex items-center gap-1 text-[11px] font-bold"
                                                        style={{ color: accent }}>
                                                        <GraduationCap className="w-3 h-3" /> {a.student_uid}
                                                    </Link>
                                                ) : (
                                                    <Link href={`/school/admissions/${a.id}`}
                                                        className="inline-flex items-center gap-1 text-[11px] font-bold text-slate-400 hover:text-slate-700">
                                                        Open <ArrowRight className="w-3 h-3" />
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {applications.last_page > 1 && (
                        <div className="px-5 py-3 border-t border-slate-100 flex items-center justify-between">
                            <p className="text-[11px] text-slate-400">{applications.total} applications</p>
                            <div className="flex items-center gap-1">
                                {applications.links.map((l, i) => (
                                    <button
                                        key={i}
                                        disabled={!l.url}
                                        onClick={() => l.url && router.get(l.url, {}, { preserveScroll: true })}
                                        className={`px-2.5 py-1 rounded-lg text-[11px] font-bold ${l.active ? 'text-white' : 'text-slate-500 hover:bg-slate-100'} disabled:opacity-30`}
                                        style={l.active ? { background: accent } : undefined}
                                        dangerouslySetInnerHTML={{ __html: l.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </SchoolLayout>
    );
}
