import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft, Phone, Mail, MapPin, GraduationCap, Trash2, UserCheck,
    ArrowRight, Calendar, School, AlertTriangle,
} from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

const STATUS_COLORS: Record<string, [string, string]> = {
    enquiry:   ['#f1f5f9', '#475569'],
    applied:   ['#dbeafe', '#1d4ed8'],
    screening: ['#fef3c7', '#b45309'],
    offered:   ['#e0e7ff', '#4338ca'],
    accepted:  ['#d1fae5', '#047857'],
    rejected:  ['#fee2e2', '#b91c1c'],
    withdrawn: ['#f3f4f6', '#6b7280'],
    converted: ['#dcfce7', '#15803d'],
};

interface Option { value: string | number; label: string }

interface Application {
    id: number;
    reference: string;
    full_name: string;
    first_name_am: string | null;
    last_name_am: string | null;
    gender: string | null;
    date_of_birth: string | null;
    phone: string | null;
    email: string | null;
    address_line: string | null;
    guardian_name: string | null;
    guardian_phone: string | null;
    guardian_email: string | null;
    guardian_relationship: string | null;
    previous_school: string | null;
    previous_grade: string | null;
    programme: string | null;
    class_name: string | null;
    academic_year: string | null;
    intended_level: string | null;
    status: string;
    status_label: string;
    source_label: string;
    notes: string | null;
    decision_note: string | null;
    follow_up_on: string | null;
    handled_by: string | null;
    decided_at: string | null;
    converted_at: string | null;
    student: { id: number; uid: string; name: string } | null;
    created_at: string;
}

interface Props {
    application: Application;
    options: { programmes: Option[]; classes: Option[]; sources: Option[] };
    statuses: Option[];
    is_admin: boolean;
}

const PROGRESSION: Record<string, string[]> = {
    enquiry:   ['applied', 'rejected', 'withdrawn'],
    applied:   ['screening', 'offered', 'rejected', 'withdrawn'],
    screening: ['offered', 'rejected', 'withdrawn'],
    offered:   ['accepted', 'rejected', 'withdrawn'],
    accepted:  ['withdrawn'],
    rejected:  ['applied'],
    withdrawn: ['applied'],
    converted: [],
};

export default function Show({ application: a, options, statuses, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const [classId, setClassId] = useState<string>(
        a.class_name ? String(options.classes.find(c => c.label.startsWith(a.class_name ?? ''))?.value ?? '') : ''
    );
    const [note, setNote] = useState(a.decision_note ?? '');
    const [deciding, setDeciding] = useState(false);

    const [bg, fg] = STATUS_COLORS[a.status] ?? ['#f1f5f9', '#475569'];
    const next = PROGRESSION[a.status] ?? [];

    function setStatus(status: string) {
        router.post(`/school/admissions/${a.id}/status`, { status, decision_note: note } as never,
            { preserveScroll: true });
    }

    function convert() {
        if (!confirm(`Enrol ${a.full_name} as a student? This creates their student record and opens their first enrolment.`)) return;
        setDeciding(true);
        router.post(`/school/admissions/${a.id}/convert`,
            { class_id: classId || null, note } as never,
            { onFinish: () => setDeciding(false) });
    }

    function remove() {
        if (!confirm('Delete this application permanently?')) return;
        router.delete(`/school/admissions/${a.id}`);
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 transition bg-white';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    function Info({ icon: Icon, label, value, href }: {
        icon: React.ComponentType<{ className?: string }>; label: string;
        value: string | null; href?: string;
    }) {
        return (
            <div className="flex items-start justify-between gap-3 py-2.5 border-b border-slate-50 last:border-0">
                <span className="flex items-center gap-2 text-xs text-slate-400 flex-shrink-0">
                    <Icon className="w-3.5 h-3.5" /> {label}
                </span>
                {value ? (
                    href ? (
                        <a href={href} className="text-sm font-semibold text-slate-800 text-right hover:underline">{value}</a>
                    ) : (
                        <span className="text-sm font-semibold text-slate-800 text-right">{value}</span>
                    )
                ) : <span className="text-sm text-slate-300">—</span>}
            </div>
        );
    }

    return (
        <SchoolLayout>
            <Head title={`${a.full_name} · ${a.reference}`} />

            <div className="flex items-center justify-between mb-6">
                <Link href="/school/admissions"
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800">
                    <ArrowLeft className="w-4 h-4" /> Back to Admissions
                </Link>
                {is_admin && !a.student && (
                    <button onClick={remove}
                        className="flex items-center gap-1.5 text-xs font-bold px-3 py-2 rounded-xl text-red-600 bg-red-50 hover:bg-red-100">
                        <Trash2 className="w-3.5 h-3.5" /> Delete
                    </button>
                )}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

                {/* ── Left: the person ── */}
                <div className="lg:col-span-2 space-y-5">
                    <div className="bg-white rounded-3xl border border-slate-200 p-6">
                        <div className="flex items-start justify-between gap-4 flex-wrap mb-5">
                            <div>
                                <h1 className="text-2xl font-extrabold text-slate-900">{a.full_name}</h1>
                                {(a.first_name_am || a.last_name_am) && (
                                    <p className="text-base text-slate-500 mt-0.5" dir="auto">
                                        {a.first_name_am} {a.last_name_am}
                                    </p>
                                )}
                                <p className="text-[11px] font-mono text-slate-400 mt-1">{a.reference}</p>
                            </div>
                            <span className="inline-flex px-3 py-1.5 rounded-full text-xs font-bold"
                                style={{ background: bg, color: fg }}>
                                {a.status_label}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
                            <Info icon={Calendar} label="Date of birth" value={a.date_of_birth} />
                            <Info icon={UserCheck} label="Gender" value={a.gender} />
                            <Info icon={School} label="Programme" value={a.programme} />
                            <Info icon={GraduationCap} label="Class" value={a.class_name ?? a.intended_level} />
                            <Info icon={Calendar} label="Academic year" value={a.academic_year} />
                            <Info icon={School} label="Previous school" value={a.previous_school} />
                        </div>
                    </div>

                    <div className="bg-white rounded-3xl border border-slate-200 p-6">
                        <h2 className="text-sm font-bold text-slate-800 mb-2">Guardian / enquirer</h2>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-6">
                            <Info icon={UserCheck} label="Name" value={a.guardian_name} />
                            <Info icon={UserCheck} label="Relationship" value={a.guardian_relationship} />
                            <Info icon={Phone} label="Phone" value={a.guardian_phone ?? a.phone}
                                href={a.guardian_phone ? `tel:${a.guardian_phone}` : undefined} />
                            <Info icon={Mail} label="Email" value={a.guardian_email ?? a.email}
                                href={(a.guardian_email ?? a.email) ? `mailto:${a.guardian_email ?? a.email}` : undefined} />
                            <div className="sm:col-span-2">
                                <Info icon={MapPin} label="Address" value={a.address_line} />
                            </div>
                        </div>
                    </div>

                    {a.notes && (
                        <div className="bg-white rounded-3xl border border-slate-200 p-6">
                            <h2 className="text-sm font-bold text-slate-800 mb-2">Notes</h2>
                            <p className="text-sm text-slate-600 leading-relaxed whitespace-pre-line">{a.notes}</p>
                        </div>
                    )}
                </div>

                {/* ── Right: the pipeline ── */}
                <div className="space-y-5">
                    {a.student ? (
                        <div className="bg-white rounded-3xl border border-emerald-200 p-6">
                            <div className="flex items-center gap-2 mb-2">
                                <GraduationCap className="w-5 h-5 text-emerald-600" />
                                <h2 className="text-sm font-bold text-slate-800">Enrolled</h2>
                            </div>
                            <p className="text-xs text-slate-400 mb-3">Converted {a.converted_at}</p>
                            <Link href={`/school/students/${a.student.id}`}
                                className="flex items-center justify-between p-3 rounded-2xl hover:bg-emerald-50 transition"
                                style={{ background: alpha('#10b981', 0.06) }}>
                                <div>
                                    <p className="text-sm font-bold text-slate-800">{a.student.name}</p>
                                    <p className="text-[11px] font-mono text-slate-400">{a.student.uid}</p>
                                </div>
                                <ArrowRight className="w-4 h-4 text-emerald-600" />
                            </Link>
                        </div>
                    ) : is_admin ? (
                        <>
                            {/* Enrol */}
                            {(a.status === 'accepted' || a.status === 'offered' || a.status === 'applied') && (
                                <div className="bg-white rounded-3xl border border-slate-200 p-6">
                                    <h2 className="text-sm font-bold text-slate-800 mb-1">Enrol as a student</h2>
                                    <p className="text-[11px] text-slate-400 mb-3">
                                        Creates the student record, QR token and first enrolment.
                                    </p>

                                    <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">
                                        Place in class
                                    </label>
                                    <select value={classId} onChange={e => setClassId(e.target.value)}
                                        className={inputCls} style={inputStyle}>
                                        <option value="">Leave unplaced for now</option>
                                        {options.classes.map(c => (
                                            <option key={c.value} value={c.value}>{c.label}</option>
                                        ))}
                                    </select>

                                    <button onClick={convert} disabled={deciding}
                                        className="w-full mt-3 flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl text-sm font-bold text-white disabled:opacity-50"
                                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.3)}` }}>
                                        <GraduationCap className="w-4 h-4" />
                                        {deciding ? 'Enrolling…' : 'Enrol student'}
                                    </button>
                                </div>
                            )}

                            {/* Move along the pipeline */}
                            {next.length > 0 && (
                                <div className="bg-white rounded-3xl border border-slate-200 p-6">
                                    <h2 className="text-sm font-bold text-slate-800 mb-3">Move to</h2>

                                    <label className="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">
                                        Decision note (optional)
                                    </label>
                                    <input value={note} onChange={e => setNote(e.target.value)}
                                        className={inputCls + ' mb-3'} style={inputStyle}
                                        placeholder="Reason, placement test score…" />

                                    <div className="flex flex-wrap gap-2">
                                        {next.map(s => {
                                            const label = statuses.find(x => x.value === s)?.label ?? s;
                                            const bad = s === 'rejected';
                                            return (
                                                <button key={s} onClick={() => setStatus(s)}
                                                    className="px-3 py-2 rounded-xl text-xs font-bold transition"
                                                    style={bad
                                                        ? { color: '#b91c1c', background: '#fef2f2' }
                                                        : { color: accent, background: alpha(accent, 0.08) }}>
                                                    {label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </>
                    ) : null}

                    <div className="bg-white rounded-3xl border border-slate-200 p-6">
                        <h2 className="text-sm font-bold text-slate-800 mb-3">Record</h2>
                        <Info icon={Calendar} label="Received" value={a.created_at} />
                        <Info icon={Calendar} label="Follow up" value={a.follow_up_on} />
                        <Info icon={UserCheck} label="Handled by" value={a.handled_by} />
                        <Info icon={ArrowRight} label="Decided" value={a.decided_at} />
                        <Info icon={School} label="Source" value={a.source_label} />
                        {a.decision_note && (
                            <div className="mt-3 p-3 rounded-2xl bg-slate-50">
                                <p className="text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-1">
                                    Decision note
                                </p>
                                <p className="text-xs text-slate-600 leading-relaxed">{a.decision_note}</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </SchoolLayout>
    );
}
