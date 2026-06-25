import { Head, Link } from '@inertiajs/react';
import { Plus, GraduationCap } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/* ── colour helpers ──────────────────────────────────────────── */
function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface SchoolClass {
    id: number;
    grade: string;
    section: string;
    display_name: string;
    academic_year: string;
    students_count: number;
    is_active: boolean;
}

interface Props {
    classes: SchoolClass[];
    is_admin: boolean;
}

export default function Index({ classes, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.school?.accent_color);

    return (
        <SchoolLayout>
            <Head title="Classes" />

            {/* Header */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Classes</h1>
                    <p className="text-xs text-slate-400 mt-0.5">Manage grade levels and sections</p>
                </div>
                {is_admin && (
                    <Link
                        href="/school/classes/create"
                        className="flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-white rounded-2xl transition"
                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}
                    >
                        <Plus className="w-4 h-4" />
                        Add Class
                    </Link>
                )}
            </div>

            {/* Table */}
            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-slate-100 text-left">
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Class</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Academic Year</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Students</th>
                                <th className="px-5 py-3 text-xs font-semibold text-slate-500 uppercase">Status</th>
                                <th className="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {classes.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="px-5 py-12 text-center text-slate-400 text-sm">
                                        <GraduationCap className="w-10 h-10 mx-auto mb-3 text-slate-200" strokeWidth={1} />
                                        No classes yet.{' '}
                                        {is_admin && (
                                            <Link href="/school/classes/create" className="font-medium" style={{ color: accent }}>
                                                Add one →
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ) : classes.map(c => (
                                <tr key={c.id} className="hover:bg-slate-50 transition">
                                    <td className="px-5 py-3">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className="w-9 h-9 rounded-lg flex items-center justify-center text-sm font-bold"
                                                style={{ background: alpha(accent, 0.1), color: accent }}
                                            >
                                                {c.grade.slice(0, 2)}
                                            </div>
                                            <div>
                                                <p className="font-semibold text-slate-900">{c.display_name}</p>
                                                <p className="text-xs text-slate-400">Grade {c.grade} · Section {c.section}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-5 py-3 text-slate-600">{c.academic_year}</td>
                                    <td className="px-5 py-3">
                                        <span className="font-medium text-slate-900">{c.students_count}</span>
                                        <span className="text-slate-400 text-xs"> students</span>
                                    </td>
                                    <td className="px-5 py-3">
                                        <span className={`text-xs px-2 py-0.5 rounded-full font-medium ${c.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'}`}>
                                            {c.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3 text-right">
                                        {is_admin && (
                                            <Link href={`/school/classes/${c.id}/edit`} className="text-xs text-slate-400 hover:text-indigo-500 transition">
                                                Edit
                                            </Link>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </SchoolLayout>
    );
}
