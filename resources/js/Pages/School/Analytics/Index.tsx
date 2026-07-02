import React from 'react';
import { Head, usePage } from '@inertiajs/react';
import { TrendingUp, ClipboardCheck, Users, GraduationCap, AlertTriangle } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

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

interface DailyPoint { date: string; rate: number | null; absent: number; total: number }
interface ClassRate { class_id: number; name: string; rate: number | null; total: number }
interface Absentee { student_id: number; name: string; class: string; absences: number }

interface Props {
    window_days: number;
    summary: { attendance_rate: number | null; sessions: number; active_students: number; classes_tracked: number };
    daily: DailyPoint[];
    by_class: ClassRate[];
    top_absent: Absentee[];
    enrolment: {
        by_class: { name: string; count: number }[];
        unassigned: number;
        male: number;
        female: number;
    };
}

function rateColor(rate: number | null): string {
    if (rate === null) return '#94a3b8';
    if (rate >= 95) return '#059669';
    if (rate >= 85) return '#0284c7';
    if (rate >= 70) return '#d97706';
    return '#dc2626';
}

/** Inline SVG area chart of the daily attendance rate (0–100%). */
function RateChart({ points, accent }: { points: DailyPoint[]; accent: string }) {
    const W = 720, H = 180, PAD = 8;
    const plotted = points.filter(p => p.rate !== null);
    if (plotted.length === 0) {
        return (
            <div className="h-[180px] flex items-center justify-center text-sm text-slate-400">
                No attendance recorded in this window yet.
            </div>
        );
    }
    const x = (i: number) => PAD + (i / Math.max(points.length - 1, 1)) * (W - PAD * 2);
    const y = (rate: number) => H - PAD - (rate / 100) * (H - PAD * 2);

    const coords = points
        .map((p, i) => (p.rate === null ? null : `${x(i)},${y(p.rate)}`))
        .filter(Boolean) as string[];

    const firstX = x(points.findIndex(p => p.rate !== null));
    const lastX = x(points.length - 1 - [...points].reverse().findIndex(p => p.rate !== null));
    const area = `${firstX},${H - PAD} ${coords.join(' ')} ${lastX},${H - PAD}`;

    return (
        <svg viewBox={`0 0 ${W} ${H}`} className="w-full" role="img" aria-label="Daily attendance rate">
            {[25, 50, 75, 100].map(g => (
                <g key={g}>
                    <line x1={PAD} x2={W - PAD} y1={y(g)} y2={y(g)} stroke="#f1f5f9" strokeWidth={1} />
                    <text x={PAD} y={y(g) - 3} fontSize={9} fill="#cbd5e1">{g}%</text>
                </g>
            ))}
            <polygon points={area} fill={alpha(accent, 0.10)} />
            <polyline points={coords.join(' ')} fill="none" stroke={accent} strokeWidth={2.5} strokeLinejoin="round" strokeLinecap="round" />
            {points.map((p, i) => p.rate === null ? null : (
                <circle key={i} cx={x(i)} cy={y(p.rate)} r={2.5} fill={accent}>
                    <title>{p.date}: {p.rate}% ({p.absent} absent of {p.total})</title>
                </circle>
            ))}
        </svg>
    );
}

/** Horizontal bar with label + % chip. */
function Bar({ label, value, max, color, suffix }: { label: string; value: number; max: number; color: string; suffix: string }) {
    const width = max > 0 ? Math.max((value / max) * 100, 2) : 0;
    return (
        <div className="flex items-center gap-3 py-1.5">
            <span className="w-28 flex-shrink-0 text-xs font-semibold text-slate-600 truncate">{label}</span>
            <div className="flex-1 h-3 rounded-full bg-slate-100 overflow-hidden">
                <div className="h-full rounded-full" style={{ width: `${width}%`, background: color }} />
            </div>
            <span className="w-14 text-right text-xs font-bold" style={{ color }}>{value}{suffix}</span>
        </div>
    );
}

export default function Index({ window_days, summary, daily, by_class, top_absent, enrolment }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const statCards = [
        { label: 'Attendance rate', value: summary.attendance_rate !== null ? `${summary.attendance_rate}%` : '—', icon: TrendingUp, color: rateColor(summary.attendance_rate) },
        { label: 'Sessions recorded', value: summary.sessions, icon: ClipboardCheck, color: accent },
        { label: 'Active students', value: summary.active_students, icon: Users, color: '#0284c7' },
        { label: 'Classes tracked', value: summary.classes_tracked, icon: GraduationCap, color: '#7c3aed' },
    ];

    const maxEnrol = Math.max(...enrolment.by_class.map(c => c.count), 1);
    const totalGender = enrolment.male + enrolment.female;

    return (
        <SchoolLayout>
            <Head title="Analytics" />

            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900">Analytics</h1>
                <p className="text-sm text-slate-500 mt-1">Attendance and enrolment over the last {window_days} days.</p>
            </div>

            {/* Headline stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {statCards.map(({ label, value, icon: Icon, color }) => (
                    <Card key={label} className="p-5">
                        <div className="flex items-center gap-3.5">
                            <span className="flex items-center justify-center w-11 h-11 rounded-2xl flex-shrink-0"
                                style={{ background: alpha(color, 0.10), color }}>
                                <Icon className="w-5 h-5" />
                            </span>
                            <div className="min-w-0">
                                <p className="text-xl font-extrabold text-slate-900 leading-none">{value}</p>
                                <p className="text-[11px] font-bold uppercase tracking-wider text-slate-400 mt-1.5">{label}</p>
                            </div>
                        </div>
                    </Card>
                ))}
            </div>

            {/* Daily rate chart */}
            <Card className="p-6 mb-6">
                <p className="text-[10px] font-bold uppercase tracking-widest mb-4" style={{ color: accent }}>
                    Daily attendance rate
                </p>
                <RateChart points={daily} accent={accent} />
            </Card>

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-6">
                {/* Per-class rates */}
                <Card className="p-6">
                    <p className="text-[10px] font-bold uppercase tracking-widest mb-4" style={{ color: accent }}>
                        Attendance by class <span className="text-slate-300 normal-case tracking-normal">(lowest first)</span>
                    </p>
                    {by_class.length === 0 ? (
                        <p className="text-sm text-slate-400 py-4">No class sessions recorded yet.</p>
                    ) : by_class.map(c => (
                        <Bar key={c.class_id} label={c.name} value={c.rate ?? 0} max={100}
                            color={rateColor(c.rate)} suffix="%" />
                    ))}
                </Card>

                {/* Absence hotspots */}
                <Card className="p-6">
                    <p className="text-[10px] font-bold uppercase tracking-widest mb-4 flex items-center gap-1.5 text-amber-600">
                        <AlertTriangle className="w-3.5 h-3.5" /> Most absences ({window_days} days)
                    </p>
                    {top_absent.length === 0 ? (
                        <p className="text-sm text-slate-400 py-4">No absences recorded — excellent!</p>
                    ) : (
                        <div className="space-y-1">
                            {top_absent.map((s, i) => (
                                <div key={s.student_id} className="flex items-center gap-3 py-2 border-b border-slate-50 last:border-0">
                                    <span className="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 text-[11px] font-extrabold flex items-center justify-center flex-shrink-0">
                                        {i + 1}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-semibold text-slate-800 truncate">{s.name}</p>
                                        <p className="text-[11px] text-slate-400">{s.class}</p>
                                    </div>
                                    <span className="text-sm font-extrabold text-red-500">{s.absences}</span>
                                    <span className="text-[10px] text-slate-400">absences</span>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                {/* Enrolment by class */}
                <Card className="p-6">
                    <p className="text-[10px] font-bold uppercase tracking-widest mb-4" style={{ color: accent }}>
                        Enrolment by class
                    </p>
                    {enrolment.by_class.map(c => (
                        <Bar key={c.name} label={c.name} value={c.count} max={maxEnrol} color={accent} suffix="" />
                    ))}
                    {enrolment.unassigned > 0 && (
                        <Bar label="Unassigned" value={enrolment.unassigned} max={maxEnrol} color="#94a3b8" suffix="" />
                    )}
                </Card>

                {/* Gender split */}
                <Card className="p-6">
                    <p className="text-[10px] font-bold uppercase tracking-widest mb-4" style={{ color: accent }}>
                        Gender split (active students)
                    </p>
                    {totalGender === 0 ? (
                        <p className="text-sm text-slate-400 py-4">No active students yet.</p>
                    ) : (
                        <>
                            <div className="flex h-5 rounded-full overflow-hidden mb-4">
                                <div style={{ width: `${(enrolment.male / totalGender) * 100}%`, background: '#0284c7' }} />
                                <div style={{ width: `${(enrolment.female / totalGender) * 100}%`, background: '#db2777' }} />
                            </div>
                            <div className="flex items-center gap-6">
                                <p className="text-sm text-slate-600">
                                    <span className="inline-block w-2.5 h-2.5 rounded-full mr-1.5" style={{ background: '#0284c7' }} />
                                    <b>{enrolment.male}</b> male ({Math.round((enrolment.male / totalGender) * 100)}%)
                                </p>
                                <p className="text-sm text-slate-600">
                                    <span className="inline-block w-2.5 h-2.5 rounded-full mr-1.5" style={{ background: '#db2777' }} />
                                    <b>{enrolment.female}</b> female ({Math.round((enrolment.female / totalGender) * 100)}%)
                                </p>
                            </div>
                        </>
                    )}
                </Card>
            </div>
        </SchoolLayout>
    );
}
