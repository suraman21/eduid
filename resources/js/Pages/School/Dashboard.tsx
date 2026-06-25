import { Head, Link, usePage } from '@inertiajs/react';
import {
    Users, UserCog, Building2, ClipboardCheck, Package,
    AlertTriangle, XCircle, ArrowRight, ArrowUpRight,
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

/* ── colour helpers ─────────────────────────────────────────── */
function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

/* ── card primitive (Eduplex soft style) ────────────────────── */
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

function StatTile({
    icon: Icon, value, label, color, badge, capacity,
}: {
    icon: React.ElementType;
    value: React.ReactNode;
    label: string;
    color: string;
    badge?: React.ReactNode;
    capacity?: { used: number; max: number };
}) {
    const pct = capacity && capacity.max > 0 ? Math.min(100, (capacity.used / capacity.max) * 100) : 0;
    const barColor = pct > 90 ? '#ef4444' : pct > 70 ? '#f59e0b' : color;

    return (
        <Card className="p-6">
            <div className="flex items-start justify-between mb-5">
                <div
                    className="w-12 h-12 rounded-2xl flex items-center justify-center"
                    style={{ background: alpha(color, 0.12), color }}
                >
                    <Icon className="w-6 h-6" strokeWidth={1.9} />
                </div>
                {badge}
            </div>
            <p className="text-3xl font-extrabold text-slate-900 leading-none tracking-tight" style={{ fontVariantNumeric: 'tabular-nums' }}>
                {value}
            </p>
            <p className="text-sm font-medium text-slate-400 mt-1.5">{label}</p>

            {capacity && (
                <div className="mt-4">
                    <div className="flex justify-between text-[11px] mb-1.5">
                        <span className="text-slate-400 font-medium">Capacity</span>
                        <span className="font-bold text-slate-600">{capacity.used.toLocaleString()} / {capacity.max.toLocaleString()}</span>
                    </div>
                    <div className="w-full rounded-full h-2" style={{ background: alpha(barColor, 0.12) }}>
                        <div className="h-2 rounded-full transition-all duration-700" style={{ width: `${pct}%`, background: barColor }} />
                    </div>
                </div>
            )}
        </Card>
    );
}

export default function Dashboard({ stats, school, recent_students }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.school?.accent_color);
    const user = props.auth?.user;

    const isGrace   = school.subscription_status === 'grace';
    const isExpired = ['expired', 'suspended'].includes(school.subscription_status);

    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    const firstName = (user?.name ?? school.name).split(' ')[0];
    const cap = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

    return (
        <SchoolLayout>
            <Head title="Dashboard" />

            {/* hero */}
            <div className="mb-7">
                <p className="text-sm font-semibold mb-1" style={{ color: accent }}>{greeting} 👋</p>
                <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">Welcome back, {firstName}</h1>
                <p className="text-sm text-slate-400 mt-1">
                    Here's what's happening at {school.name}
                    {school.academic_year ? ` · ${school.academic_year}` : ''}
                </p>
            </div>

            {/* subscription warnings */}
            {isGrace && (
                <Card className="p-4 mb-6 flex items-start gap-3" style={{ borderColor: '#f59e0b55', background: '#fffbeb' }}>
                    <div className="w-9 h-9 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                        <AlertTriangle className="w-4.5 h-4.5 text-amber-500" />
                    </div>
                    <div>
                        <p className="text-sm font-bold text-amber-800">Subscription in Grace Period</p>
                        <p className="text-xs text-amber-600 mt-0.5">New data entry is restricted. Contact Arkeon to renew.</p>
                    </div>
                </Card>
            )}
            {isExpired && (
                <Card className="p-4 mb-6 flex items-start gap-3" style={{ borderColor: '#ef444455', background: '#fef2f2' }}>
                    <div className="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                        <XCircle className="w-4.5 h-4.5 text-red-500" />
                    </div>
                    <div>
                        <p className="text-sm font-bold text-red-800">Subscription Expired</p>
                        <p className="text-xs text-red-600 mt-0.5">Data is read-only. Contact Arkeon to reactivate.</p>
                    </div>
                </Card>
            )}

            {/* primary stat tiles */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mb-5">
                <StatTile
                    icon={Users}
                    value={stats.total_students.toLocaleString()}
                    label="Total Students"
                    color={accent}
                    capacity={{ used: stats.total_students, max: school.max_students }}
                    badge={
                        <span className="text-xs px-2.5 py-1 rounded-full font-bold" style={{ background: '#22c55e18', color: '#16a34a' }}>
                            {stats.active_students} active
                        </span>
                    }
                />
                <StatTile icon={UserCog} value={stats.total_staff} label="Staff Members" color="#3b82f6" />
                <StatTile icon={Building2} value={stats.total_classes} label="Active Classes" color="#8b5cf6" />
            </div>

            {/* secondary + recent grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* left: two mini tiles stacked */}
                <div className="space-y-5">
                    <Card className="p-5 flex items-center gap-4">
                        <div className="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0" style={{ background: '#22c55e16', color: '#16a34a' }}>
                            <ClipboardCheck className="w-6 h-6" strokeWidth={1.9} />
                        </div>
                        <div>
                            <p className="text-2xl font-extrabold text-slate-900 leading-none" style={{ fontVariantNumeric: 'tabular-nums' }}>{stats.today_sessions}</p>
                            <p className="text-xs font-medium text-slate-400 mt-1">Attendance Today</p>
                        </div>
                    </Card>

                    <Link href="/school/card-orders" className="block">
                        <Card className="p-5 flex items-center gap-4 transition-all duration-200 hover:-translate-y-0.5" style={{ cursor: 'pointer' }}>
                            <div className="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0" style={{ background: '#f9731616', color: '#ea580c' }}>
                                <Package className="w-6 h-6" strokeWidth={1.9} />
                            </div>
                            <div className="flex-1">
                                <p className="text-2xl font-extrabold text-slate-900 leading-none" style={{ fontVariantNumeric: 'tabular-nums' }}>{stats.pending_orders}</p>
                                <p className="text-xs font-medium text-slate-400 mt-1">Pending Card Orders</p>
                            </div>
                            <ArrowUpRight className="w-4 h-4 text-slate-300" />
                        </Card>
                    </Link>

                    {/* info card */}
                    <Card className="p-5">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">School Details</p>
                        <div className="space-y-3">
                            {[
                                { label: 'Plan', value: cap(school.plan_tier ?? 'starter') },
                                { label: 'Calendar', value: cap(school.calendar_system ?? 'ethiopian') },
                                {
                                    label: 'Subscription Expires',
                                    value: school.subscription_expires_at
                                        ? new Date(school.subscription_expires_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                                        : 'N/A',
                                },
                            ].map(({ label, value }) => (
                                <div key={label} className="flex items-center justify-between">
                                    <span className="text-xs text-slate-400">{label}</span>
                                    <span className="text-xs font-bold text-slate-700">{value}</span>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>

                {/* right: recent students (span 2) */}
                <div className="lg:col-span-2">
                    <Card className="overflow-hidden h-full">
                        <div className="px-6 py-5 flex items-center justify-between border-b border-slate-100">
                            <div>
                                <h3 className="text-base font-bold text-slate-800">Recently Added Students</h3>
                                <p className="text-xs text-slate-400 mt-0.5">Latest enrollments</p>
                            </div>
                            <Link
                                href="/school/students"
                                className="text-xs font-bold flex items-center gap-1 px-3 py-1.5 rounded-xl transition-all"
                                style={{ color: accent, background: alpha(accent, 0.08) }}
                            >
                                View all <ArrowRight className="w-3 h-3" />
                            </Link>
                        </div>

                        <div className="divide-y divide-slate-50">
                            {recent_students.length === 0 ? (
                                <div className="px-6 py-14 text-center text-sm text-slate-400">No students registered yet</div>
                            ) : recent_students.map(s => (
                                <div key={s.id} className="px-6 py-3.5 flex items-center justify-between hover:bg-slate-50/70 transition-colors">
                                    <div className="flex items-center gap-3">
                                        {s.photo_url ? (
                                            <img src={s.photo_url} className="w-10 h-10 rounded-2xl object-cover" alt="" style={{ border: `2px solid ${alpha(accent, 0.18)}` }} />
                                        ) : (
                                            <div className="w-10 h-10 rounded-2xl flex items-center justify-center text-xs font-extrabold" style={{ background: alpha(accent, 0.10), color: accent }}>
                                                {s.initials}
                                            </div>
                                        )}
                                        <div>
                                            <p className="text-sm font-bold text-slate-800">{s.full_name}</p>
                                            <p className="text-xs font-mono text-slate-400">{s.uid || 'No ID'}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <span className="text-xs text-slate-400 hidden sm:block">{s.created_at_human}</span>
                                        <a
                                            href={`/school/students/${s.id}/id-card`}
                                            className="text-xs font-bold px-3 py-1.5 rounded-xl transition-all"
                                            style={{ color: accent, background: alpha(accent, 0.08) }}
                                        >
                                            ID Card
                                        </a>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>
            </div>
        </SchoolLayout>
    );
}
