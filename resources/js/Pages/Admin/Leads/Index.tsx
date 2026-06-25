import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Search, Mail } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { type Tone } from '@/Components/Badge';

interface Lead {
    id: number;
    name: string;
    school_name: string;
    phone: string;
    email: string | null;
    status: string;
    created_at: string;
}

interface Props {
    leads: {
        data: Lead[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    new_count: number;
    filters: { search?: string; status?: string };
}

const statusTone: Record<string, Tone> = {
    new:            'amber',
    contacted:      'blue',
    demo_scheduled: 'purple',
    converted:      'green',
    lost:           'slate',
};

const statusLabel: Record<string, string> = {
    new:            'New',
    contacted:      'Contacted',
    demo_scheduled: 'Demo Scheduled',
    converted:      'Converted',
    lost:           'Lost',
};

export default function Index({ leads, new_count, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    function applyFilters(e: React.FormEvent) {
        e.preventDefault();
        router.get('/admin/leads', { search, status }, { preserveState: true, replace: true });
    }

    return (
        <AdminLayout>
            <Head title="Leads" />

            <PageHeader
                title="Leads"
                subtitle="Marketing inquiries from the contact form"
            />

            {/* Stats */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div className="bg-white/[0.025] border border-white/10 rounded-xl p-5">
                    <p className="text-xs font-semibold text-slate-500 uppercase mb-1">New Leads</p>
                    <div className="flex items-center gap-3">
                        <span className="text-3xl font-bold text-slate-100">{new_count}</span>
                        <Badge tone="amber">New</Badge>
                    </div>
                </div>
                <div className="bg-white/[0.025] border border-white/10 rounded-xl p-5">
                    <p className="text-xs font-semibold text-slate-500 uppercase mb-1">Total Leads</p>
                    <span className="text-3xl font-bold text-slate-100">{leads.total}</span>
                </div>
            </div>

            {/* Filters */}
            <form onSubmit={applyFilters} className="flex flex-wrap items-center gap-2 mb-6">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" />
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search name / school / phone…"
                        className="bg-white/5 border border-white/10 rounded-lg pl-9 pr-4 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-purple-500 w-72"
                    />
                </div>
                <select
                    value={status}
                    onChange={e => setStatus(e.target.value)}
                    className="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-300 focus:outline-none focus:border-purple-500"
                >
                    <option value="">All Statuses</option>
                    <option value="new">New</option>
                    <option value="contacted">Contacted</option>
                    <option value="demo_scheduled">Demo Scheduled</option>
                    <option value="converted">Converted</option>
                    <option value="lost">Lost</option>
                </select>
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
                                {['Name', 'School', 'Phone', 'Status', 'Submitted', ''].map(h => (
                                    <th key={h} className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">{h}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-white/5">
                            {leads.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-5 py-12 text-center text-slate-500">
                                        <Mail className="w-10 h-10 mx-auto mb-3 text-slate-700" />
                                        No leads found
                                    </td>
                                </tr>
                            ) : leads.data.map(lead => (
                                <tr
                                    key={lead.id}
                                    className="hover:bg-white/5 transition cursor-pointer"
                                    onClick={() => router.visit(`/admin/leads/${lead.id}`)}
                                >
                                    <td className="px-5 py-3 font-medium text-slate-200">{lead.name}</td>
                                    <td className="px-5 py-3 text-slate-400">{lead.school_name}</td>
                                    <td className="px-5 py-3 text-slate-400">{lead.phone}</td>
                                    <td className="px-5 py-3">
                                        <Badge tone={statusTone[lead.status] ?? 'slate'}>
                                            {statusLabel[lead.status] ?? lead.status}
                                        </Badge>
                                    </td>
                                    <td className="px-5 py-3 text-slate-400 text-xs">
                                        {new Date(lead.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })}
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className="text-purple-400 hover:text-purple-300 text-xs font-medium transition">View</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {leads.last_page > 1 && (
                <div className="flex items-center gap-1 mt-4 flex-wrap">
                    {leads.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                            className={`px-3 py-1.5 text-xs rounded-lg transition ${
                                link.active
                                    ? 'bg-purple-600 text-white'
                                    : link.url
                                        ? 'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-slate-200'
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
