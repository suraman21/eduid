import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { UserPlus, Upload, Search } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

interface Student {
    id: number;
    full_name: string;
    uid: string;
    status: string;
    photo_url: string | null;
    initials: string;
    class_name: string | null;
    gender: string;
}

interface SchoolClass {
    id: number;
    name: string;
}

interface Props {
    students: {
        data: Student[];
        last_page: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    classes: SchoolClass[];
    unassigned_count: number;
    filters: { search?: string; class_id?: string; status?: string };
}

/* ── colour helpers ─────────────────────────────────────────── */
function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

const STATUS_COLORS: Record<string, string> = {
    active:      'bg-green-100 text-green-700',
    promoted:    'bg-blue-100 text-blue-700',
    repeating:   'bg-amber-100 text-amber-700',
    graduated:   'bg-purple-100 text-purple-700',
    transferred: 'bg-slate-100 text-slate-700',
    withdrawn:   'bg-red-100 text-red-700',
};

const STATUSES = ['active', 'promoted', 'repeating', 'graduated', 'transferred', 'withdrawn'];

export default function Index({ students, classes, unassigned_count, filters }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const [search, setSearch]   = useState(filters.search ?? '');
    const [classId, setClassId] = useState(filters.class_id ?? '');
    const [status, setStatus]   = useState(filters.status ?? '');

    function apply(e: React.FormEvent) {
        e.preventDefault();
        router.get('/school/students', { search, class_id: classId, status }, { preserveState: true, replace: true });
    }

    const ringStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };
    const selectClass = 'border border-slate-200 rounded-xl px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 bg-white transition';

    return (
        <SchoolLayout>
            <Head title="Students" />

            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Students</h1>
                    {unassigned_count > 0 && (
                        <p className="text-xs text-amber-600 mt-0.5">{unassigned_count} student{unassigned_count > 1 ? 's' : ''} not assigned to a class</p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <Link href="/school/students/import" className="flex items-center gap-1.5 px-3.5 py-2.5 text-sm font-semibold text-slate-600 border border-slate-200 rounded-2xl bg-white hover:bg-slate-50 transition">
                        <Upload className="w-4 h-4" />
                        Import
                    </Link>
                    <Link
                        href="/school/students/create"
                        className="flex items-center gap-1.5 px-4 py-2.5 text-sm font-bold text-white rounded-2xl transition hover:-translate-y-0.5"
                        style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                    >
                        <UserPlus className="w-4 h-4" />
                        Add Student
                    </Link>
                </div>
            </div>

            {/* Filters */}
            <form onSubmit={apply} className="flex flex-wrap gap-2 mb-5">
                <div className="relative">
                    <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search name or ID…"
                        className="pl-10 pr-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 w-56 bg-white transition"
                        style={ringStyle}
                    />
                </div>
                <select value={classId} onChange={e => setClassId(e.target.value)} className={selectClass} style={ringStyle}>
                    <option value="">All Classes</option>
                    {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <select value={status} onChange={e => setStatus(e.target.value)} className={selectClass} style={ringStyle}>
                    <option value="">All Statuses</option>
                    {STATUSES.map(s => <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
                </select>
                <button
                    type="submit"
                    className="px-4 py-2 text-sm font-semibold text-white rounded-xl transition"
                    style={{ background: accent }}
                >
                    Filter
                </button>
            </form>

            {/* Table */}
            <div className="bg-white rounded-3xl border border-slate-200/60 overflow-hidden" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left">
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Student</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Class</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                                <th className="px-5 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {students.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-5 py-14 text-center text-slate-400 text-sm">
                                        No students found
                                    </td>
                                </tr>
                            ) : students.data.map(s => (
                                <tr key={s.id} className="hover:bg-slate-50/70 transition">
                                    <td className="px-5 py-3">
                                        <div className="flex items-center gap-3">
                                            {s.photo_url ? (
                                                <img src={s.photo_url} className="w-9 h-9 rounded-2xl object-cover" alt="" style={{ border: `2px solid ${alpha(accent, 0.18)}` }} />
                                            ) : (
                                                <div className="w-9 h-9 rounded-2xl flex items-center justify-center text-xs font-extrabold" style={{ background: alpha(accent, 0.10), color: accent }}>
                                                    {s.initials}
                                                </div>
                                            )}
                                            <div>
                                                <p className="font-bold text-slate-800">{s.full_name}</p>
                                                <p className="text-xs text-slate-400 capitalize">{s.gender}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-5 py-3 font-mono text-xs text-slate-500">{s.uid || '—'}</td>
                                    <td className="px-5 py-3 text-slate-600 text-xs">{s.class_name ?? <span className="text-amber-500">Unassigned</span>}</td>
                                    <td className="px-5 py-3">
                                        <span className={`text-xs px-2.5 py-1 rounded-full font-bold capitalize ${STATUS_COLORS[s.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                            {s.status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <div className="flex items-center gap-3 justify-end">
                                            <Link href={`/school/students/${s.id}`} className="text-xs font-bold transition" style={{ color: accent }}>
                                                View
                                            </Link>
                                            <Link href={`/school/students/${s.id}/id-card`} className="text-xs font-semibold text-slate-500 hover:text-slate-700 transition">
                                                ID Card
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pagination */}
            {students.last_page > 1 && (
                <div className="flex items-center gap-1 mt-4 flex-wrap">
                    {students.links.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                            className={`px-3 py-1.5 text-xs font-semibold rounded-xl transition ${
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
