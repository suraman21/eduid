import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { UserPlus, Upload, Search } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';

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
    const [search, setSearch]   = useState(filters.search ?? '');
    const [classId, setClassId] = useState(filters.class_id ?? '');
    const [status, setStatus]   = useState(filters.status ?? '');

    function apply(e: React.FormEvent) {
        e.preventDefault();
        router.get('/school/students', { search, class_id: classId, status }, { preserveState: true, replace: true });
    }

    const selectClass = 'border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:border-indigo-400 bg-white';

    return (
        <SchoolLayout>
            <Head title="Students" />

            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Students</h1>
                    {unassigned_count > 0 && (
                        <p className="text-xs text-amber-600 mt-0.5">{unassigned_count} student{unassigned_count > 1 ? 's' : ''} not assigned to a class</p>
                    )}
                </div>
                <div className="flex items-center gap-2">
                    <Link href="/school/students/import" className="flex items-center gap-1.5 px-3 py-2 text-sm text-slate-600 border border-slate-200 rounded-lg hover:bg-slate-50 transition">
                        <Upload className="w-4 h-4" />
                        Import
                    </Link>
                    <Link href="/school/students/create" className="flex items-center gap-1.5 px-3 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        <UserPlus className="w-4 h-4" />
                        Add Student
                    </Link>
                </div>
            </div>

            {/* Filters */}
            <form onSubmit={apply} className="flex flex-wrap gap-2 mb-5">
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input
                        type="text"
                        value={search}
                        onChange={e => setSearch(e.target.value)}
                        placeholder="Search name or ID…"
                        className="pl-9 pr-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:border-indigo-400 w-56"
                    />
                </div>
                <select value={classId} onChange={e => setClassId(e.target.value)} className={selectClass}>
                    <option value="">All Classes</option>
                    {classes.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                </select>
                <select value={status} onChange={e => setStatus(e.target.value)} className={selectClass}>
                    <option value="">All Statuses</option>
                    {STATUSES.map(s => <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>)}
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
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Student</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">ID</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Class</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {students.data.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-5 py-12 text-center text-slate-400 text-sm">
                                        No students found
                                    </td>
                                </tr>
                            ) : students.data.map(s => (
                                <tr key={s.id} className="hover:bg-slate-50 transition">
                                    <td className="px-5 py-3">
                                        <div className="flex items-center gap-3">
                                            {s.photo_url ? (
                                                <img src={s.photo_url} className="w-8 h-8 rounded-full object-cover" alt="" />
                                            ) : (
                                                <div className="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-bold text-indigo-600">
                                                    {s.initials}
                                                </div>
                                            )}
                                            <div>
                                                <p className="font-medium text-slate-800">{s.full_name}</p>
                                                <p className="text-xs text-slate-400 capitalize">{s.gender}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-5 py-3 font-mono text-xs text-slate-500">{s.uid || '—'}</td>
                                    <td className="px-5 py-3 text-slate-600 text-xs">{s.class_name ?? <span className="text-amber-500">Unassigned</span>}</td>
                                    <td className="px-5 py-3">
                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium capitalize ${STATUS_COLORS[s.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                            {s.status}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        <div className="flex items-center gap-3 justify-end">
                                            <Link href={`/school/students/${s.id}`} className="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">
                                                View
                                            </Link>
                                            <Link href={`/school/students/${s.id}/id-card`} className="text-xs text-slate-500 hover:text-slate-700 transition">
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
                            className={`px-3 py-1.5 text-xs rounded-lg transition ${
                                link.active ? 'bg-indigo-600 text-white'
                                    : link.url ? 'bg-white border border-slate-200 text-slate-500 hover:bg-slate-50'
                                    : 'bg-white border border-slate-100 text-slate-300 cursor-not-allowed'
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </SchoolLayout>
    );
}
