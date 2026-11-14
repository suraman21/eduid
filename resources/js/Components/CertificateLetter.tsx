import React from 'react';

export interface CertFact {
    label: string;
    value: string;
}

export interface CertSnapshot {
    type: string;
    title: string;
    addressed_to: string | null;
    paragraphs: string[];
    closing: string | null;
    facts: CertFact[];
    student: {
        id: number;
        uid: string;
        full_name: string;
        full_name_am: string | null;
        gender: string | null;
        date_of_birth: string | null;
    };
    school: {
        name: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
    };
    generated_on: string;
}

export function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
export function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface Props {
    snapshot: CertSnapshot;
    accent: string;
    reference?: string | null;
    issuedOn?: string | null;
    addressedTo?: string | null;
    isRevoked?: boolean;
    revokedReason?: string | null;
}

/**
 * The letter itself. Deliberately styled as a document rather than app UI —
 * this is what lands on school letterhead, gets signed and stamped.
 */
export default function CertificateLetter({
    snapshot, accent, reference, issuedOn, addressedTo, isRevoked, revokedReason,
}: Props) {
    const s = snapshot;
    const school = s.school || { name: null, address: null, phone: null, email: null };

    return (
        <div className="bg-white rounded-3xl border border-slate-200 p-8 sm:p-10 print:border-0 print:p-0 print:rounded-none max-w-3xl mx-auto">

            {isRevoked && (
                <div className="mb-6 border-2 border-red-300 rounded-xl px-4 py-2 text-center">
                    <p className="text-[11px] font-extrabold uppercase tracking-[0.2em] text-red-600">
                        Revoked — not valid
                    </p>
                    {revokedReason && <p className="text-[11px] text-red-500 mt-0.5">{revokedReason}</p>}
                </div>
            )}

            {/* Letterhead */}
            <div className="text-center border-b-2 pb-4 mb-6" style={{ borderColor: accent }}>
                <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">{school.name}</h1>
                {(school.address || school.phone || school.email) && (
                    <p className="text-[11px] text-slate-400 mt-1">
                        {[school.address, school.phone, school.email].filter(Boolean).join(' · ')}
                    </p>
                )}
            </div>

            {/* Reference / date */}
            <div className="flex items-start justify-between text-[11px] text-slate-500 mb-6">
                <div className="space-y-0.5">
                    {reference && <p>Ref: <span className="font-mono font-semibold text-slate-700">{reference}</span></p>}
                    {s.student.uid && <p>Student ID: <span className="font-mono font-semibold text-slate-700">{s.student.uid}</span></p>}
                </div>
                <div className="text-right space-y-0.5">
                    <p>{issuedOn || s.generated_on}</p>
                </div>
            </div>

            {/* Title */}
            <h2 className="text-center text-sm font-extrabold uppercase tracking-[0.18em] text-slate-800 mb-6">
                {s.title}
            </h2>

            {/* Addressee */}
            <p className="text-sm text-slate-700 mb-4">
                {addressedTo ? <>To: <span className="font-semibold">{addressedTo}</span></> : 'To whom it may concern, '}
            </p>

            {/* Body */}
            <div className="space-y-3">
                {s.paragraphs.map((p, i) => (
                    <p key={i} className="text-sm leading-7 text-slate-700">{p}</p>
                ))}
            </div>

            {/* Facts table */}
            {s.facts.length > 0 && (
                <table className="w-full text-xs mt-6 border border-slate-200">
                    <tbody className="divide-y divide-slate-100">
                        {s.facts.map(f => (
                            <tr key={f.label}>
                                <td className="px-3 py-1.5 bg-slate-50 font-bold text-slate-500 w-1/3">{f.label}</td>
                                <td className="px-3 py-1.5 text-slate-800">{f.value}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}

            {/* Closing + signature */}
            <p className="text-sm text-slate-700 mt-6">{s.closing}</p>

            <div className="mt-10 flex items-end justify-between gap-8">
                <div className="flex-1">
                    <div className="border-b border-slate-300 h-8" />
                    <p className="text-[10px] text-slate-400 mt-1 uppercase tracking-widest">Registrar signature</p>
                </div>
                <div className="flex-1">
                    <div className="border-b border-slate-300 h-8" />
                    <p className="text-[10px] text-slate-400 mt-1 uppercase tracking-widest">School stamp / date</p>
                </div>
            </div>

            <p className="text-[9px] text-slate-300 mt-8 text-center">
                Generated from school records{s.generated_on ? ` · ${s.generated_on}` : ''}
                {reference ? ` · ${reference}` : ''}
            </p>
        </div>
    );
}
