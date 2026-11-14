import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { TrendingUp, Users, GraduationCap, AlertTriangle, Download, ArrowUpRight, ArrowDownRight, Minus } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { normHex } from '@/Components/CertificateLetter';

interface YearRow {
    academic_year: string;
    students: number;
    entries: number;
    exits: number;
    change: number | null;
    change_pct: number | null;
}

interface Props {
    enrolment: {
        years: YearRow[];
        gender: { male: number; female: number; unspecified: number };
        by_class: { class_id: number; label: string; academic_year: string; students: number; capacity: number | null; fill_pct: number | null }[];
        current_total: number;
        small_base: number;
    };
    retention: {
        cohorts: {
            academic_year: string; joined: number; still_enrolled: number; graduated: number;
            transferred: number; withdrawn: number; other: number; leavers: number;
            retention_pct: number | null; small_base: boolean;
        }[];
        exit_reasons: { reason: string; total: number }[];
    };
    academic: {
        subjects: {
            subject_id: number; subject: string; academic_year: string; marked: number;
            average: number | null; highest: number | null; lowest: number | null;
            pass_rate: number | null; bands: Record<string, number>;
        }[];
        distribution: { label: string; range: string; count: number }[];
        years: string[];
        academic_year: string | null;
        pass_mark: number;
        total_marked: number;
        overall_average: number | null;
    };
    risk: {
        students: {
            id: number; name: string; uid: string; class: string | null;
            attendance: number | null; attendance_base: number; absences: number;
            average: number | null; failing: number; subjects: { subject: string; total: number }[];
            reasons: string[]; severity: 'high' | 'medium';
        }[];
        attendance_risk: number; academic_risk: number; both: number; checked: number;
    };
    filters: { year: string | null };
    currency_year: string;
    is_admin: boolean;
}

type Tab = 'enrolment' | 'retention' | 'results' | 'risk';

export default function Index({ enrolment, retention, academic, risk, filters, currency_year }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const [tab, setTab] = useState<Tab>('enrolment');

    const TABS: { key: Tab; label: string; icon: React.ElementType; count?: number }[] = [
        { key: 'enrolment', label: 'Enrolment', icon: Users },
        { key: 'retention', label: 'Retention', icon: GraduationCap },
        { key: 'results',   label: 'Results',   icon: TrendingUp },
        { key: 'risk',      label: 'At risk',   icon: AlertTriangle, count: risk.students.length },
    ];

    const exportHref = (name: Tab) =>
        `/school/reports/${name}/export${name === 'results' && filters.year ? `?year=${encodeURIComponent(filters.year)}` : ''}`;

    const card = 'bg-white rounded-3xl border border-slate-200 p-6';

    return (
        <SchoolLayout>
            <Head title="Reports" />

            <div className="flex items-start justify-between mb-6 flex-wrap gap-3">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Reports</h1>
                    <p className="text-sm text-slate-500 mt-0.5">
                        Built from the enrolment, results and attendance records — nothing typed twice.
                    </p>
                </div>
                <a href={exportHref(tab)} className="flex items-center gap-2 text-xs font-bold px-4 py-2.5 rounded-2xl text-slate-600 bg-slate-100 hover:bg-slate-200">
                    <Download className="w-3.5 h-3.5" /> Export CSV
                </a>
            </div>

            {/* Tabs */}
            <div className="flex gap-1.5 mb-6 overflow-x-auto pb-1">
                {TABS.map(t => {
                    const Icon = t.icon;
                    const active = tab === t.key;
                    return (
                        <button key={t.key} onClick={() => setTab(t.key)}
                            className={`flex items-center gap-2 px-4 py-2 rounded-2xl text-sm font-bold whitespace-nowrap transition ${active ? 'text-white' : 'text-slate-600 bg-slate-100 hover:bg-slate-200'}`}
                            style={active ? { background: accent } : undefined}>
                            <Icon className="w-4 h-4" />
                            {t.label}
                            {t.count ? (
                                <span className={`text-[10px] px-1.5 py-0.5 rounded-full ${active ? 'bg-white/25' : 'bg-white text-slate-500'}`}>
                                    {t.count}
                                </span>
                            ) : null}
                        </button>
                    );
                })}
            </div>

            {/* ── ENROLMENT ── */}
            {tab === 'enrolment' && (
                <div className="space-y-5">
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Enrolled now</p>
                            <p className="text-3xl font-extrabold text-slate-900 mt-1">{enrolment.current_total}</p>
                        </div>
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Girls</p>
                            <p className="text-3xl font-extrabold text-slate-900 mt-1">{enrolment.gender.female}</p>
                        </div>
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Boys</p>
                            <p className="text-3xl font-extrabold text-slate-900 mt-1">{enrolment.gender.male}</p>
                        </div>
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Academic year</p>
                            <p className="text-lg font-extrabold text-slate-900 mt-2">{currency_year || '—'}</p>
                        </div>
                    </div>

                    <div className={card}>
                        <h2 className="text-sm font-bold text-slate-800 mb-1">Headcount by academic year</h2>
                        <p className="text-[11px] text-slate-400 mb-4">Entries and exits are counted from the enrolment record.</p>
                        {enrolment.years.length === 0 ? (
                            <p className="text-xs text-slate-400 py-6 text-center">No enrolment history yet.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-100">
                                        <th className="text-left py-2">Year</th>
                                        <th className="text-right py-2">Students</th>
                                        <th className="text-right py-2">Entries</th>
                                        <th className="text-right py-2">Exits</th>
                                        <th className="text-right py-2">Change</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {enrolment.years.map(y => (
                                        <tr key={y.academic_year}>
                                            <td className="py-2.5 font-semibold text-slate-800">{y.academic_year}</td>
                                            <td className="py-2.5 text-right font-bold text-slate-900">{y.students}</td>
                                            <td className="py-2.5 text-right text-slate-500">{y.entries}</td>
                                            <td className="py-2.5 text-right text-slate-500">{y.exits}</td>
                                            <td className="py-2.5 text-right">
                                                {y.change === null ? <span className="text-slate-300">—</span> : (
                                                    <span className={`inline-flex items-center gap-1 font-bold ${y.change > 0 ? 'text-green-600' : y.change < 0 ? 'text-red-600' : 'text-slate-400'}`}>
                                                        {y.change > 0 ? <ArrowUpRight className="w-3.5 h-3.5" /> : y.change < 0 ? <ArrowDownRight className="w-3.5 h-3.5" /> : <Minus className="w-3.5 h-3.5" />}
                                                        {y.change > 0 ? '+' : ''}{y.change}
                                                        {y.change_pct !== null && <span className="font-normal text-[11px] opacity-70">({y.change_pct}%)</span>}
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    <div className={card}>
                        <h2 className="text-sm font-bold text-slate-800 mb-1">Class fill</h2>
                        <p className="text-[11px] text-slate-400 mb-4">Current enrolments against each class's capacity.</p>
                        {enrolment.by_class.length === 0 ? (
                            <p className="text-xs text-slate-400 py-6 text-center">No current enrolments.</p>
                        ) : (
                            <div className="space-y-2.5">
                                {enrolment.by_class.map(c => (
                                    <div key={c.class_id}>
                                        <div className="flex items-center justify-between text-xs mb-1">
                                            <span className="font-semibold text-slate-700">{c.label}
                                                <span className="text-slate-400 font-normal ml-1.5">{c.academic_year}</span>
                                            </span>
                                            <span className="text-slate-500">
                                                {c.students}{c.capacity ? ` / ${c.capacity}` : ''}
                                                {c.fill_pct !== null && <span className="ml-1.5 font-bold" style={{ color: c.fill_pct > 95 ? '#dc2626' : accent }}>{c.fill_pct}%</span>}
                                            </span>
                                        </div>
                                        <div className="h-2 rounded-full bg-slate-100 overflow-hidden">
                                            <div className="h-full rounded-full transition-all"
                                                style={{
                                                    width: `${Math.min(c.fill_pct ?? (c.students > 0 ? 100 : 0), 100)}%`,
                                                    background: c.fill_pct !== null && c.fill_pct > 95 ? '#dc2626' : accent,
                                                }} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* ── RETENTION ── */}
            {tab === 'retention' && (
                <div className="space-y-5">
                    <div className={card}>
                        <h2 className="text-sm font-bold text-slate-800 mb-1">Cohort retention</h2>
                        <p className="text-[11px] text-slate-400 mb-4">
                            Everyone who first joined in a given year, and where they are now. Graduates count as retained —
                            they finished, they did not leave.
                        </p>
                        {retention.cohorts.length === 0 ? (
                            <p className="text-xs text-slate-400 py-6 text-center">No enrolment history yet.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-100">
                                            <th className="text-left py-2">Joined in</th>
                                            <th className="text-right py-2">Cohort</th>
                                            <th className="text-right py-2">Still here</th>
                                            <th className="text-right py-2">Graduated</th>
                                            <th className="text-right py-2">Transferred</th>
                                            <th className="text-right py-2">Withdrawn</th>
                                            <th className="text-right py-2">Retention</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-50">
                                        {retention.cohorts.map(c => (
                                            <tr key={c.academic_year}>
                                                <td className="py-2.5 font-semibold text-slate-800">{c.academic_year}</td>
                                                <td className="py-2.5 text-right text-slate-600">{c.joined}</td>
                                                <td className="py-2.5 text-right text-slate-600">{c.still_enrolled}</td>
                                                <td className="py-2.5 text-right text-slate-600">{c.graduated}</td>
                                                <td className="py-2.5 text-right text-slate-600">{c.transferred}</td>
                                                <td className="py-2.5 text-right text-slate-600">{c.withdrawn}</td>
                                                <td className="py-2.5 text-right">
                                                    <span className="font-bold" style={{ color: (c.retention_pct ?? 100) >= 80 ? '#15803d' : (c.retention_pct ?? 100) >= 60 ? '#b45309' : '#dc2626' }}>
                                                        {c.retention_pct === null ? '—' : `${c.retention_pct}%`}
                                                    </span>
                                                    {c.small_base && (
                                                        <span className="ml-1 text-[10px] text-slate-300" title={`Only ${c.joined} students — too small to read as a trend`}>⚠</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                        {retention.cohorts.some(c => c.small_base) && (
                            <p className="text-[10px] text-slate-400 mt-3">
                                ⚠ groups under {enrolment.small_base} students — the percentage is shown, but one student moves it a long way.
                            </p>
                        )}
                    </div>

                    {retention.exit_reasons.length > 0 && (
                        <div className={card}>
                            <h2 className="text-sm font-bold text-slate-800 mb-4">Why students leave</h2>
                            <div className="space-y-2">
                                {retention.exit_reasons.map(r => {
                                    const max = Math.max(...retention.exit_reasons.map(x => x.total));
                                    return (
                                        <div key={r.reason} className="flex items-center gap-3">
                                            <span className="text-xs text-slate-600 w-40 capitalize">{r.reason}</span>
                                            <div className="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                                <div className="h-full rounded-full" style={{ width: `${r.total / max * 100}%`, background: accent }} />
                                            </div>
                                            <span className="text-xs font-bold text-slate-700 w-6 text-right">{r.total}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* ── RESULTS ── */}
            {tab === 'results' && (
                <div className="space-y-5">
                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Overall average</p>
                            <p className="text-3xl font-extrabold text-slate-900 mt-1">{academic.overall_average ?? '—'}</p>
                        </div>
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Subject results</p>
                            <p className="text-3xl font-extrabold text-slate-900 mt-1">{academic.total_marked}</p>
                        </div>
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Subjects marked</p>
                            <p className="text-3xl font-extrabold text-slate-900 mt-1">{academic.subjects.length}</p>
                        </div>
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Pass mark</p>
                            <p className="text-3xl font-extrabold text-slate-900 mt-1">{academic.pass_mark}</p>
                        </div>
                    </div>

                    {academic.years.length > 1 && (
                        <div className="flex gap-2 flex-wrap">
                            <button onClick={() => router.get('/school/reports', {}, { preserveState: true })}
                                className={`text-xs font-bold px-3 py-1.5 rounded-full ${!filters.year ? 'text-white' : 'text-slate-600 bg-slate-100'}`}
                                style={!filters.year ? { background: accent } : undefined}>All years</button>
                            {academic.years.map(y => (
                                <button key={y} onClick={() => router.get('/school/reports', { year: y } as never, { preserveState: true })}
                                    className={`text-xs font-bold px-3 py-1.5 rounded-full ${filters.year === y ? 'text-white' : 'text-slate-600 bg-slate-100'}`}
                                    style={filters.year === y ? { background: accent } : undefined}>{y}</button>
                            ))}
                        </div>
                    )}

                    <div className={card}>
                        <h2 className="text-sm font-bold text-slate-800 mb-1">Results by subject</h2>
                        <p className="text-[11px] text-slate-400 mb-4">
                            Published marks only, weighted 40 / 20 / 40 — the same arithmetic as the report card and transcript.
                        </p>
                        {academic.subjects.length === 0 ? (
                            <div className="py-8 text-center">
                                <p className="text-xs text-slate-500 font-semibold">No published results yet</p>
                                <p className="text-[11px] text-slate-400 mt-1">
                                    Results appear here once marks are published in the gradebook.
                                </p>
                            </div>
                        ) : (
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-[10px] font-bold uppercase tracking-widest text-slate-400 border-b border-slate-100">
                                        <th className="text-left py-2">Subject</th>
                                        <th className="text-right py-2">Marked</th>
                                        <th className="text-right py-2">Average</th>
                                        <th className="text-right py-2">High</th>
                                        <th className="text-right py-2">Low</th>
                                        <th className="text-right py-2">Pass rate</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {academic.subjects.map(s => (
                                        <tr key={s.subject_id}>
                                            <td className="py-2.5 font-semibold text-slate-800">{s.subject}</td>
                                            <td className="py-2.5 text-right text-slate-500">{s.marked}</td>
                                            <td className="py-2.5 text-right font-bold text-slate-900">{s.average}</td>
                                            <td className="py-2.5 text-right text-slate-500">{s.highest}</td>
                                            <td className="py-2.5 text-right text-slate-500">{s.lowest}</td>
                                            <td className="py-2.5 text-right font-bold"
                                                style={{ color: (s.pass_rate ?? 100) >= 80 ? '#15803d' : (s.pass_rate ?? 100) >= 50 ? '#b45309' : '#dc2626' }}>
                                                {s.pass_rate}%
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>

                    {academic.distribution.length > 0 && academic.total_marked > 0 && (
                        <div className={card}>
                            <h2 className="text-sm font-bold text-slate-800 mb-4">Grade distribution</h2>
                            <div className="space-y-2">
                                {academic.distribution.map(d => {
                                    const max = Math.max(...academic.distribution.map(x => x.count), 1);
                                    return (
                                        <div key={d.label} className="flex items-center gap-3">
                                            <span className="text-xs font-bold text-slate-700 w-6">{d.label}</span>
                                            <span className="text-[10px] text-slate-400 w-20">{d.range}</span>
                                            <div className="flex-1 h-3 rounded-full bg-slate-100 overflow-hidden">
                                                <div className="h-full rounded-full" style={{ width: `${d.count / max * 100}%`, background: accent }} />
                                            </div>
                                            <span className="text-xs font-bold text-slate-700 w-6 text-right">{d.count}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}

            {/* ── AT RISK ── */}
            {tab === 'risk' && (
                <div className="space-y-5">
                    <div className="grid grid-cols-3 gap-3">
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Attendance</p>
                            <p className="text-3xl font-extrabold mt-1" style={{ color: '#b45309' }}>{risk.attendance_risk}</p>
                        </div>
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Academic</p>
                            <p className="text-3xl font-extrabold mt-1" style={{ color: '#b45309' }}>{risk.academic_risk}</p>
                        </div>
                        <div className={card}>
                            <p className="text-[10px] font-bold uppercase tracking-widest text-slate-400">Both signals</p>
                            <p className="text-3xl font-extrabold mt-1" style={{ color: '#dc2626' }}>{risk.both}</p>
                        </div>
                    </div>

                    <div className={card}>
                        <h2 className="text-sm font-bold text-slate-800 mb-1">Students to look at this week</h2>
                        <p className="text-[11px] text-slate-400 mb-4">
                            Checked across {risk.checked} currently enrolled students. Attendance at or below 80%,
                            or an average under 50, or any failing subject — both signals together is the urgent case.
                        </p>

                        {risk.students.length === 0 ? (
                            <div className="py-10 text-center">
                                <p className="text-sm font-bold text-slate-700">Nobody flagged</p>
                                <p className="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                                    {risk.checked === 0
                                        ? 'No current enrolments to check yet.'
                                        : 'No student is currently below the thresholds on attendance or marks.'}
                                </p>
                            </div>
                        ) : (
                            <div className="divide-y divide-slate-50">
                                {risk.students.map(s => (
                                    <Link key={s.id} href={`/school/students/${s.id}`}
                                        className="flex items-center gap-4 py-3 hover:bg-slate-50/60 -mx-2 px-2 rounded-xl transition">
                                        <div className="w-2 h-2 rounded-full flex-shrink-0"
                                            style={{ background: s.severity === 'high' ? '#dc2626' : '#f59e0b' }} />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold text-slate-800">{s.name}
                                                <span className="ml-2 text-[11px] font-normal text-slate-400">{s.uid}{s.class ? ` · ${s.class}` : ''}</span>
                                            </p>
                                            <p className="text-[11px] text-slate-500 mt-0.5">{s.reasons.join(' · ')}</p>
                                        </div>
                                        <div className="text-right flex-shrink-0">
                                            {s.attendance !== null && (
                                                <p className="text-[11px] text-slate-500">
                                                    Attendance <span className="font-bold text-slate-800">{s.attendance}%</span>
                                                </p>
                                            )}
                                            {s.average !== null && (
                                                <p className="text-[11px] text-slate-500">
                                                    Average <span className="font-bold text-slate-800">{s.average}%</span>
                                                </p>
                                            )}
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </SchoolLayout>
    );
}
