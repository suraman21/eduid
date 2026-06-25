import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Search, UserPlus, Eye, Edit2 } from 'lucide-react';
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

interface StaffMember {
    id: number;
    name: string;
    email: string;
    role: string;
    job_title: string | null;
    employee_id: string | null;
    is_active: boolean;
    initials: string;
}

interface Props {
    staff: {
        data: StaffMember[];
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { search?: string; role?: string };
    is_admin: boolean;
}

const ROLE_COLORS: Record<string, string> = {
    school_admin: 'bg-purple-100 text-purple-700',
    teacher:      'bg-blue-100 text-blue-700',
    staff:        'bg-slate-100 text-slate-600',
};

const ROLE_LABELS: Record<string, string> = {
    school_admin: 'School Admin',
    teacher:      'Teacher',
    staff:        'Staff',
};

export default function Index({ staff, filters, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const [search, setSearch] = useState(filters.search ?? '');
    const [role, setRole]     = useState(filters.role ?? '');

    function apply(e: React.FormEvent) {
        e.preventDefault();
        router.get('/school/staff', { search, role }, { preserveState: true, replace: true });
    }

    const selectClass = 'border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:border-indigo-400 bg-white';

    return (
        <SchoolLayout>
            <Head title="Staff" />

            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Staff</h1>
                    <p className="text-xs text-slate-400 mt-0.5">{staff.data.length > 0 ? `${staff.data.length} shown` : 'No staff found'}</p>
                </div>
                {is_admin && (
                    <Link
                        href="/school/staff/create"
                        className="flex items-center gap-1.5 px-3 py-2 text-sm text-white rounded-lg transition"
                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}
                    >
                        <UserPlus className="w-4 h-4" />
                        Add Staff
                    </Link>
                )}
            </div>

            {/* Filters */}
            <form onSubmit={apply} className="flex flex-wrap gap-2 mb-5">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search name, email or ID…"
                        className="pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-400 w-64"
                    />
                </div>
                <select value={role} onChange={e => setRole(e.target.value)} className={selectClass}>
                    <option value="">All Roles</option>
                    <option value="school_admin">School Admin</option>
                    <option value="teacher">Teacher</option>
                    <option value="staff">Staff</option>
                </select>
                <button type="submit" className="px-4 py-2 text-sm bg-slate-800 text-white rounded-lg hover:bg-slate-700 transition">
                    Filter
                </button>
            </form>

            {/* Table */}
            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left">
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Name</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Email</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Role</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Employee ID</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {staff.data.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-5 py-12 text-center text-slate-400 text-sm">
                                        No staff found
                                    </td>
                                </tr>
                            ) : staff.data.map(m => (
                                <tr key={m.id} className="hover:bg-slate-50 transition">
                                    <td className="px-5 py-3">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                                style={{ background: alpha(accent, 0.12), color: accent }}
                                            >
                                                {m.initials}
                                            </div>
                                            <div>
                                                <p className="font-medium text-slate-800">{m.name}</p>
                                                {m.job_title && <p className="text-xs text-slate-400">{m.job_title}</p>}
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-5 py-3 text-slate-500 text-xs">{m.email}</td>
                                    <td className="px-5 py-3">
                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${ROLE_COLORS[m.role] ?? 'bg-slate-100 text-slate-600'}`}>
                                            {ROLE_LABELS[m.role] ?? m.role}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 font-mono text-xs text-slate-500">{m.employee_id || '—'}</td>
                                    <td className="px-5 py-3">
                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${m.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                            {m.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <div className="flex items-center gap-3 justify-end">
                                            <Link href={`/school/staff/${m.id}`} className="text-slate-400 transition hover:opacity-100" title="View" style={{ ['--hover-accent' as string]: accent }} onMouseEnter={e => (e.currentTarget.style.color = accent)} onMouseLeave={e => (e.currentTarget.style.color = '')}>
                                                <Eye className="w-4 h-4" />
                                            </Link>
                                            {is_admin && (
                                                <Link href={`/school/staff/${m.id}/edit`} className="text-slate-400 hover:text-slate-700 transition" title="Edit">
                                                    <Edit2 className="w-4 h-4" />
                                                </Link>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {staff.last_page > 1 && (
                <div className="flex items-center gap-1 mt-4 flex-wrap">
                    {staff.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                            className={`px-3 py-1.5 text-xs font-semibold rounded-lg transition ${
                                link.active ? 'text-white'
                                    : link.url ? 'bg-white border border-slate-200 text-slate-500 hover:bg-slate-50'
                                    : 'bg-white border border-slate-100 text-slate-300 cursor-not-allowed'
                            }`}
                            style={link.active ? { background: accent } : undefined}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </SchoolLayout>
    );
}
