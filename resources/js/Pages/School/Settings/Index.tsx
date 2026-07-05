import React from 'react';
import { Head, useForm, Link, usePage } from '@inertiajs/react';
import { Settings2, CalendarRange, Palette, Phone, ClipboardCheck } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}

function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return <div className={`bg-white rounded-3xl border border-slate-200/60 ${className}`} style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}>{children}</div>;
}

interface Props {
    settings: {
        name: string; code: string; academic_year: string; plan_tier: string;
        attendance_cadence: string; accent_color: string | null;
        phone: string | null; email: string | null; address: string | null;
        working_days: number[];
    };
    has_subject_attendance: boolean;
}

const DAY_SHORT = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const CADENCES = [
    { value: 'once', label: 'Once a day', desc: 'One full-day session per class per day.' },
    { value: 'twice', label: 'Twice a day', desc: 'Separate morning and afternoon sessions (recommended).' },
    { value: 'per_period', label: 'Per period', desc: 'Attendance for every timetable period. Requires the Per-Subject Attendance module.' },
];

export default function Index({ settings, has_subject_attendance }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent as string);
    const inputCls = 'w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm text-slate-700 focus:outline-none focus:ring-2 bg-white';

    const { data, setData, patch, processing } = useForm({
        attendance_cadence: settings.attendance_cadence,
        accent_color: settings.accent_color ?? '#059669',
        phone: settings.phone ?? '',
        email: settings.email ?? '',
        address: settings.address ?? '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch('/school/settings', { preserveScroll: true });
    }

    return (
        <SchoolLayout>
            <Head title="Settings" />

            <div className="mb-6 flex items-center gap-3">
                <div className="w-11 h-11 rounded-2xl flex items-center justify-center" style={{ background: `${accent}14`, color: accent }}>
                    <Settings2 className="w-5 h-5" />
                </div>
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900">School Settings</h1>
                    <p className="text-sm text-slate-500">{settings.name} · {settings.code} · {settings.academic_year} · {settings.plan_tier} plan</p>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-6 max-w-3xl">
                {/* Attendance cadence */}
                <Card className="p-6">
                    <div className="flex items-center gap-2 mb-1">
                        <ClipboardCheck className="w-4 h-4" style={{ color: accent }} />
                        <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>Attendance</p>
                    </div>
                    <p className="text-sm text-slate-500 mb-4">How often teachers record attendance each day. The web form and the mobile app both follow this.</p>
                    <div className="space-y-2">
                        {CADENCES.map(c => {
                            const disabled = c.value === 'per_period' && !has_subject_attendance;
                            const on = data.attendance_cadence === c.value;
                            return (
                                <label key={c.value} className={`flex items-start gap-3 p-4 rounded-2xl border cursor-pointer transition ${on ? 'border-transparent' : 'border-slate-200 hover:bg-slate-50'} ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    style={on ? { background: `${accent}0d`, boxShadow: `inset 0 0 0 2px ${accent}` } : undefined}>
                                    <input type="radio" name="cadence" className="mt-1 accent-current" style={{ color: accent }}
                                        checked={on} disabled={disabled}
                                        onChange={() => setData('attendance_cadence', c.value)} />
                                    <span>
                                        <span className="block text-sm font-bold text-slate-800">{c.label}{disabled && ' — not in your plan'}</span>
                                        <span className="block text-xs text-slate-500 mt-0.5">{c.desc}</span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                </Card>

                {/* Working days (read-only summary → timetable) */}
                <Card className="p-6">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2 mb-1">
                                <CalendarRange className="w-4 h-4" style={{ color: accent }} />
                                <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>Working Days</p>
                            </div>
                            <p className="text-sm font-bold text-slate-800">{settings.working_days.map(d => DAY_SHORT[d - 1]).join(' · ')}</p>
                            <p className="text-xs text-slate-500 mt-1">Changed on the Timetable page because it affects scheduled lessons.</p>
                        </div>
                        <Link href="/school/timetable" className="px-3.5 py-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 flex-shrink-0">
                            Edit on Timetable
                        </Link>
                    </div>
                </Card>

                {/* Branding + contact */}
                <Card className="p-6 space-y-4">
                    <div className="flex items-center gap-2">
                        <Palette className="w-4 h-4" style={{ color: accent }} />
                        <p className="text-[10px] font-bold uppercase tracking-widest" style={{ color: accent }}>Branding & Contact</p>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-bold text-slate-600 mb-1.5">Accent colour</label>
                            <div className="flex items-center gap-2">
                                <input type="color" value={data.accent_color} onChange={e => setData('accent_color', e.target.value)}
                                    className="w-10 h-10 rounded-xl border border-slate-200 cursor-pointer p-1 bg-white" />
                                <input value={data.accent_color} onChange={e => setData('accent_color', e.target.value)} className={inputCls} />
                            </div>
                            <p className="text-[11px] text-slate-400 mt-1">Used across your panel and on printed ID cards.</p>
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 mb-1.5"><Phone className="w-3 h-3 inline mr-1" />Phone</label>
                            <input value={data.phone} onChange={e => setData('phone', e.target.value)} className={inputCls} placeholder="09…" />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 mb-1.5">Email</label>
                            <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className={inputCls} />
                        </div>
                        <div>
                            <label className="block text-xs font-bold text-slate-600 mb-1.5">Address</label>
                            <input value={data.address} onChange={e => setData('address', e.target.value)} className={inputCls} />
                        </div>
                    </div>
                </Card>

                <button type="submit" disabled={processing}
                    className="px-6 py-3 rounded-2xl text-sm font-bold text-white disabled:opacity-50"
                    style={{ background: accent, boxShadow: `0 4px 14px ${accent}40` }}>
                    Save settings
                </button>
            </form>
        </SchoolLayout>
    );
}
