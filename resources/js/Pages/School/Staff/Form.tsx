import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
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

interface StaffMember {
    id: number;
    name: string;
    email: string;
    role: string;
    phone: string | null;
    employee_id: string | null;
    job_title: string | null;
    is_active: boolean;
    can_login: boolean;
    is_homeroom: boolean;
    is_coordinator: boolean;
    can_record_staff_attendance: boolean;
}

interface Props {
    member: StaffMember | null;
}

export default function Form({ member }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const isEdit = member !== null;

    const { data, setData, post, transform, processing, errors } = useForm({
        name:                        member?.name ?? '',
        email:                       member?.email ?? '',
        password:                    '',
        role:                        member?.role ?? 'teacher',
        phone:                       member?.phone ?? '',
        employee_id:                 member?.employee_id ?? '',
        job_title:                   member?.job_title ?? '',
        is_active:                   member?.is_active ?? true,
        can_login:                   member?.can_login ?? true,
        is_homeroom:                 member?.is_homeroom ?? false,
        is_coordinator:              member?.is_coordinator ?? false,
        can_record_staff_attendance: member?.can_record_staff_attendance ?? false,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (isEdit) {
            transform(d => ({ ...d, _method: 'PUT' }));
            post(`/school/staff/${member!.id}`);
        } else {
            post('/school/staff');
        }
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 transition bg-white';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };
    const labelCls = 'block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5';

    function SectionTitle({ children }: { children: React.ReactNode }) {
        return (
            <p className="text-[10px] font-bold uppercase tracking-widest mb-4" style={{ color: accent }}>
                {children}
            </p>
        );
    }

    function FieldError({ name }: { name: keyof typeof errors }) {
        return errors[name] ? <p className="text-red-500 text-xs mt-1">{errors[name]}</p> : null;
    }

    function CheckField({ field, label }: { field: keyof typeof data; label: string }) {
        return (
            <label className="flex items-center gap-3 cursor-pointer">
                <input
                    type="checkbox"
                    checked={data[field] as boolean}
                    onChange={e => setData(field, e.target.checked)}
                    className="w-4 h-4 rounded border-slate-300 accent-indigo-600"
                />
                <span className="text-sm text-slate-700">{label}</span>
            </label>
        );
    }

    return (
        <SchoolLayout>
            <Head title={isEdit ? `Edit ${member.name}` : 'New Staff Member'} />

            {/* Top bar */}
            <div className="flex items-center justify-between mb-6">
                <Link
                    href={isEdit ? `/school/staff/${member.id}` : '/school/staff'}
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" /> {isEdit ? 'Back to Profile' : 'Back to Staff'}
                </Link>
                <h1 className="text-lg font-extrabold text-slate-900">
                    {isEdit ? 'Edit Staff Member' : 'New Staff Member'}
                </h1>
            </div>

            <form onSubmit={submit} className="max-w-3xl space-y-5">

                {/* Basic info */}
                <Card className="p-6">
                    <SectionTitle>Basic Information</SectionTitle>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className={labelCls}>Name *</label>
                            <input
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                placeholder="Full name"
                                className={inputCls}
                                style={inputStyle}
                                required
                            />
                            <FieldError name="name" />
                        </div>
                        <div>
                            <label className={labelCls}>Email *</label>
                            <input
                                type="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                placeholder="email@school.edu"
                                className={inputCls}
                                style={inputStyle}
                                required
                            />
                            <FieldError name="email" />
                        </div>
                        <div>
                            <label className={labelCls}>Password {isEdit ? '' : '*'}</label>
                            <input
                                type="password"
                                value={data.password}
                                onChange={e => setData('password', e.target.value)}
                                placeholder={isEdit ? 'Leave blank to keep current' : 'Min. 8 characters'}
                                className={inputCls}
                                style={inputStyle}
                                required={!isEdit}
                            />
                            {isEdit && <p className="text-xs text-slate-400 mt-1">Leave blank to keep current password</p>}
                            <FieldError name="password" />
                        </div>
                        <div>
                            <label className={labelCls}>Role *</label>
                            <select
                                value={data.role}
                                onChange={e => setData('role', e.target.value)}
                                className={inputCls}
                                style={inputStyle}
                                required
                            >
                                <option value="school_admin">School Admin</option>
                                <option value="teacher">Teacher</option>
                                <option value="staff">Staff</option>
                            </select>
                            <FieldError name="role" />
                        </div>
                    </div>
                </Card>

                {/* Optional details */}
                <Card className="p-6">
                    <SectionTitle>Optional Details</SectionTitle>
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label className={labelCls}>Phone</label>
                            <input
                                value={data.phone}
                                onChange={e => setData('phone', e.target.value)}
                                placeholder="+251 9xx xxx xxx"
                                className={inputCls}
                                style={inputStyle}
                            />
                            <FieldError name="phone" />
                        </div>
                        <div>
                            <label className={labelCls}>Employee ID</label>
                            <input
                                value={data.employee_id}
                                onChange={e => setData('employee_id', e.target.value)}
                                placeholder="EMP-001"
                                className={`${inputCls} font-mono`}
                                style={inputStyle}
                            />
                            <FieldError name="employee_id" />
                        </div>
                        <div>
                            <label className={labelCls}>Job Title</label>
                            <input
                                value={data.job_title}
                                onChange={e => setData('job_title', e.target.value)}
                                placeholder="e.g. Head of Science"
                                className={inputCls}
                                style={inputStyle}
                            />
                            <FieldError name="job_title" />
                        </div>
                    </div>
                </Card>

                {/* Permissions */}
                <Card className="p-6">
                    <SectionTitle>Permissions</SectionTitle>
                    <div className="space-y-4">
                        <CheckField field="can_login" label="Can log in to the system" />
                        <CheckField field="is_homeroom" label="Homeroom teacher" />
                        <CheckField field="is_coordinator" label="Department coordinator" />
                        <CheckField field="can_record_staff_attendance" label="Can record staff attendance" />
                        {isEdit && (
                            <CheckField field="is_active" label="Account is active" />
                        )}
                    </div>
                </Card>

                {/* Submit */}
                <div className="flex items-center justify-end gap-3 pb-4">
                    <Link
                        href={isEdit ? `/school/staff/${member.id}` : '/school/staff'}
                        className="px-5 py-2.5 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-all"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-6 py-2.5 rounded-2xl text-sm font-bold text-white transition-all disabled:opacity-50"
                        style={{
                            background: accent,
                            boxShadow: `0 4px 14px ${alpha(accent, 0.35)}`,
                        }}
                    >
                        {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Staff Member'}
                    </button>
                </div>
            </form>
        </SchoolLayout>
    );
}
