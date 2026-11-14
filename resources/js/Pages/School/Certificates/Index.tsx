import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { FilePlus2, Search, ChevronLeft, ChevronRight, ScrollText } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { normHex } from '@/Components/CertificateLetter';

interface Row {
    id: number;
    reference: string;
    type: string;
    type_label: string;
    student: { id: number | null; name: string | null; uid: string | null };
    addressed_to: string | null;
    issued_on: string;
    issued_by: string | null;
    is_revoked: boolean;
}

interface Page {
    data: Row[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    certificates: Page;
    counts: Record<string, number>;
    total: number;
    types: { value: string; label: string }[];
    filters: { type: string | null; q: string };
    is_admin: boolean;
}

export default function Index({ certificates, counts, total, types, filters, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const [q, setQ] = useState(filters.q || '');

    function filter(patch: Record<string, string | null>) {
        router.get('/school/certificates', { ...filters, ...patch } as never, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }

    return (
        <SchoolLayout>
            <Head title="Certificates" />

            <div className="flex items-center justify-between mb-6 flex-wrap gap-3">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Certificates</h1>
                    <p className="text-sm text-slate-500 mt-0.5">
                        {total} issued · letters generated from student records
                    </p>
                </div>
                {is_admin && (
                    <Link href="/school/certificates/create"
                        className="flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-2xl text-white"
                        style={{ background: accent, boxShadow: `0 4px 14px ${accent}59` }}>
                        <FilePlus2 className="w-4 h-4" /> New certificate
                    </Link>
                )}
            </div>

            {/* Type filter chips */}
            <div className="flex flex-wrap items-center gap-2 mb-4">
                <button onClick={() => filter({ type: null })}
                    className={`text-xs font-bold px-3 py-1.5 rounded-full transition ${!filters.type ? 'text-white' : 'text-slate-600 bg-slate-100 hover:bg-slate-200'}`}
                    style={!filters.type ? { background: accent } : undefined}>
                    All
                </button>
                {types.map(t => (
                    <button key={t.value} onClick={() => filter({ type: t.value })}
                        className={`text-xs font-bold px-3 py-1.5 rounded-full transition ${filters.type === t.value ? 'text-white' : 'text-slate-600 bg-slate-100 hover:bg-slate-200'}`}
                        style={filters.type === t.value ? { background: accent } : undefined}>
                        {t.label}
                        {counts[t.value] ? <span className="ml-1.5 opacity-70">{counts[t.value]}</span> : null}
                    </button>
                ))}
            </div>

            {/* Search */}
            <form onSubmit={e => { e.preventDefault(); filter({ q: q || null }); }}
                className="relative mb-5 max-w-md">
                <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input value={q} onChange={e => setQ(e.target.value)}
                    placeholder="Reference, name or student ID…"
                    className="w-full pl-10 pr-3 py-2.5 rounded-2xl border border-slate-200 text-sm focus:outline-none focus:ring-2"
                    style={{ ['--tw-ring-color' as string]: `${accent}59` }} />
            </form>

            {certificates.data.length === 0 ? (
                <div className="bg-white rounded-3xl border border-slate-200 py-16 text-center">
                    <ScrollText className="w-10 h-10 text-slate-200 mx-auto mb-3" />
                    <p className="text-sm font-bold text-slate-700">No certificates yet</p>
                    <p className="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                        Transfer letters, completion certificates, testimonials and bonafide letters
                        all come from here — generated from the student's own record.
                    </p>
                    {is_admin && (
                        <Link href="/school/certificates/create"
                            className="inline-flex items-center gap-2 mt-4 text-xs font-bold px-4 py-2 rounded-2xl text-white"
                            style={{ background: accent }}>
                            <FilePlus2 className="w-3.5 h-3.5" /> Issue the first one
                        </Link>
                    )}
                </div>
            ) : (
                <div className="bg-white rounded-3xl border border-slate-200 divide-y divide-slate-50 overflow-hidden">
                    {certificates.data.map(c => (
                        <Link key={c.id} href={`/school/certificates/${c.id}`}
                            className="flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50 transition">
                            <div className="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                style={{ background: `${accent}14`, color: accent }}>
                                <ScrollText className="w-4 h-4" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-bold text-slate-800 flex items-center gap-2 flex-wrap">
                                    {c.type_label}
                                    {c.is_revoked && (
                                        <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-50 text-red-600">
                                            Revoked
                                        </span>
                                    )}
                                </p>
                                <p className="text-[11px] text-slate-400 truncate">
                                    {c.student.name} · {c.student.uid}
                                    {c.addressed_to ? ` · ${c.addressed_to}` : ''}
                                </p>
                            </div>
                            <div className="text-right flex-shrink-0">
                                <p className="text-[11px] font-mono font-semibold text-slate-500">{c.reference}</p>
                                <p className="text-[10px] text-slate-400">{c.issued_on}</p>
                            </div>
                        </Link>
                    ))}
                </div>
            )}

            {certificates.last_page > 1 && (
                <div className="flex items-center justify-between mt-5">
                    <p className="text-xs text-slate-400">
                        {(certificates.current_page - 1) * 25 + 1}–{Math.min(certificates.current_page * 25, certificates.total)} of {certificates.total}
                    </p>
                    <div className="flex gap-2">
                        {certificates.current_page > 1 && (
                            <Link href={`/school/certificates?page=${certificates.current_page - 1}`}
                                className="p-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200">
                                <ChevronLeft className="w-4 h-4" />
                            </Link>
                        )}
                        {certificates.current_page < certificates.last_page && (
                            <Link href={`/school/certificates?page=${certificates.current_page + 1}`}
                                className="p-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200">
                                <ChevronRight className="w-4 h-4" />
                            </Link>
                        )}
                    </div>
                </div>
            )}
        </SchoolLayout>
    );
}
