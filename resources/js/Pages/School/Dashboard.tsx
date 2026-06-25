import { Head, Link } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';
import {
    Users, UserCog, Building2, ClipboardCheck, Package,
    AlertTriangle, XCircle, ArrowRight, TrendingUp, Star,
} from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

interface Student {
    id: number;
    full_name: string;
    uid: string;
    photo_url: string | null;
    initials: string;
    created_at_human: string;
}

interface Stats {
    total_students: number;
    active_students: number;
    total_staff: number;
    total_classes: number;
    pending_orders: number;
    today_sessions: number;
}

interface School {
    name: string;
    academic_year: string | null;
    plan_tier: string;
    calendar_system: string;
    subscription_status: string;
    subscription_expires_at: string | null;
    max_students: number;
}

interface Props {
    stats: Stats;
    school: School;
    recent_students: Student[];
}

/* ─── helpers ─────────────────────────────────────────────────── */

function hex(color: string, alpha: number) {
    const a = Math.round(alpha * 255).toString(16).padStart(2, '0');
    return `${color}${a}`;
}

function CapacityBar({ used, max, accent }: { used: number; max: number; accent: string }) {
    const pct = max > 0 ? Math.min(100, (used / max) * 100) : 0;
    const barColor = pct > 90 ? '#ef4444' : pct > 70 ? '#f59e0b' : accent;
    return (
        <div className="mt-4">
            <div className="flex justify-between text-xs mb-2">
                <span className="text-slate-400 font-medium tracking-wide uppercase" style={{ fontSize: 10 }}>
                    Student Capacity
                </span>
                <span className="font-bold text-slate-700">{used.toLocaleString()} / {max.toLocaleString()}</span>
            </div>
            <div className="w-full rounded-full h-2" style={{ background: hex(barColor, 0.12) }}>
                <div
                    className="h-2 rounded-full transition-all duration-700"
                    style={{
                        width: `${pct}%`,
                        background: barColor,
                        boxShadow: `0 0 8px ${hex(barColor, 0.5)}`,
                    }}
                />
            </div>
            <p className="text-right text-xs mt-1" style={{ color: barColor, fontVariantNumeric: 'tabular-nums' }}>
                {pct.toFixed(1)}% used
            </p>
        </div>
    );
}

function StatCard({
    icon: Icon, value, label, accent, badge, extra, href,
}: {
    icon: React.ElementType;
    value: React.ReactNode;
    label: string;
    accent: string;
    badge?: React.ReactNode;
    extra?: React.ReactNode;
    href?: string;
}) {
    const card = (
        <div
            className="bg-white rounded-2xl p-6 transition-all duration-200 cursor-default group relative overflow-hidden"
            style={{
                border: `1.5px solid ${hex(accent, 0.22)}`,
                boxShadow: `0 0 0 0 ${hex(accent, 0)}, 0 4px 24px ${hex(accent, 0.10)}, 0 1px 4px rgba(0,0,0,0.05)`,
            }}
            onMouseEnter={e => {
                (e.currentTarget as HTMLDivElement).style.boxShadow =
                    `0 0 0 3px ${hex(accent, 0.14)}, 0 8px 32px ${hex(accent, 0.18)}, 0 2px 8px rgba(0,0,0,0.07)`;
                (e.currentTarget as HTMLDivElement).style.borderColor = hex(accent, 0.45);
            }}
            onMouseLeave={e => {
                (e.currentTarget as HTMLDivElement).style.boxShadow =
                    `0 0 0 0 ${hex(accent, 0)}, 0 4px 24px ${hex(accent, 0.10)}, 0 1px 4px rgba(0,0,0,0.05)`;
                (e.currentTarget as HTMLDivElement).style.borderColor = hex(accent, 0.22);
            }}
        >
            {/* top accent stripe */}
            <div className="absolute top-0 left-0 right-0 h-[3px] rounded-t-2xl" style={{ background: accent }} />

            {/* subtle bg glow */}
            <div
                className="absolute -top-8 -right-8 w-32 h-32 rounded-full blur-3xl pointer-events-none"
                style={{ background: hex(accent, 0.07) }}
            />

            <div className="flex items-start justify-between mb-4 relative">
                <div
                    className="w-12 h-12 rounded-xl flex items-center justify-center"
                    style={{
                        background: hex(accent, 0.10),
                        boxShadow: `0 0 16px ${hex(accent, 0.20)}`,
                        border: `1px solid ${hex(accent, 0.18)}`,
                    }}
                >
                    <Icon className="w-6 h-6" style={{ color: accent }} strokeWidth={1.75} />
                </div>
                {badge}
            </div>

            <p className="text-4xl font-extrabold text-slate-900 leading-none tracking-tight relative"
               style={{ fontVariantNumeric: 'tabular-nums' }}>
                {value}
            </p>
            <p className="text-sm font-semibold text-slate-500 mt-2 relative">{label}</p>
            {extra && <div className="relative">{extra}</div>}
        </div>
    );

    return href ? <a href={href}>{card}</a> : card;
}

function MiniCard({
    icon: Icon, value, label, accent, color, href,
}: {
    icon: React.ElementType;
    value: React.ReactNode;
    label: string;
    accent: string;
    color: string;
    href?: string;
}) {
    const content = (
        <div
            className="bg-white rounded-2xl p-5 flex items-center gap-4 transition-all duration-200"
            style={{
                border: `1.5px solid ${hex(color, 0.20)}`,
                boxShadow: `0 4px 20px ${hex(color, 0.08)}, 0 1px 3px rgba(0,0,0,0.04)`,
            }}
            onMouseEnter={e => {
                (e.currentTarget as HTMLDivElement).style.boxShadow =
                    `0 0 0 3px ${hex(color, 0.12)}, 0 8px 28px ${hex(color, 0.16)}`;
                (e.currentTarget as HTMLDivElement).style.borderColor = hex(color, 0.40);
            }}
            onMouseLeave={e => {
                (e.currentTarget as HTMLDivElement).style.boxShadow =
                    `0 4px 20px ${hex(color, 0.08)}, 0 1px 3px rgba(0,0,0,0.04)`;
                (e.currentTarget as HTMLDivElement).style.borderColor = hex(color, 0.20);
            }}
        >
            <div
                className="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0"
                style={{
                    background: hex(color, 0.10),
                    border: `1px solid ${hex(color, 0.16)}`,
                    boxShadow: `0 0 14px ${hex(color, 0.18)}`,
                }}
            >
                <Icon className="w-6 h-6" style={{ color }} strokeWidth={1.75} />
            </div>
            <div>
                <p className="text-3xl font-extrabold text-slate-900 leading-none"
                   style={{ fontVariantNumeric: 'tabular-nums' }}>
                    {value}
                </p>
                <p className="text-xs font-semibold text-slate-500 mt-1">{label}</p>
            </div>
            {href && (
                <div className="ml-auto">
                    <ArrowRight className="w-4 h-4" style={{ color }} />
                </div>
            )}
        </div>
    );

    return href ? <a href={href} className="block">{content}</a> : content;
}

/* ─── main component ──────────────────────────────────────────── */

export default function Dashboard({ stats, school, recent_students }: Props) {
    const { props } = usePage<PageProps>();
    const accent: string = (props.school as any)?.accent_color || '#6366f1';

    const isGrace   = school.subscription_status === 'grace';
    const isExpired = ['expired', 'suspended'].includes(school.subscription_status);

    const greetingHour = new Date().getHours();
    const greeting = greetingHour < 12 ? 'Good morning' : greetingHour < 17 ? 'Good afternoon' : 'Good evening';

    const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

    return (
        <SchoolLayout>
            <Head title="Dashboard" />

            {/* ── Page header ──────────────────────────── */}
            <div className="flex items-start justify-between mb-8">
                <div>
                    <div className="flex items-center gap-2 mb-1">
                        <Star className="w-4 h-4" style={{ color: accent }} />
                        <span className="text-xs font-semibold uppercase tracking-widest" style={{ color: accent }}>
                            {greeting}
                        </span>
                    </div>
                    <h1 className="text-2xl font-extrabold text-slate-900 leading-tight">
                        {school.name}
                    </h1>
                    {school.academic_year && (
                        <p className="text-sm text-slate-400 mt-0.5">Academic Year: {school.academic_year}</p>
                    )}
                </div>
                <div
                    className="hidden sm:flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-wider"
                    style={{
                        background: hex(accent, 0.08),
                        color: accent,
                        border: `1.5px solid ${hex(accent, 0.22)}`,
                        boxShadow: `0 0 12px ${hex(accent, 0.12)}`,
                    }}
                >
                    <TrendingUp className="w-3.5 h-3.5" />
                    {capitalize(school.plan_tier)} Plan
                </div>
            </div>

            {/* ── Subscription warnings ─────────────────── */}
            {isGrace && (
                <div className="mb-6 p-4 rounded-2xl bg-amber-50 flex items-start gap-3"
                     style={{ border: '1.5px solid #f59e0b40', boxShadow: '0 4px 20px #f59e0b18' }}>
                    <div className="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0"
                         style={{ boxShadow: '0 0 12px #f59e0b30' }}>
                        <AlertTriangle className="w-4.5 h-4.5 text-amber-500" />
                    </div>
                    <div>
                        <p className="text-sm font-bold text-amber-800">Subscription in Grace Period</p>
                        <p className="text-xs text-amber-600 mt-0.5">New data entry is restricted. Contact Arkeon to renew.</p>
                    </div>
                </div>
            )}
            {isExpired && (
                <div className="mb-6 p-4 rounded-2xl bg-red-50 flex items-start gap-3"
                     style={{ border: '1.5px solid #ef444440', boxShadow: '0 4px 20px #ef444418' }}>
                    <div className="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0"
                         style={{ boxShadow: '0 0 12px #ef444430' }}>
                        <XCircle className="w-4.5 h-4.5 text-red-500" />
                    </div>
                    <div>
                        <p className="text-sm font-bold text-red-800">Subscription Expired</p>
                        <p className="text-xs text-red-600 mt-0.5">Your subscription has expired. Data is read-only. Contact Arkeon to reactivate.</p>
                    </div>
                </div>
            )}

            {/* ── Primary stat cards ───────────────────── */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-6">
                <StatCard
                    icon={Users}
                    value={stats.total_students.toLocaleString()}
                    label="Total Students"
                    accent={accent}
                    badge={
                        <span
                            className="text-xs px-2.5 py-1 rounded-full font-bold"
                            style={{
                                background: '#22c55e18',
                                color: '#16a34a',
                                border: '1px solid #22c55e28',
                            }}
                        >
                            {stats.active_students} active
                        </span>
                    }
                    extra={
                        <CapacityBar
                            used={stats.total_students}
                            max={school.max_students}
                            accent={accent}
                        />
                    }
                />
                <StatCard
                    icon={UserCog}
                    value={stats.total_staff}
                    label="Staff Members"
                    accent="#3b82f6"
                />
                <StatCard
                    icon={Building2}
                    value={stats.total_classes}
                    label="Active Classes"
                    accent="#8b5cf6"
                />
            </div>

            {/* ── Secondary stats ──────────────────────── */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
                <MiniCard
                    icon={ClipboardCheck}
                    value={stats.today_sessions}
                    label="Attendance Sessions Today"
                    accent={accent}
                    color="#22c55e"
                />
                <MiniCard
                    icon={Package}
                    value={stats.pending_orders}
                    label="Pending Card Orders"
                    accent={accent}
                    color="#f97316"
                    href="/school/card-orders"
                />
            </div>

            {/* ── Recent students ──────────────────────── */}
            <div
                className="bg-white rounded-2xl overflow-hidden mb-6"
                style={{
                    border: `1.5px solid ${hex(accent, 0.16)}`,
                    boxShadow: `0 4px 24px ${hex(accent, 0.08)}, 0 1px 4px rgba(0,0,0,0.04)`,
                }}
            >
                <div
                    className="px-6 py-4 flex items-center justify-between"
                    style={{
                        borderBottom: `1px solid ${hex(accent, 0.12)}`,
                        background: hex(accent, 0.03),
                    }}
                >
                    <h3 className="text-sm font-bold text-slate-800">Recently Added Students</h3>
                    <Link
                        href="/school/students"
                        className="text-xs font-semibold flex items-center gap-1 transition-opacity hover:opacity-70"
                        style={{ color: accent }}
                    >
                        View all <ArrowRight className="w-3 h-3" />
                    </Link>
                </div>

                <div className="divide-y" style={{ borderColor: hex(accent, 0.07) }}>
                    {recent_students.length === 0 ? (
                        <div className="px-6 py-10 text-center text-sm text-slate-400">
                            No students registered yet
                        </div>
                    ) : recent_students.map(s => (
                        <div key={s.id} className="px-6 py-3.5 flex items-center justify-between hover:bg-slate-50/60 transition-colors">
                            <div className="flex items-center gap-3">
                                {s.photo_url ? (
                                    <img
                                        src={s.photo_url}
                                        className="w-9 h-9 rounded-full object-cover"
                                        style={{
                                            border: `2px solid ${hex(accent, 0.25)}`,
                                            boxShadow: `0 0 8px ${hex(accent, 0.18)}`,
                                        }}
                                        alt=""
                                    />
                                ) : (
                                    <div
                                        className="w-9 h-9 rounded-full flex items-center justify-center text-xs font-extrabold"
                                        style={{
                                            background: hex(accent, 0.10),
                                            color: accent,
                                            border: `2px solid ${hex(accent, 0.22)}`,
                                            boxShadow: `0 0 8px ${hex(accent, 0.18)}`,
                                        }}
                                    >
                                        {s.initials}
                                    </div>
                                )}
                                <div>
                                    <p className="text-sm font-semibold text-slate-800">{s.full_name}</p>
                                    <p className="text-xs font-mono text-slate-400">{s.uid || 'No ID'}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-4">
                                <span className="text-xs text-slate-400 hidden sm:block">{s.created_at_human}</span>
                                <a
                                    href={`/school/students/${s.id}/id-card`}
                                    className="text-xs font-bold px-3 py-1 rounded-lg transition-all"
                                    style={{
                                        color: accent,
                                        background: hex(accent, 0.08),
                                        border: `1px solid ${hex(accent, 0.20)}`,
                                    }}
                                    onMouseEnter={e => {
                                        (e.currentTarget as HTMLAnchorElement).style.background = hex(accent, 0.14);
                                        (e.currentTarget as HTMLAnchorElement).style.boxShadow = `0 0 10px ${hex(accent, 0.20)}`;
                                    }}
                                    onMouseLeave={e => {
                                        (e.currentTarget as HTMLAnchorElement).style.background = hex(accent, 0.08);
                                        (e.currentTarget as HTMLAnchorElement).style.boxShadow = 'none';
                                    }}
                                >
                                    ID Card
                                </a>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* ── School info strip ────────────────────── */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                {[
                    {
                        label: 'Plan',
                        value: capitalize(school.plan_tier ?? 'starter'),
                        icon: '✦',
                    },
                    {
                        label: 'Calendar System',
                        value: capitalize(school.calendar_system ?? 'ethiopian'),
                        icon: '◈',
                    },
                    {
                        label: 'Subscription Expires',
                        value: school.subscription_expires_at
                            ? new Date(school.subscription_expires_at).toLocaleDateString('en-US', {
                                  month: 'short', day: 'numeric', year: 'numeric',
                              })
                            : 'N/A',
                        icon: '◉',
                    },
                ].map(({ label, value, icon }) => (
                    <div
                        key={label}
                        className="bg-white rounded-xl px-5 py-4"
                        style={{
                            border: `1.5px solid ${hex(accent, 0.14)}`,
                            boxShadow: `0 2px 12px ${hex(accent, 0.06)}`,
                        }}
                    >
                        <div className="flex items-center gap-1.5 mb-1.5">
                            <span className="text-[10px]" style={{ color: accent }}>{icon}</span>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">{label}</p>
                        </div>
                        <p className="text-sm font-bold text-slate-800">{value}</p>
                    </div>
                ))}
            </div>
        </SchoolLayout>
    );
}
