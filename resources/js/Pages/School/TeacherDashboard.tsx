import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ClipboardCheck, FileBarChart, Users, GraduationCap, Home,
    CheckCircle2, Clock, CalendarDays,
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

interface MyClass {
    id: number;
    name: string;
    student_count: number;
    is_homeroom: boolean;
    subjects: string[];
    attendance_taken_today: boolean;
}

interface Period {
    label: string | null;
    start_time: string | null;
    end_time: string | null;
    class_name: string | null;
    subject: string | null;
    room: string | null;
}

interface Props {
    school: { name: string; academic_year: string; calendar_system: string };
    my_classes: MyClass[];
    today_periods: Period[];
    stats: { classes: number; students: number; taken_today: number; homeroom_classes: number };
}

export default function TeacherDashboard({ school, my_classes, today_periods, stats }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const user = props.auth?.user;

    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    const firstName = (user?.name ?? '').split(' ')[0];

    const statCards = [
        { label: 'My Classes', value: stats.classes, icon: GraduationCap, color: accent },
        { label: 'My Students', value: stats.students, icon: Users, color: '#0284c7' },
        { label: 'Attendance Today', value: `${stats.taken_today}/${stats.classes}`, icon: ClipboardCheck, color: stats.taken_today >= stats.classes && stats.classes > 0 ? '#059669' : '#d97706' },
        { label: 'Homeroom Of', value: stats.homeroom_classes, icon: Home, color: '#7c3aed' },
    ];

    return (
        <SchoolLayout>
            <Head title="My Dashboard" />

            {/* Greeting */}
            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900">
                    {greeting}{firstName ? `, ${firstName}` : ''} 👋
                </h1>
                <p className="text-sm text-slate-500 mt-1">
                    {school.name} · {school.academic_year} · {new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })}
                </p>
            </div>

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {statCards.map(({ label, value, icon: Icon, color }) => (
                    <Card key={label} className="p-5">
                        <div className="flex items-center gap-3.5">
                            <span
                                className="flex items-center justify-center w-11 h-11 rounded-2xl flex-shrink-0"
                                style={{ background: alpha(color, 0.10), color }}
                            >
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

            <div className="grid grid-cols-1 xl:grid-cols-3 gap-5">
                {/* My classes */}
                <div className="xl:col-span-2 space-y-4">
                    <div className="flex items-center justify-between px-1">
                        <h2 className="text-xs font-bold uppercase tracking-widest text-slate-400">My Classes</h2>
                    </div>

                    {my_classes.length === 0 && (
                        <Card className="p-8 text-center">
                            <p className="text-sm text-slate-500">
                                You have no class assignments yet.<br />
                                Ask your school administrator to assign your classes and subjects.
                            </p>
                        </Card>
                    )}

                    {my_classes.map(c => (
                        <Card key={c.id} className="p-5">
                            <div className="flex items-center gap-4 flex-wrap">
                                <div
                                    className="w-12 h-12 rounded-2xl flex items-center justify-center text-base font-extrabold flex-shrink-0"
                                    style={{ background: alpha(accent, 0.10), color: accent }}
                                >
                                    {c.name.replace(/[^0-9A-Za-z]/g, '').slice(0, 2).toUpperCase() || '•'}
                                </div>
                                <div className="flex-1 min-w-[180px]">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <p className="font-extrabold text-slate-900">{c.name}</p>
                                        {c.is_homeroom && (
                                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">
                                                <Home className="w-3 h-3" /> Homeroom
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-xs text-slate-500 mt-0.5">
                                        {c.student_count} students
                                        {c.subjects.length > 0 && <> · {c.subjects.join(', ')}</>}
                                    </p>
                                </div>

                                {/* attendance status */}
                                {c.attendance_taken_today ? (
                                    <span className="inline-flex items-center gap-1.5 text-xs font-bold text-green-600">
                                        <CheckCircle2 className="w-4 h-4" /> Attendance taken
                                    </span>
                                ) : (
                                    <span className="inline-flex items-center gap-1.5 text-xs font-bold text-amber-600">
                                        <Clock className="w-4 h-4" /> Not taken today
                                    </span>
                                )}

                                <div className="flex items-center gap-2">
                                    <Link
                                        href={`/school/attendance/create?class_id=${c.id}`}
                                        className="px-4 py-2 rounded-xl text-xs font-bold text-white transition-all"
                                        style={{ background: accent, boxShadow: `0 4px 12px ${alpha(accent, 0.3)}` }}
                                    >
                                        Take attendance
                                    </Link>
                                    <Link
                                        href="/school/grades"
                                        className="px-4 py-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all"
                                    >
                                        Grades
                                    </Link>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>

                {/* Today's schedule */}
                <div className="space-y-4">
                    <h2 className="text-xs font-bold uppercase tracking-widest text-slate-400 px-1">Today's Schedule</h2>
                    <Card className="p-5">
                        {today_periods.length === 0 ? (
                            <div className="text-center py-6">
                                <CalendarDays className="w-8 h-8 text-slate-200 mx-auto mb-3" />
                                <p className="text-sm text-slate-400">
                                    No periods scheduled today.<br />
                                    <span className="text-xs">The timetable appears here once your school sets it up.</span>
                                </p>
                            </div>
                        ) : (
                            <div className="space-y-1">
                                {today_periods.map((p, i) => (
                                    <div key={i} className="flex items-center gap-3 py-2.5 border-b border-slate-50 last:border-0">
                                        <div className="w-14 flex-shrink-0 text-[11px] font-bold text-slate-400 leading-tight">
                                            {p.start_time}<br />{p.end_time}
                                        </div>
                                        <div
                                            className="w-1 self-stretch rounded-full flex-shrink-0"
                                            style={{ background: alpha(accent, 0.35) }}
                                        />
                                        <div className="min-w-0">
                                            <p className="text-sm font-bold text-slate-800 truncate">
                                                {p.subject ?? p.label ?? 'Period'}
                                                {p.class_name && <span className="font-semibold text-slate-500"> · {p.class_name}</span>}
                                            </p>
                                            {p.room && <p className="text-[11px] text-slate-400">Room {p.room}</p>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>

                    {/* Quick links */}
                    <Card className="p-5">
                        <p className="text-[10px] font-bold uppercase tracking-widest mb-3" style={{ color: accent }}>Quick Actions</p>
                        <div className="space-y-2">
                            <Link
                                href="/school/attendance"
                                className="flex items-center gap-3 px-3.5 py-3 rounded-2xl text-sm font-semibold text-slate-700 hover:bg-slate-50 border border-slate-100 transition"
                            >
                                <ClipboardCheck className="w-4 h-4" style={{ color: accent }} /> Attendance history
                            </Link>
                            <Link
                                href="/school/grades"
                                className="flex items-center gap-3 px-3.5 py-3 rounded-2xl text-sm font-semibold text-slate-700 hover:bg-slate-50 border border-slate-100 transition"
                            >
                                <FileBarChart className="w-4 h-4" style={{ color: accent }} /> Gradebook
                            </Link>
                        </div>
                    </Card>
                </div>
            </div>
        </SchoolLayout>
    );
}
