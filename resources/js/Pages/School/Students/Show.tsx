import React, { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft, Edit2, CreditCard, ShieldCheck, ShieldOff,
    User, Phone, Mail, Calendar, Droplets, Activity, BookOpen,
    Plus, Trash2, Star, Smartphone, Send,
} from 'lucide-react';
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
function Card({ children, className = '', style }: { children: React.ReactNode; className?: string; style?: React.CSSProperties }) {
    return (
        <div
            className={`bg-white rounded-3xl border border-slate-200/60 ${className}`}
            style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)', ...style }}
        >
            {children}
        </div>
    );
}

/* ── Status badge ────────────────────────────────────────────── */
const STATUS_COLORS: Record<string, { bg: string; text: string }> = {
    active:      { bg: '#dcfce7', text: '#15803d' },
    promoted:    { bg: '#d1fae5', text: '#047857' },
    repeating:   { bg: '#ffedd5', text: '#c2410c' },
    graduated:   { bg: '#dbeafe', text: '#1d4ed8' },
    withdrawn:   { bg: '#fee2e2', text: '#b91c1c' },
    transferred: { bg: '#fef3c7', text: '#b45309' },
};

function StatusBadge({ status }: { status: string }) {
    const colors = STATUS_COLORS[status] ?? { bg: '#f1f5f9', text: '#475569' };
    return (
        <span
            className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold capitalize"
            style={{ background: colors.bg, color: colors.text }}
        >
            {status}
        </span>
    );
}

/* ── Types ───────────────────────────────────────────────────── */
interface Guardian {
    app_access: 'active' | 'revoked' | null;
    account_email: string | null;
    telegram_linked: boolean;
    telegram_link: string | null;
    id: number;
    full_name: string;
    phone: string | null;
    phone_alt: string | null;
    email: string | null;
    relationship: string;
    is_primary: boolean;
}

interface StudentData {
    id: number;
    full_name: string;
    full_name_am: string | null;
    uid: string;
    gender: string;
    date_of_birth: string | null;
    blood_type: string | null;
    status: string;
    photo_url: string | null;
    initials: string;
    class_name: string | null;
    enrollment_date: string | null;
    join_date: string | null;
    end_date: string | null;
    medical_notes: string | null;
    qr_token: string | null;
    qr_token_year: number | null;
    qr_valid: boolean;
    created_at: string;
    guardians: Guardian[];
}

interface Props {
    student: StudentData;
    is_admin: boolean;
    parent_app: boolean;
    alerts_on: boolean;
}

const RELATIONSHIPS = ['father', 'mother', 'guardian', 'sibling', 'other'];

/* ── Add Guardian form ───────────────────────────────────────── */
function AddGuardianForm({ studentId, accent }: { studentId: number; accent: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        full_name: '',
        phone: '',
        phone_alt: '',
        email: '',
        relationship: 'guardian',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/school/students/${studentId}/guardians`, {
            onSuccess: () => { reset(); setOpen(false); },
        });
    }

    if (!open) {
        return (
            <button
                onClick={() => setOpen(true)}
                className="flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-2xl transition-all"
                style={{ color: accent, background: alpha(accent, 0.08) }}
            >
                <Plus className="w-4 h-4" /> Add Guardian
            </button>
        );
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <form onSubmit={submit} className="mt-4 p-4 rounded-2xl border border-slate-100 bg-slate-50/60 space-y-3">
            <p className="text-xs font-bold uppercase tracking-widest text-slate-400 mb-2">New Guardian</p>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <input
                        placeholder="Full name *"
                        value={data.full_name}
                        onChange={e => setData('full_name', e.target.value)}
                        className={inputCls}
                        style={inputStyle}
                        required
                    />
                    {errors.full_name && <p className="text-red-500 text-xs mt-1">{errors.full_name}</p>}
                </div>
                <div>
                    <select
                        value={data.relationship}
                        onChange={e => setData('relationship', e.target.value)}
                        className={inputCls}
                        style={inputStyle}
                    >
                        {RELATIONSHIPS.map(r => <option key={r} value={r}>{r.charAt(0).toUpperCase() + r.slice(1)}</option>)}
                    </select>
                </div>
                <div>
                    <input
                        placeholder="Phone"
                        value={data.phone}
                        onChange={e => setData('phone', e.target.value)}
                        className={inputCls}
                        style={inputStyle}
                    />
                </div>
                <div>
                    <input
                        placeholder="Alt phone"
                        value={data.phone_alt}
                        onChange={e => setData('phone_alt', e.target.value)}
                        className={inputCls}
                        style={inputStyle}
                    />
                </div>
                <div className="sm:col-span-2">
                    <input
                        type="email"
                        placeholder="Email"
                        value={data.email}
                        onChange={e => setData('email', e.target.value)}
                        className={inputCls}
                        style={inputStyle}
                    />
                </div>
            </div>
            <div className="flex items-center gap-2 pt-1">
                <button
                    type="submit"
                    disabled={processing}
                    className="px-4 py-2 rounded-2xl text-sm font-bold text-white transition-all disabled:opacity-50"
                    style={{ background: accent }}
                >
                    {processing ? 'Saving…' : 'Save Guardian'}
                </button>
                <button
                    type="button"
                    onClick={() => { reset(); setOpen(false); }}
                    className="px-4 py-2 rounded-2xl text-sm font-semibold text-slate-500 hover:bg-slate-100 transition-all"
                >
                    Cancel
                </button>
            </div>
        </form>
    );
}

/* ── Main page ───────────────────────────────────────────────── */
export default function Show({ student, is_admin, parent_app, alerts_on }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    function removeGuardian(guardianId: number) {
        if (!confirm('Remove this guardian from the student?')) return;
        router.delete(`/school/students/${student.id}/guardians/${guardianId}`);
    }

    function grantAppAccess(g: Guardian) {
        const email = prompt(
            `Email for ${g.full_name}'s parent app login:`,
            g.account_email ?? g.email ?? ''
        );
        if (!email) return;
        // The response flashes a ONE-TIME temporary password — share it now.
        router.post(`/school/guardians/${g.id}/account`, { email }, { preserveScroll: true });
    }

    function revokeAppAccess(g: Guardian) {
        if (!confirm(`Revoke ${g.full_name}'s parent app access?`)) return;
        router.delete(`/school/guardians/${g.id}/account`, { preserveScroll: true });
    }

    function copyTelegramLink(g: Guardian) {
        if (!g.telegram_link) {
            alert('The Telegram bot is not configured yet — contact Arkeon support.');
            return;
        }
        navigator.clipboard?.writeText(g.telegram_link);
        alert(`Personal Telegram link copied for ${g.full_name} — share it via any channel.\n\nWhen they open it and tap START, absence alerts switch on automatically.`);
    }

    function InfoRow({ icon: Icon, label, value }: { icon: React.ElementType; label: string; value: React.ReactNode }) {
        return (
            <div className="flex items-start gap-3 py-3 border-b border-slate-50 last:border-0">
                <div className="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5"
                    style={{ background: alpha(accent, 0.08), color: accent }}>
                    <Icon className="w-3.5 h-3.5" />
                </div>
                <div className="min-w-0">
                    <p className="text-[11px] font-medium text-slate-400 uppercase tracking-widest">{label}</p>
                    <p className="text-sm font-semibold text-slate-800 mt-0.5">{value || <span className="text-slate-300 font-normal">—</span>}</p>
                </div>
            </div>
        );
    }

    return (
        <SchoolLayout>
            <Head title={student.full_name} />

            {/* Top action bar */}
            <div className="flex items-center justify-between mb-6">
                <Link
                    href="/school/students"
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" /> Back to Students
                </Link>
                <div className="flex items-center gap-2">
                    <a
                        href={`/school/students/${student.id}/id-card`}
                        className="flex items-center gap-2 text-xs font-bold px-3.5 py-2 rounded-2xl transition-all"
                        style={{ color: accent, background: alpha(accent, 0.08) }}
                    >
                        <CreditCard className="w-4 h-4" /> ID Card
                    </a>
                    {is_admin && (
                        <Link
                            href={`/school/students/${student.id}/edit`}
                            className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-white transition-all"
                            style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}
                        >
                            <Edit2 className="w-4 h-4" /> Edit
                        </Link>
                    )}
                </div>
            </div>

            {/* 3-column grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

                {/* ── Left 2 cols ── */}
                <div className="lg:col-span-2 space-y-5">

                    {/* Student info card */}
                    <Card className="p-6">
                        {/* Header with photo + name */}
                        <div className="flex items-start gap-5 mb-6">
                            <div className="flex-shrink-0">
                                {student.photo_url ? (
                                    <img
                                        src={student.photo_url}
                                        alt={student.full_name}
                                        className="w-20 h-20 rounded-2xl object-cover"
                                        style={{ border: `3px solid ${alpha(accent, 0.2)}` }}
                                    />
                                ) : (
                                    <div
                                        className="w-20 h-20 rounded-2xl flex items-center justify-center text-2xl font-extrabold"
                                        style={{ background: alpha(accent, 0.12), color: accent }}
                                    >
                                        {student.initials}
                                    </div>
                                )}
                            </div>
                            <div className="flex-1 min-w-0">
                                <h1 className="text-2xl font-extrabold text-slate-900 leading-tight">{student.full_name}</h1>
                                {student.full_name_am && (
                                    <p className="text-base text-slate-500 mt-0.5" dir="auto">{student.full_name_am}</p>
                                )}
                                <div className="flex flex-wrap items-center gap-2 mt-2">
                                    <StatusBadge status={student.status} />
                                    <span className="text-xs font-mono text-slate-400 bg-slate-100 px-2.5 py-1 rounded-full">{student.uid}</span>
                                    {student.class_name && (
                                        <span className="text-xs font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">{student.class_name}</span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Info rows */}
                        <div className="divide-y divide-slate-50">
                            <InfoRow icon={User} label="Gender" value={student.gender ? (student.gender.charAt(0).toUpperCase() + student.gender.slice(1)) : null} />
                            <InfoRow icon={Calendar} label="Date of Birth" value={student.date_of_birth} />
                            <InfoRow icon={Droplets} label="Blood Type" value={student.blood_type} />
                            <InfoRow icon={BookOpen} label="Class" value={student.class_name} />
                            {student.medical_notes && (
                                <div className="py-3">
                                    <p className="text-[11px] font-medium text-slate-400 uppercase tracking-widest mb-1">Medical Notes</p>
                                    <p className="text-sm text-slate-700 leading-relaxed">{student.medical_notes}</p>
                                </div>
                            )}
                        </div>

                        <p className="text-[11px] text-slate-300 mt-4">Registered {student.created_at}</p>
                    </Card>

                    {/* Guardians card */}
                    <Card className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div>
                                <h2 className="text-base font-bold text-slate-800">Guardians</h2>
                                <p className="text-xs text-slate-400 mt-0.5">Emergency contacts &amp; parents</p>
                            </div>
                            {is_admin && <AddGuardianForm studentId={student.id} accent={accent} />}
                        </div>

                        {student.guardians.length === 0 ? (
                            <p className="text-sm text-slate-400 py-6 text-center">No guardians linked yet</p>
                        ) : (
                            <div className="space-y-3">
                                {student.guardians.map(g => (
                                    <div key={g.id} className="flex items-start gap-4 p-4 rounded-2xl border border-slate-100 bg-slate-50/40">
                                        <div
                                            className="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0 text-xs font-extrabold"
                                            style={{ background: alpha(accent, 0.10), color: accent }}
                                        >
                                            {g.full_name.charAt(0).toUpperCase()}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <p className="text-sm font-bold text-slate-800">{g.full_name}</p>
                                                {g.is_primary && (
                                                    <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                                        style={{ background: alpha(accent, 0.10), color: accent }}>
                                                        <Star className="w-2.5 h-2.5" /> Primary
                                                    </span>
                                                )}
                                                <span className="text-[10px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full capitalize">{g.relationship}</span>
                                            </div>
                                            <div className="flex flex-wrap gap-x-4 gap-y-1 mt-1.5">
                                                {g.phone && (
                                                    <a href={`tel:${g.phone}`} className="flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700">
                                                        <Phone className="w-3 h-3" /> {g.phone}
                                                    </a>
                                                )}
                                                {g.phone_alt && (
                                                    <a href={`tel:${g.phone_alt}`} className="flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700">
                                                        <Phone className="w-3 h-3" /> {g.phone_alt}
                                                    </a>
                                                )}
                                                {g.email && (
                                                    <a href={`mailto:${g.email}`} className="flex items-center gap-1 text-xs text-slate-500 hover:text-slate-700">
                                                        <Mail className="w-3 h-3" /> {g.email}
                                                    </a>
                                                )}
                                            </div>
                                        </div>
                                        {is_admin && alerts_on && (
                                            <div className="flex-shrink-0">
                                                {g.telegram_linked ? (
                                                    <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-full bg-sky-100 text-sky-700">
                                                        <Send className="w-3 h-3" /> Alerts on
                                                    </span>
                                                ) : (
                                                    <button onClick={() => copyTelegramLink(g)}
                                                        className="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1.5 rounded-xl border border-sky-200 text-sky-600 hover:border-sky-300 hover:bg-sky-50 transition"
                                                        title="Copy this guardian's personal Telegram link">
                                                        <Send className="w-3 h-3" /> Telegram link
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                        {is_admin && parent_app && (
                                            <div className="flex flex-col items-end gap-1 flex-shrink-0">
                                                {g.app_access === 'active' ? (
                                                    <>
                                                        <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-1 rounded-full bg-green-100 text-green-700">
                                                            <Smartphone className="w-3 h-3" /> App access
                                                        </span>
                                                        <div className="flex gap-1">
                                                            <button onClick={() => grantAppAccess(g)}
                                                                className="text-[10px] font-bold text-slate-400 hover:text-slate-600 px-1.5 py-0.5">
                                                                Reset PW
                                                            </button>
                                                            <button onClick={() => revokeAppAccess(g)}
                                                                className="text-[10px] font-bold text-red-400 hover:text-red-600 px-1.5 py-0.5">
                                                                Revoke
                                                            </button>
                                                        </div>
                                                    </>
                                                ) : (
                                                    <button onClick={() => grantAppAccess(g)}
                                                        className="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1.5 rounded-xl border border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-700 transition">
                                                        <Smartphone className="w-3 h-3" />
                                                        {g.app_access === 'revoked' ? 'Re-enable app' : 'Enable app access'}
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                        {is_admin && (
                                            <button
                                                onClick={() => removeGuardian(g.id)}
                                                className="p-2 rounded-xl text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all flex-shrink-0"
                                                title="Remove guardian"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>

                {/* ── Right col ── */}
                <div className="space-y-5">

                    {/* Dates sidebar */}
                    <Card className="p-5">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-4">Academic Dates</p>
                        <div className="space-y-3">
                            {[
                                { label: 'Enrollment', value: student.enrollment_date },
                                { label: 'Join Date', value: student.join_date },
                                { label: 'End Date', value: student.end_date },
                            ].map(({ label, value }) => (
                                <div key={label} className="flex items-center justify-between">
                                    <span className="text-xs text-slate-400">{label}</span>
                                    <span className="text-xs font-bold text-slate-700">{value || <span className="text-slate-300">—</span>}</span>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* QR status */}
                    <Card className="p-5">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-4">QR Token</p>
                        <div className="flex items-center gap-3">
                            <div className={`w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0 ${student.qr_valid ? '' : ''}`}
                                style={{ background: student.qr_valid ? '#dcfce7' : '#fee2e2', color: student.qr_valid ? '#15803d' : '#b91c1c' }}>
                                {student.qr_valid
                                    ? <ShieldCheck className="w-5 h-5" />
                                    : <ShieldOff className="w-5 h-5" />
                                }
                            </div>
                            <div>
                                <p className="text-sm font-bold text-slate-800">{student.qr_valid ? 'Valid' : 'Invalid / Expired'}</p>
                                {student.qr_token_year && (
                                    <p className="text-xs text-slate-400 mt-0.5">Year {student.qr_token_year}</p>
                                )}
                            </div>
                        </div>
                        {student.qr_token && (
                            <p className="mt-3 text-[10px] font-mono text-slate-300 break-all">{student.qr_token}</p>
                        )}
                    </Card>

                    {/* Quick actions */}
                    <Card className="p-5">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-4">Quick Actions</p>
                        <div className="space-y-2">
                            <a
                                href={`/school/students/${student.id}/id-card`}
                                className="flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-semibold transition-all"
                                style={{ color: accent, background: alpha(accent, 0.07) }}
                            >
                                <CreditCard className="w-4 h-4" /> View ID Card
                            </a>
                            {is_admin && (
                                <Link
                                    href={`/school/students/${student.id}/edit`}
                                    className="flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all"
                                >
                                    <Edit2 className="w-4 h-4" /> Edit Profile
                                </Link>
                            )}
                            <div className="flex items-center gap-3 w-full px-4 py-3 rounded-2xl text-sm font-semibold text-slate-600">
                                <Activity className="w-4 h-4" />
                                <span className="flex-1">Status</span>
                                <StatusBadge status={student.status} />
                            </div>
                        </div>
                    </Card>
                </div>
            </div>
        </SchoolLayout>
    );
}
