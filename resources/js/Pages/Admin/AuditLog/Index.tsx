import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { ClipboardList } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';

interface LogEntry {
    id: number;
    action: string;
    school_name: string | null;
    user_name: string | null;
    target_type: string | null;
    target_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    created_at: string;
}

interface School { id: number; name: string; }

interface Props {
    logs: {
        data: LogEntry[];
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    schools: School[];
    actions: string[];
    filters: { school_id?: string; action?: string; date_from?: string; date_to?: string };
}

function Details({ log }: { log: LogEntry }) {
    const parts: Record<string, unknown> = {};
    if (log.target_type) parts.target = log.target_type + (log.target_id ? ` #${log.target_id}` : '');
    if (log.old_values && Object.keys(log.old_values).length) parts.old = log.old_values;
    if (log.new_values && Object.keys(log.new_values).length) parts.new = log.new_values;
    if (!Object.keys(parts).length) return <span className="text-xs text-slate-600">—</span>;
    return (
        <pre className="text-xs text-slate-500 max-w-xs whitespace-pre-wrap break-words">
            {JSON.stringify(parts, null, 2)}
        </pre>
    );
}

export default function Index({ logs, schools, actions, filters }: Props) {
    const [schoolId, setSchoolId] = useState(filters.school_id ?? '');
    const [action, setAction]     = useState(filters.action ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo]     = useState(filters.date_to ?? '');

    function apply(e: React.FormEvent) {
        e.preventDefault();
        router.get('/admin/audit-log', { school_id: schoolId, action, date_from: dateFrom, date_to: dateTo }, { preserveState: true, replace: true });
    }

    function fmtDate(d: string) {
        return new Date(d).toLocaleString('en-GB', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
        });
    }

    const selectClass = 'bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-purple-500';

    return (
        <AdminLayout>
            <Head title="Audit Log" />
            <PageHeader title="Audit Log" subtitle="System activity trail" />

            {/* Filters */}
            <form onSubmit={apply} className="flex flex-wrap items-center gap-2 mb-6">
                <select value={schoolId} onChange={e => setSchoolId(e.target.value)} className={selectClass}>
                    <option value="">All Schools</option>
                    {schools.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
                <select value={action} onChange={e => setAction(e.target.value)} className={selectClass}>
                    <option value="">All Actions</option>
                    {actions.map(a => <option key={a} value={a}>{a}</option>)}
                </select>
                <input
                    type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)}
                    className={selectClass}
                />
                <input
                    type="date" value={dateTo} onChange={e => setDateTo(e.target.value)}
                    className={selectClass}
                />
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
                                {['Timestamp', 'Action', 'School', 'User', 'Details'].map(h => (
                                    <th key={h} className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {logs.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-5 py-12 text-center text-slate-500">
                                        <ClipboardList className="w-10 h-10 mx-auto mb-3 text-slate-700" />
                                        No log entries found
                                    </td>
                                </tr>
                            ) : logs.data.map(log => (
                                <tr key={log.id} className="hover:bg-white/5 transition">
                                    <td className="px-5 py-3 text-slate-400 text-xs whitespace-nowrap">{fmtDate(log.created_at)}</td>
                                    <td className="px-5 py-3">
                                        <span className="font-mono text-xs px-2 py-0.5 rounded bg-white/5 text-purple-300">{log.action}</span>
                                    </td>
                                    <td className="px-5 py-3 text-slate-400 text-xs">{log.school_name ?? '—'}</td>
                                    <td className="px-5 py-3 text-slate-400 text-xs">{log.user_name ?? '—'}</td>
                                    <td className="px-5 py-3"><Details log={log} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {logs.last_page > 1 && (
                <div className="flex items-center gap-1 mt-4 flex-wrap">
                    {logs.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                            className={`px-3 py-1.5 text-xs rounded-lg transition ${
                                link.active ? 'bg-purple-600 text-white'
                                    : link.url ? 'bg-white/5 text-slate-400 hover:bg-white/10'
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
