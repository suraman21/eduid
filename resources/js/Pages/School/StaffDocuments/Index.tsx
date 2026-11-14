import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle, CalendarClock, CheckCircle2, FileText, Search, UserX, ChevronLeft, ChevronRight, ExternalLink,
} from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { normHex } from '@/Components/CertificateLetter';

interface Doc {
    id: number;
    type: string;
    type_label: string;
    title: string;
    file_name: string | null;
    file_size: string;
    issued_on: string | null;
    expires_on: string | null;
    expiry_state: 'expired' | 'expiring' | 'valid' | 'none';
    days_left: number | null;
    is_expired: boolean;
    is_verified: boolean;
    staff: { id: number; name: string; role: string; job_title: string | null; employee_id: string | null; is_active: boolean };
}

interface Paginated {
    data: Doc[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Props {
    documents: Paginated;
    counts: { expired: number; expiring: number; valid: number; none: number; total: number };
    staff_without_documents: { id: number; name: string; role: string; job_title: string | null }[];
    types: { value: string; label: string }[];
    filters: { state: string | null; type: string | null; q: string };
    warn_days: number;
    is_admin: boolean;
}

const STATE_META: Record<string, { label: string; bg: string; text: string }> = {
    expired:  { label: 'Expired',   bg: '#fee2e2', text: '#b91c1c' },
    expiring: { label: 'Expiring',  bg: '#fef3c7', text: '#b45309' },
    valid:    { label: 'Valid',     bg: '#dcfce7', text: '#15803d' },
    none:     { label: 'No expiry', bg: '#f1f5f9', text: '#64748b' },
};

export default function Index({ documents, counts, staff_without_documents, types, filters, warn_days, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const [q, setQ] = useState(filters.q || '');

    function filter(patch: Record<string, string | null>) {
        router.get('/school/staff-documents', { ...filters, ...patch } as never, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }

    const tiles = [
        { key: 'expired',  value: counts.expired,  label: 'Expired',      hint: 'Action needed now',       icon: AlertTriangle,  color: '#dc2626', bg: '#fee2e2' },
        { key: 'expiring', value: counts.expiring, label: 'Expiring soon', hint: `Within ${warn_days} days`, icon: CalendarClock, color: '#d97706', bg: '#fef3c7' },
        { key: 'valid',    value: counts.valid,    label: 'In date',      hint: 'Nothing to do',            icon: CheckCircle2,   color: '#15803d', bg: '#dcfce7' },
        { key: 'total',    value: counts.total,    label: 'On file',      hint: 'All documents',            icon: FileText,       color: accent,    bg: `${accent}14` },
    ];

    return (
        <SchoolLayout>
            <Head title="Staff documents" />

            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Staff documents</h1>
                <p className="text-sm text-slate-500 mt-0.5">
                    Contracts, licences, clearances and permits — ordered by what lapses first.
                </p>
            </div>

            {/* Alert tiles */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                {tiles.map(t => {
                    const Icon = t.icon;
                    const active = filters.state === t.key || (t.key === 'total' && !filters.state);
                    return (
                        <button key={t.key}
                            onClick={() => filter({ state: t.key === 'total' ? null : t.key })}
                            className={`text-left p-4 rounded-3xl border transition ${active ? 'border-transparent' : 'border-slate-200 bg-white hover:border-slate-300'}`}
                            style={active ? { background: t.bg, boxShadow: `inset 0 0 0 1.5px ${t.color}` } : undefined}>
                            <div className="flex items-center gap-2 mb-2">
                                <span className="w-7 h-7 rounded-lg grid place-items-center" style={{ background: t.bg, color: t.color }}>
                                    <Icon className="w-3.5 h-3.5" />
                                </span>
                                <span className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{t.label}</span>
                            </div>
                            <p className="text-2xl font-extrabold" style={{ color: t.color }}>{t.value}</p>
                            <p className="text-[10px] text-slate-400">{t.hint}</p>
                        </button>
                    );
                })}
            </div>

            {/* Gap warning */}
            {staff_without_documents.length > 0 && (
                <div className="mb-6 p-4 rounded-3xl border border-amber-200 bg-amber-50">
                    <div className="flex items-start gap-3">
                        <UserX className="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                        <div className="min-w-0">
                            <p className="text-sm font-bold text-amber-900">
                                {staff_without_documents.length} active {staff_without_documents.length === 1 ? 'member has' : 'members have'} nothing on file
                            </p>
                            <p className="text-[11px] text-amber-700 mt-0.5 mb-2">
                                No contract, licence or clearance recorded. Often a new hire whose paperwork was never filed.
                            </p>
                            <div className="flex flex-wrap gap-1.5">
                                {staff_without_documents.slice(0, 12).map(m => (
                                    <Link key={m.id} href={`/school/staff/${m.id}`}
                                        className="text-[11px] font-bold px-2.5 py-1 rounded-full bg-white text-amber-800 border border-amber-200 hover:bg-amber-100">
                                        {m.name}
                                    </Link>
                                ))}
                                {staff_without_documents.length > 12 && (
                                    <span className="text-[11px] text-amber-600 px-2 py-1">
                                        +{staff_without_documents.length - 12} more
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Filters */}
            <div className="flex flex-wrap items-center gap-2 mb-4">
                <form onSubmit={e => { e.preventDefault(); filter({ q: q || null }); }} className="relative flex-1 min-w-[200px] max-w-md">
                    <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                    <input value={q} onChange={e => setQ(e.target.value)}
                        placeholder="Staff name, employee ID or title…"
                        className="w-full pl-10 pr-3 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:ring-2"
                        style={{ ['--tw-ring-color' as string]: `${accent}59` }} />
                </form>
                <select value={filters.type || ''} onChange={e => filter({ type: e.target.value || null })}
                    className="px-3 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-600 bg-white">
                    <option value="">All types</option>
                    {types.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                </select>
                {(filters.state || filters.type || filters.q) && (
                    <button onClick={() => { setQ(''); filter({ state: null, type: null, q: null }); }}
                        className="text-xs font-bold text-slate-400 hover:text-slate-700 px-2">
                        Clear
                    </button>
                )}
            </div>

            {/* Table */}
            {documents.data.length === 0 ? (
                <div className="bg-white rounded-3xl border border-slate-200 py-16 text-center">
                    <FileText className="w-10 h-10 text-slate-200 mx-auto mb-3" />
                    <p className="text-sm font-bold text-slate-700">Nothing here</p>
                    <p className="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                        {counts.total === 0
                            ? 'No staff paperwork has been filed yet. Open a staff member and upload their contract to start.'
                            : 'No documents match this filter.'}
                    </p>
                </div>
            ) : (
                <div className="bg-white rounded-3xl border border-slate-200 overflow-hidden">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-slate-50 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                                <th className="text-left px-4 py-2.5">Staff member</th>
                                <th className="text-left px-4 py-2.5 hidden sm:table-cell">Document</th>
                                <th className="text-left px-4 py-2.5 hidden md:table-cell">Expires</th>
                                <th className="text-right px-4 py-2.5">State</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {documents.data.map(d => {
                                const meta = STATE_META[d.expiry_state] ?? STATE_META.none;
                                return (
                                    <tr key={d.id} className="hover:bg-slate-50/60 transition">
                                        <td className="px-4 py-3">
                                            <Link href={`/school/staff/${d.staff.id}`} className="group">
                                                <p className="font-bold text-slate-800 group-hover:underline flex items-center gap-1.5">
                                                    {d.staff.name}
                                                    {!d.staff.is_active && (
                                                        <span className="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-400">inactive</span>
                                                    )}
                                                </p>
                                                <p className="text-[11px] text-slate-400">
                                                    {d.staff.job_title || d.staff.role.replace('_', ' ')}
                                                    {d.staff.employee_id ? ` · ${d.staff.employee_id}` : ''}
                                                </p>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 hidden sm:table-cell">
                                            <p className="text-slate-700 font-semibold truncate max-w-[220px]">{d.title}</p>
                                            <p className="text-[11px] text-slate-400">{d.type_label} · {d.file_size}</p>
                                        </td>
                                        <td className="px-4 py-3 hidden md:table-cell">
                                            <p className="text-slate-600 text-xs">{d.expires_on || '—'}</p>
                                            {d.days_left !== null && d.days_left >= 0 && d.expiry_state !== 'valid' && (
                                                <p className="text-[11px] text-slate-400">{d.days_left === 0 ? 'today' : `${d.days_left} days`}</p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <span className="inline-block text-[10px] font-bold px-2 py-1 rounded-full whitespace-nowrap"
                                                style={{ background: meta.bg, color: meta.text }}>
                                                {d.expiry_state === 'expiring' && d.days_left !== null
                                                    ? (d.days_left === 0 ? 'Expires today' : `${d.days_left}d left`)
                                                    : meta.label}
                                            </span>
                                            <a href={`/school/staff/${d.staff.id}/documents/${d.id}/download`}
                                                className="inline-block ml-2 text-slate-300 hover:text-slate-600 align-middle"
                                                title="Download">
                                                <ExternalLink className="w-3.5 h-3.5 inline" />
                                            </a>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {documents.last_page > 1 && (
                <div className="flex items-center justify-between mt-5">
                    <p className="text-xs text-slate-400">
                        {(documents.current_page - 1) * 30 + 1}–{Math.min(documents.current_page * 30, documents.total)} of {documents.total}
                    </p>
                    <div className="flex gap-2">
                        {documents.current_page > 1 && (
                            <Link href={`/school/staff-documents?page=${documents.current_page - 1}`}
                                className="p-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200">
                                <ChevronLeft className="w-4 h-4" />
                            </Link>
                        )}
                        {documents.current_page < documents.last_page && (
                            <Link href={`/school/staff-documents?page=${documents.current_page + 1}`}
                                className="p-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200">
                                <ChevronRight className="w-4 h-4" />
                            </Link>
                        )}
                    </div>
                </div>
            )}
        </SchoolLayout>
    );
}
