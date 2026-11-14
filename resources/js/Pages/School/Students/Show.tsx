import React, { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft, Edit2, CreditCard, ShieldCheck, ShieldOff,
    User, Phone, Mail, Calendar, Droplets, Activity, BookOpen,
    Plus, Trash2, Star, Smartphone, Send,
    GraduationCap, ArrowUpRight, RotateCcw, LogOut, Award, Undo2,
    FileText, Upload, Download, Eye, CheckCircle2, Clock, MapPin,
    History, Paperclip, X, AlertTriangle, ScrollText,
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
    // enrolment states
    enrolled:    { bg: '#dcfce7', text: '#15803d' },
    completed:   { bg: '#d1fae5', text: '#047857' },
    repeated:    { bg: '#ffedd5', text: '#c2410c' },
    suspended:   { bg: '#fef3c7', text: '#b45309' },
};

function StatusBadge({ status }: { status: string }) {
    const colors = STATUS_COLORS[status] ?? { bg: '#f1f5f9', text: '#475569' };
    return (
        <span
            className="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold capitalize"
            style={{ background: colors.bg, color: colors.text }}
        >
            {status.replace(/_/g, ' ')}
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

/* ── Lifecycle types ─────────────────────────────────────────── */
interface Enrollment {
    id: number;
    label: string;
    class_name: string | null;
    academic_year: string | null;
    term_label: string | null;
    level_number: number | null;
    status: string;
    exit_reason: string | null;
    exit_note: string | null;
    destination_school: string | null;
    enrolled_on: string | null;
    ended_on: string | null;
    is_current: boolean;
    duration: string;
}

interface LifecycleEventRow {
    id: number;
    event: string;
    label: string;
    occurred_on: string | null;
    from_label: string | null;
    to_label: string | null;
    notes: string | null;
    by: string | null;
    is_exit: boolean;
}

interface DocumentRow {
    id: number;
    type: string;
    type_label: string;
    title: string;
    description: string | null;
    original_name: string | null;
    mime_type: string | null;
    size: string;
    is_image: boolean;
    is_pdf: boolean;
    issued_on: string | null;
    is_verified: boolean;
    created_at: string;
    view_url: string;
    download_url: string;
}

interface Profile {
    national_id: string | null;
    birth_place: string | null;
    nationality: string | null;
    mother_tongue: string | null;
    religion: string | null;
    region: string | null;
    zone: string | null;
    woreda: string | null;
    kebele: string | null;
    house_number: string | null;
    address_line: string | null;
    phone: string | null;
    email: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    emergency_contact_relation: string | null;
    previous_school: string | null;
    previous_school_year: string | null;
    previous_grade: string | null;
    admission_source: string | null;
    allergies: string | null;
    chronic_conditions: string | null;
    insurance_provider: string | null;
    insurance_number: string | null;
    special_needs: string | null;
    exit_reason_category: string | null;
    exit_note: string | null;
    exit_destination: string | null;
}

interface ClassOption {
    id: number;
    label: string;
    academic_year: string | null;
    seats_remaining: number | null;
}

interface Lifecycle {
    current: Enrollment | null;
    history: Enrollment[];
    events: LifecycleEventRow[];
    profile: Profile;
    documents: DocumentRow[];
    classes: ClassOption[];
    document_types: { value: string; label: string }[];
}

interface Props {
    student: StudentData;
    lifecycle: Lifecycle;
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

/* ── Info row ────────────────────────────────────────────────── */
function InfoRow({ icon: Icon, label, value }: { icon: React.ComponentType<{ className?: string }>; label: string; value: string | null | undefined }) {
    return (
        <div className="flex items-center justify-between py-3">
            <div className="flex items-center gap-2.5 text-slate-400">
                <Icon className="w-4 h-4" />
                <span className="text-xs font-medium uppercase tracking-wide">{label}</span>
            </div>
            <span className="text-sm font-semibold text-slate-800 text-right">
                {value || <span className="text-slate-300">—</span>}
            </span>
        </div>
    );
}

export default function Show({ student, lifecycle, is_admin, parent_app, alerts_on }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const [tab, setTab] = useState<'overview' | 'history' | 'documents' | 'details'>('overview');

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
                    <Link
                        href={`/school/students/${student.id}/transcripts`}
                        className="flex items-center gap-2 text-xs font-bold px-3.5 py-2 rounded-2xl text-slate-600 bg-slate-100 hover:bg-slate-200 transition-all"
                    >
                        <ScrollText className="w-4 h-4" /> Transcript
                    </Link>
                    <Link
                        href={`/school/certificates/create?student_id=${student.id}`}
                        className="flex items-center gap-2 text-xs font-bold px-3.5 py-2 rounded-2xl text-slate-600 bg-slate-100 hover:bg-slate-200 transition-all"
                    >
                        <Award className="w-4 h-4" /> Certificate
                    </Link>
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

            {/* Tab bar */}
            <div className="flex items-center gap-1 mb-6 p-1.5 rounded-2xl bg-slate-100/80 w-fit max-w-full overflow-x-auto">
                {([
                    { key: 'overview',  label: 'Overview',  icon: User },
                    { key: 'history',   label: 'Lifecycle', icon: History },
                    { key: 'documents', label: 'Documents', icon: FileText },
                    { key: 'details',   label: 'Full Profile', icon: MapPin },
                ] as const).map(t => {
                    const Icon = t.icon;
                    const on = tab === t.key;
                    return (
                        <button
                            key={t.key}
                            onClick={() => setTab(t.key)}
                            className={`flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-bold transition-all whitespace-nowrap ${on ? 'bg-white shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
                            style={on ? { color: accent } : undefined}
                        >
                            <Icon className="w-4 h-4" />
                            {t.label}
                            {t.key === 'documents' && lifecycle.documents.length > 0 && (
                                <span className="text-[10px] font-extrabold px-1.5 py-0.5 rounded-full"
                                    style={{ background: alpha(accent, 0.12), color: accent }}>
                                    {lifecycle.documents.length}
                                </span>
                            )}
                            {t.key === 'history' && lifecycle.history.length > 0 && (
                                <span className="text-[10px] font-extrabold px-1.5 py-0.5 rounded-full"
                                    style={{ background: alpha(accent, 0.12), color: accent }}>
                                    {lifecycle.history.length}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {tab === 'overview' && (
            <>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

                {/* ── Left 2 cols ── */}
                <div className="lg:col-span-2 space-y-5">

                    {/* Student info card */}
                    <Card className="p-6">
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
                                                        <button onClick={() => revokeAppAccess(g)}
                                                            className="text-[10px] font-semibold text-slate-400 hover:text-red-500 transition-colors">
                                                            Revoke
                                                        </button>
                                                    </>
                                                ) : (
                                                    <button onClick={() => grantAppAccess(g)}
                                                        className="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1.5 rounded-xl border border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 transition">
                                                        <Smartphone className="w-3 h-3" /> Grant app access
                                                    </button>
                                                )}
                                            </div>
                                        )}
                                        {is_admin && (
                                            <button onClick={() => removeGuardian(g.id)}
                                                className="flex-shrink-0 p-1.5 rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-colors"
                                                title="Remove guardian">
                                                <Trash2 className="w-3.5 h-3.5" />
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                </div>

                {/* ── Right column ── */}
                <div className="space-y-5">
                    <Card className="p-5">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-4">Enrolment</p>
                        <div className="space-y-2">
                            {[
                                ['Joined', student.join_date],
                                ['Enrolled', student.enrollment_date],
                                ['Left', student.end_date],
                            ].map(([label, value]) => (
                                <div key={label as string} className="flex items-center justify-between">
                                    <span className="text-xs text-slate-400">{label}</span>
                                    <span className="text-xs font-bold text-slate-700">{(value as string) || <span className="text-slate-300">—</span>}</span>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* QR status */}
                    <Card className="p-5">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-4">QR Token</p>
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0"
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
            </>
            )}

            {tab === 'history' && (
                <LifecyclePanel
                    studentId={student.id}
                    lifecycle={lifecycle}
                    accent={accent}
                    isAdmin={is_admin}
                />
            )}

            {tab === 'documents' && (
                <DocumentsPanel
                    studentId={student.id}
                    lifecycle={lifecycle}
                    accent={accent}
                    isAdmin={is_admin}
                />
            )}

            {tab === 'details' && (
                <DetailsPanel
                    studentId={student.id}
                    lifecycle={lifecycle}
                    accent={accent}
                    isAdmin={is_admin}
                />
            )}
        </SchoolLayout>
    );
}

/* ══════════════════════════════════════════════════════════════
   Lifecycle panel — where the student stands, and how they got here
   ══════════════════════════════════════════════════════════════ */

type LifecycleAction =
    | 'enroll' | 'promote' | 'repeat' | 'change-class'
    | 'transfer' | 'withdraw' | 'graduate';

const ACTION_META: Record<LifecycleAction, { title: string; hint: string; icon: React.ComponentType<{ className?: string }>; danger?: boolean }> = {
    enroll:         { title: 'Enrol',           hint: 'Open a new period of study for this student.', icon: GraduationCap },
    promote:        { title: 'Promote',         hint: 'Close the current year and open the next one in another class.', icon: ArrowUpRight },
    repeat:         { title: 'Repeat',          hint: 'Keep the student at the same level for another year.', icon: RotateCcw },
    'change-class': { title: 'Change class',    hint: 'Move to a different class without ending the year.', icon: BookOpen },
    transfer:       { title: 'Transfer out',    hint: 'Leaving for another institution.', icon: LogOut, danger: true },
    withdraw:       { title: 'Withdraw',        hint: 'Left the school, not transferring.', icon: LogOut, danger: true },
    graduate:       { title: 'Graduate',        hint: 'Completed the programme.', icon: Award },
};

function LifecyclePanel({ studentId, lifecycle, accent, isAdmin }: {
    studentId: number;
    lifecycle: Lifecycle;
    accent: string;
    isAdmin: boolean;
}) {
    const current = lifecycle.current;
    const [action, setAction] = useState<LifecycleAction | null>(null);
    const [classId, setClassId] = useState<string>('');
    const [note, setNote] = useState('');
    const [destination, setDestination] = useState('');
    const [when, setWhen] = useState(new Date().toISOString().slice(0, 10));
    const [busy, setBusy] = useState(false);

    function reset() {
        setAction(null); setClassId(''); setNote(''); setDestination(''); setBusy(false);
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (!action) return;
        setBusy(true);

        const url = `/school/students/${studentId}/lifecycle/${action}`;
        const payload: Record<string, unknown> = { note: note || undefined };

        if (action === 'enroll') {
            payload.class_id = classId ? Number(classId) : undefined;
            payload.enrolled_on = when;
        } else if (action === 'transfer') {
            payload.destination_school = destination;
            payload.note = note || undefined;
            payload.date = when;
        } else if (action === 'withdraw' || action === 'graduate') {
            payload.date = when;
        } else {
            payload.class_id = classId ? Number(classId) : undefined;
        }

        router.post(url, payload as never, {
            preserveScroll: true,
            onFinish: () => { reset(); },
        });
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <div className="space-y-5">

            {/* Current standing */}
            <Card className="p-6">
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Current standing</p>
                        {current ? (
                            <>
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h2 className="text-xl font-extrabold text-slate-900">{current.label}</h2>
                                    <StatusBadge status={current.status} />
                                    {current.is_current && (
                                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">Current</span>
                                    )}
                                </div>
                                <p className="text-xs text-slate-400 mt-1.5">
                                    Since {current.enrolled_on} · {current.duration} so far
                                </p>
                            </>
                        ) : (
                            <>
                                <div className="flex items-center gap-2">
                                    <AlertTriangle className="w-4 h-4 text-amber-500" />
                                    <h2 className="text-lg font-extrabold text-slate-900">Not currently enrolled</h2>
                                </div>
                                <p className="text-xs text-slate-400 mt-1.5">
                                    This student has no open period of study. Enrol them to place them in a class.
                                </p>
                            </>
                        )}
                    </div>

                    {isAdmin && (
                        <div className="flex flex-wrap items-center gap-2">
                            {current ? (
                                <>
                                    <ActionButton label="Promote" icon={ArrowUpRight} accent={accent} onClick={() => setAction('promote')} />
                                    <ActionButton label="Change class" icon={BookOpen} accent={accent} onClick={() => setAction('change-class')} />
                                    <ActionButton label="Repeat" icon={RotateCcw} accent={accent} onClick={() => setAction('repeat')} />
                                    <ActionButton label="Graduate" icon={Award} accent={accent} onClick={() => setAction('graduate')} />
                                    <ActionButton label="Transfer" icon={LogOut} accent={accent} onClick={() => setAction('transfer')} danger />
                                    <ActionButton label="Withdraw" icon={LogOut} accent={accent} onClick={() => setAction('withdraw')} danger />
                                </>
                            ) : (
                                <ActionButton label="Enrol / reinstate" icon={GraduationCap} accent={accent} onClick={() => setAction('enroll')} />
                            )}
                        </div>
                    )}
                </div>

                {/* Inline action form */}
                {action && (
                    <form onSubmit={submit} className="mt-5 p-4 rounded-2xl border border-slate-100 bg-slate-50/70 space-y-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <p className="text-sm font-bold text-slate-800">{ACTION_META[action].title}</p>
                                <p className="text-xs text-slate-400">{ACTION_META[action].hint}</p>
                            </div>
                            <button type="button" onClick={reset} className="p-1.5 rounded-lg text-slate-400 hover:bg-white hover:text-slate-600">
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        {(action === 'promote' || action === 'change-class' || action === 'repeat' || action === 'enroll') && (
                            <div>
                                <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">
                                    {action === 'repeat' ? 'Class (optional — defaults to current)' : 'Target class'}
                                </label>
                                <select
                                    value={classId}
                                    onChange={e => setClassId(e.target.value)}
                                    className={inputCls + ' mt-1.5'}
                                    style={inputStyle}
                                    required={action !== 'repeat'}
                                >
                                    <option value="">Select a class…</option>
                                    {lifecycle.classes.map(c => (
                                        <option key={c.id} value={c.id}>
                                            {c.label}{c.seats_remaining !== null ? ` — ${c.seats_remaining} seats left` : ''}
                                        </option>
                                    ))}
                                </select>
                                {lifecycle.classes.length === 0 && (
                                    <p className="text-xs text-amber-600 mt-1.5">
                                        No active classes yet — create one under Classes first.
                                    </p>
                                )}
                            </div>
                        )}

                        {action === 'transfer' && (
                            <div>
                                <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Destination school</label>
                                <input
                                    value={destination}
                                    onChange={e => setDestination(e.target.value)}
                                    className={inputCls + ' mt-1.5'}
                                    style={inputStyle}
                                    placeholder="e.g. St. Joseph School, Addis Ababa"
                                    required
                                />
                            </div>
                        )}

                        {(action === 'transfer' || action === 'withdraw' || action === 'graduate' || action === 'enroll') && (
                            <div>
                                <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Effective date</label>
                                <input
                                    type="date"
                                    value={when}
                                    onChange={e => setWhen(e.target.value)}
                                    className={inputCls + ' mt-1.5'}
                                    style={inputStyle}
                                />
                            </div>
                        )}

                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Note (optional)</label>
                            <textarea
                                value={note}
                                onChange={e => setNote(e.target.value)}
                                rows={2}
                                className={inputCls + ' mt-1.5'}
                                style={inputStyle}
                                placeholder="Reason, reference number, anything worth keeping on the record…"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={busy}
                            className="px-4 py-2 rounded-2xl text-sm font-bold text-white disabled:opacity-50"
                            style={{ background: ACTION_META[action].danger ? '#dc2626' : accent }}
                        >
                            {busy ? 'Working…' : `Confirm ${ACTION_META[action].title.toLowerCase()}`}
                        </button>
                    </form>
                )}
            </Card>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">

                {/* Enrolment history */}
                <Card className="p-6">
                    <h3 className="text-base font-bold text-slate-800 mb-1">Enrolment history</h3>
                    <p className="text-xs text-slate-400 mb-4">Every period of study, newest first</p>

                    {lifecycle.history.length === 0 ? (
                        <p className="text-sm text-slate-400 py-6 text-center">No enrolments recorded yet</p>
                    ) : (
                        <div className="space-y-2.5">
                            {lifecycle.history.map(e => (
                                <div key={e.id} className="p-3.5 rounded-2xl border border-slate-100 bg-slate-50/40">
                                    <div className="flex items-center justify-between gap-2 flex-wrap">
                                        <p className="text-sm font-bold text-slate-800">{e.label}</p>
                                        <StatusBadge status={e.status} />
                                    </div>
                                    <p className="text-[11px] text-slate-400 mt-1">
                                        {e.enrolled_on} → {e.ended_on ?? 'present'} · {e.duration}
                                    </p>
                                    {e.destination_school && (
                                        <p className="text-[11px] text-slate-500 mt-1">→ {e.destination_school}</p>
                                    )}
                                    {e.exit_note && (
                                        <p className="text-[11px] text-slate-500 mt-1 italic">{e.exit_note}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                {/* Timeline */}
                <Card className="p-6">
                    <h3 className="text-base font-bold text-slate-800 mb-1">Timeline</h3>
                    <p className="text-xs text-slate-400 mb-4">Append-only record — nothing here is ever rewritten</p>

                    {lifecycle.events.length === 0 ? (
                        <p className="text-sm text-slate-400 py-6 text-center">No events yet</p>
                    ) : (
                        <ol className="relative border-l border-slate-100 ml-2 space-y-4">
                            {lifecycle.events.map(ev => (
                                <li key={ev.id} className="ml-4">
                                    <span
                                        className="absolute -left-[5px] mt-1.5 w-2.5 h-2.5 rounded-full"
                                        style={{ background: ev.is_exit ? '#dc2626' : accent }}
                                    />
                                    <div className="flex items-baseline gap-2 flex-wrap">
                                        <p className="text-sm font-bold text-slate-800">{ev.label}</p>
                                        <p className="text-[11px] text-slate-400">{ev.occurred_on}</p>
                                    </div>
                                    {(ev.from_label || ev.to_label) && (
                                        <p className="text-[11px] text-slate-500 mt-0.5">
                                            {ev.from_label ?? '—'} → {ev.to_label ?? '—'}
                                        </p>
                                    )}
                                    {ev.notes && <p className="text-[11px] text-slate-400 mt-0.5 italic">{ev.notes}</p>}
                                    {ev.by && <p className="text-[10px] text-slate-300 mt-0.5">by {ev.by}</p>}
                                </li>
                            ))}
                        </ol>
                    )}
                </Card>
            </div>
        </div>
    );
}

function ActionButton({ label, icon: Icon, accent, onClick, danger }: {
    label: string; icon: React.ComponentType<{ className?: string }>;
    accent: string; onClick: () => void; danger?: boolean;
}) {
    return (
        <button
            onClick={onClick}
            className="flex items-center gap-1.5 text-xs font-bold px-3 py-2 rounded-xl transition-all"
            style={danger
                ? { color: '#b91c1c', background: '#fef2f2' }
                : { color: accent, background: alpha(accent, 0.08) }}
        >
            <Icon className="w-3.5 h-3.5" /> {label}
        </button>
    );
}

/* ══════════════════════════════════════════════════════════════
   Documents panel — the vault
   ══════════════════════════════════════════════════════════════ */

function DocumentsPanel({ studentId, lifecycle, accent, isAdmin }: {
    studentId: number;
    lifecycle: Lifecycle;
    accent: string;
    isAdmin: boolean;
}) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset, progress } = useForm<{
        type: string; title: string; description: string;
        issued_on: string; file: File | null;
    }>({
        type: 'birth_certificate',
        title: '',
        description: '',
        issued_on: '',
        file: null,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/school/students/${studentId}/documents`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => { reset(); setOpen(false); },
        });
    }

    function remove(id: number, title: string) {
        if (!confirm(`Delete "${title}"? This removes the file permanently.`)) return;
        router.delete(`/school/students/${studentId}/documents/${id}`, { preserveScroll: true });
    }

    function verify(id: number) {
        router.post(`/school/students/${studentId}/documents/${id}/verify`, {}, { preserveScroll: true });
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <Card className="p-6">
            <div className="flex items-center justify-between mb-5">
                <div>
                    <h2 className="text-base font-bold text-slate-800">Documents</h2>
                    <p className="text-xs text-slate-400 mt-0.5">
                        Birth certificate, previous report cards, transfer letters — stored privately
                    </p>
                </div>
                {isAdmin && !open && (
                    <button
                        onClick={() => setOpen(true)}
                        className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-white"
                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.3)}` }}
                    >
                        <Upload className="w-4 h-4" /> Upload
                    </button>
                )}
            </div>

            {open && isAdmin && (
                <form onSubmit={submit} className="mb-6 p-4 rounded-2xl border border-slate-100 bg-slate-50/70 space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Type</label>
                            <select value={data.type} onChange={e => setData('type', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle}>
                                {lifecycle.document_types.map(t => (
                                    <option key={t.value} value={t.value}>{t.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Title</label>
                            <input value={data.title} onChange={e => setData('title', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle}
                                placeholder="e.g. Grade 7 report card" required />
                            {errors.title && <p className="text-red-500 text-xs mt-1">{errors.title}</p>}
                        </div>
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Issued on</label>
                            <input type="date" value={data.issued_on} onChange={e => setData('issued_on', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle} />
                        </div>
                        <div>
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">File (PDF or image, max 10 MB)</label>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                                onChange={e => setData('file', e.target.files?.[0] ?? null)}
                                className="mt-1.5 block w-full text-sm text-slate-600 file:mr-3 file:px-3 file:py-2 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:text-white"
                                style={inputStyle}
                                required />
                            {errors.file && <p className="text-red-500 text-xs mt-1">{errors.file}</p>}
                        </div>
                        <div className="sm:col-span-2">
                            <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Description (optional)</label>
                            <input value={data.description} onChange={e => setData('description', e.target.value)}
                                className={inputCls + ' mt-1.5'} style={inputStyle} />
                        </div>
                    </div>

                    {progress && (
                        <div className="w-full bg-slate-200 rounded-full h-1.5">
                            <div className="h-1.5 rounded-full transition-all" style={{ width: `${progress.percentage}%`, background: accent }} />
                        </div>
                    )}

                    <div className="flex items-center gap-2">
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 rounded-2xl text-sm font-bold text-white disabled:opacity-50"
                            style={{ background: accent }}>
                            {processing ? 'Uploading…' : 'Upload document'}
                        </button>
                        <button type="button" onClick={() => { reset(); setOpen(false); }}
                            className="px-4 py-2 rounded-2xl text-sm font-semibold text-slate-500 hover:bg-slate-100">
                            Cancel
                        </button>
                    </div>
                </form>
            )}

            {lifecycle.documents.length === 0 ? (
                <div className="py-12 text-center">
                    <Paperclip className="w-8 h-8 text-slate-200 mx-auto mb-3" />
                    <p className="text-sm text-slate-400">No documents yet</p>
                    <p className="text-xs text-slate-300 mt-1">Birth certificates, previous reports and transfer letters live here.</p>
                </div>
            ) : (
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    {lifecycle.documents.map(d => (
                        <div key={d.id} className="p-4 rounded-2xl border border-slate-100 bg-slate-50/40 flex gap-3">
                            <div className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                                style={{ background: alpha(accent, 0.10), color: accent }}>
                                <FileText className="w-5 h-5" />
                            </div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-start justify-between gap-2">
                                    <p className="text-sm font-bold text-slate-800 truncate">{d.title}</p>
                                    {d.is_verified && (
                                        <span className="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-600 flex-shrink-0">
                                            <CheckCircle2 className="w-2.5 h-2.5" /> Verified
                                        </span>
                                    )}
                                </div>
                                <p className="text-[11px] text-slate-400">{d.type_label} · {d.size}</p>
                                {d.issued_on && <p className="text-[11px] text-slate-400">Issued {d.issued_on}</p>}

                                <div className="flex items-center gap-1 mt-2 flex-wrap">
                                    <a href={d.view_url} target="_blank" rel="noreferrer"
                                        className="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-lg hover:bg-white"
                                        style={{ color: accent }}>
                                        <Eye className="w-3 h-3" /> View
                                    </a>
                                    <a href={d.download_url}
                                        className="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-lg text-slate-500 hover:bg-white">
                                        <Download className="w-3 h-3" /> Download
                                    </a>
                                    {isAdmin && (
                                        <button onClick={() => verify(d.id)}
                                            className="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-lg text-slate-500 hover:bg-white">
                                            <CheckCircle2 className="w-3 h-3" /> {d.is_verified ? 'Unverify' : 'Verify'}
                                        </button>
                                    )}
                                    {isAdmin && (
                                        <button onClick={() => remove(d.id, d.title)}
                                            className="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-lg text-slate-400 hover:bg-red-50 hover:text-red-600 ml-auto">
                                            <Trash2 className="w-3 h-3" />
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </Card>
    );
}

/* ══════════════════════════════════════════════════════════════
   Full profile panel
   ══════════════════════════════════════════════════════════════ */

function DetailsPanel({ studentId, lifecycle, accent, isAdmin }: {
    studentId: number;
    lifecycle: Lifecycle;
    accent: string;
    isAdmin: boolean;
}) {
    const p = lifecycle.profile;
    const { data, setData, patch, processing, errors } = useForm({
        national_id: p.national_id ?? '',
        birth_place: p.birth_place ?? '',
        nationality: p.nationality ?? '',
        mother_tongue: p.mother_tongue ?? '',
        religion: p.religion ?? '',
        region: p.region ?? '',
        zone: p.zone ?? '',
        woreda: p.woreda ?? '',
        kebele: p.kebele ?? '',
        house_number: p.house_number ?? '',
        address_line: p.address_line ?? '',
        phone: p.phone ?? '',
        email: p.email ?? '',
        emergency_contact_name: p.emergency_contact_name ?? '',
        emergency_contact_phone: p.emergency_contact_phone ?? '',
        emergency_contact_relation: p.emergency_contact_relation ?? '',
        previous_school: p.previous_school ?? '',
        previous_school_year: p.previous_school_year ?? '',
        previous_grade: p.previous_grade ?? '',
        admission_source: p.admission_source ?? '',
        allergies: p.allergies ?? '',
        chronic_conditions: p.chronic_conditions ?? '',
        insurance_provider: p.insurance_provider ?? '',
        insurance_number: p.insurance_number ?? '',
        special_needs: p.special_needs ?? '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(`/school/students/${studentId}/profile`, { preserveScroll: true });
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition disabled:bg-slate-50 disabled:text-slate-400';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    function Field({ label, name, type = 'text', placeholder }: {
        label: string; name: keyof typeof data; type?: string; placeholder?: string;
    }) {
        return (
            <div>
                <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">{label}</label>
                <input
                    type={type}
                    value={(data[name] as string) ?? ''}
                    onChange={e => setData(name, e.target.value)}
                    className={inputCls + ' mt-1.5'}
                    style={inputStyle}
                    placeholder={placeholder}
                    disabled={!isAdmin}
                />
                {errors[name] && <p className="text-red-500 text-xs mt-1">{errors[name]}</p>}
            </div>
        );
    }

    function Section({ title, hint, children }: { title: string; hint?: string; children: React.ReactNode }) {
        return (
            <div>
                <h3 className="text-sm font-bold text-slate-800">{title}</h3>
                {hint && <p className="text-[11px] text-slate-400 mb-3">{hint}</p>}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">{children}</div>
            </div>
        );
    }

    return (
        <form onSubmit={submit} className="space-y-5">
            <Card className="p-6 space-y-7">
                <Section title="Identity" hint="National registration and personal background">
                    <Field label="National ID" name="national_id" />
                    <Field label="Place of birth" name="birth_place" />
                    <Field label="Nationality" name="nationality" placeholder="Ethiopian" />
                    <Field label="Mother tongue" name="mother_tongue" placeholder="Amharic" />
                    <Field label="Religion" name="religion" />
                </Section>

                <hr className="border-slate-100" />

                <Section title="Address & contact" hint="Where the student lives and how to reach them">
                    <Field label="Region" name="region" placeholder="Oromia" />
                    <Field label="Zone" name="zone" />
                    <Field label="Woreda" name="woreda" />
                    <Field label="Kebele" name="kebele" />
                    <Field label="House number" name="house_number" />
                    <Field label="Address line" name="address_line" />
                    <Field label="Phone" name="phone" placeholder="+251…" />
                    <Field label="Email" name="email" type="email" />
                </Section>

                <hr className="border-slate-100" />

                <Section title="Emergency contact" hint="Who to call if a guardian cannot be reached">
                    <Field label="Name" name="emergency_contact_name" />
                    <Field label="Phone" name="emergency_contact_phone" />
                    <Field label="Relationship" name="emergency_contact_relation" />
                </Section>

                <hr className="border-slate-100" />

                <Section title="Previous education" hint="Where they came from before joining">
                    <Field label="Previous school" name="previous_school" />
                    <Field label="Year completed" name="previous_school_year" placeholder="2024/2025" />
                    <Field label="Last grade / level" name="previous_grade" />
                    <Field label="How they heard about us" name="admission_source" placeholder="Referral, walk-in, radio…" />
                </Section>

                <hr className="border-slate-100" />

                <Section title="Health" hint="Visible to staff who need it — keep it factual">
                    <Field label="Allergies" name="allergies" />
                    <Field label="Chronic conditions" name="chronic_conditions" />
                    <Field label="Insurance provider" name="insurance_provider" />
                    <Field label="Insurance number" name="insurance_number" />
                </Section>

                <div>
                    <label className="text-[11px] font-bold uppercase tracking-widest text-slate-400">Special needs / support notes</label>
                    <textarea
                        value={data.special_needs}
                        onChange={e => setData('special_needs', e.target.value)}
                        rows={3}
                        className={inputCls + ' mt-1.5'}
                        style={inputStyle}
                        disabled={!isAdmin}
                    />
                </div>

                {isAdmin && (
                    <div className="flex items-center gap-2 pt-1">
                        <button type="submit" disabled={processing}
                            className="px-5 py-2.5 rounded-2xl text-sm font-bold text-white disabled:opacity-50"
                            style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.3)}` }}>
                            {processing ? 'Saving…' : 'Save profile'}
                        </button>
                        <span className="text-xs text-slate-400">Changes are recorded on the student's timeline.</span>
                    </div>
                )}
            </Card>
        </form>
    );
}
