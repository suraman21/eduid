import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Edit2, CheckCircle, XCircle } from 'lucide-react';
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

/* ── Card primitive ─────────────────────────────────────────── */
function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <div
            className={`bg-white rounded-3xl border border-slate-200/60 ${className}`}
            style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}
        >
            {children}
        </div>
    );
}

const ROLE_LABELS: Record<string, string> = {
    school_admin: 'School Admin',
    teacher:      'Teacher',
    staff:        'Staff',
};

const ROLE_COLORS: Record<string, { bg: string; text: string }> = {
    school_admin: { bg: '#f3e8ff', text: '#7e22ce' },
    teacher:      { bg: '#dbeafe', text: '#1d4ed8' },
    staff:        { bg: '#f1f5f9', text: '#475569' },
};

interface StaffMember {
    id: number;
    name: string;
    email: string;
    role: string;
    job_title: string | null;
    phone: string | null;
    employee_id: string | null;
    is_active: boolean;
    can_login: boolean;
    is_homeroom: boolean;
    is_coordinator: boolean;
    can_record_staff_attendance: boolean;
    last_login_at: string | null;
    initials: string;
}

interface Props {
    member: StaffMember;
    is_admin: boolean;
}

export default function Show({ member, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const roleColors = ROLE_COLORS[member.role] ?? { bg: '#f1f5f9', text: '#475569' };

    function BoolRow({ label, value }: { label: string; value: boolean }) {
        return (
            <div className="flex items-center justify-between py-2.5 border-b border-slate-50 last:border-0">
                <span className="text-sm text-slate-600">{label}</span>
                {value
                    ? <CheckCircle className="w-5 h-5 text-green-500" />
                    : <XCircle className="w-5 h-5 text-slate-300" />
                }
            </div>
        );
    }

    return (
        <SchoolLayout>
            <Head title={member.name} />

            {/* Top bar */}
            <div className="flex items-center justify-between mb-6">
                <Link
                    href="/school/staff"
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" /> Back to Staff
                </Link>
                {is_admin && (
                    <Link
                        href={`/school/staff/${member.id}/edit`}
                        className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-white transition-all"
                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}
                    >
                        <Edit2 className="w-4 h-4" /> Edit
                    </Link>
                )}
            </div>

            <div className="max-w-3xl space-y-5">

                {/* Profile card */}
                <Card className="p-6">
                    <div className="flex items-start gap-5">
                        <div
                            className="w-20 h-20 rounded-2xl flex items-center justify-center text-2xl font-extrabold flex-shrink-0"
                            style={{ background: alpha(accent, 0.12), color: accent }}
                        >
                            {member.initials}
                        </div>
                        <div className="flex-1 min-w-0">
                            <h1 className="text-2xl font-extrabold text-slate-900 leading-tight">{member.name}</h1>
                            <p className="text-sm text-slate-500 mt-0.5">{member.email}</p>
                            <div className="flex flex-wrap items-center gap-2 mt-3">
                                <span
                                    className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold"
                                    style={{ background: roleColors.bg, color: roleColors.text }}
                                >
                                    {ROLE_LABELS[member.role] ?? member.role}
                                </span>
                                <span
                                    className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ${member.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}
                                >
                                    {member.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Details card */}
                <Card className="p-6">
                    <p className="text-[10px] font-bold uppercase tracking-widest mb-4" style={{ color: accent }}>Details</p>
                    <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                        {[
                            { label: 'Phone', value: member.phone || '—' },
                            { label: 'Employee ID', value: member.employee_id || '—', mono: true },
                            { label: 'Job Title', value: member.job_title || '—' },
                            { label: 'Last Login', value: member.last_login_at || 'Never' },
                        ].map(({ label, value, mono }) => (
                            <div key={label}>
                                <dt className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-1">{label}</dt>
                                <dd className={`text-sm font-semibold text-slate-800 ${mono ? 'font-mono' : ''}`}>{value}</dd>
                            </div>
                        ))}
                    </dl>
                </Card>

                {/* Permissions card */}
                <Card className="p-6">
                    <p className="text-[10px] font-bold uppercase tracking-widest mb-2" style={{ color: accent }}>Permissions</p>
                    <BoolRow label="Can log in" value={member.can_login} />
                    <BoolRow label="Homeroom teacher" value={member.is_homeroom} />
                    <BoolRow label="Department coordinator" value={member.is_coordinator} />
                    <BoolRow label="Can record staff attendance" value={member.can_record_staff_attendance} />
                </Card>
            </div>
        </SchoolLayout>
    );
}
