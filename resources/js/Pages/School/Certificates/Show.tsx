import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Printer, ShieldOff, X } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import CertificateLetter, { normHex, type CertSnapshot } from '@/Components/CertificateLetter';

interface Certificate {
    id: number;
    reference: string;
    type: string;
    type_label: string;
    addressed_to: string | null;
    issued_on: string;
    issued_by: string | null;
    body_note: string | null;
    is_revoked: boolean;
    revoked_reason: string | null;
    snapshot: CertSnapshot;
}

interface Props {
    certificate: Certificate;
    student: { id: number | null; name: string | null; uid: string | null };
    schoolName: string;
    is_admin: boolean;
}

export default function Show({ certificate, student, is_admin }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');

    function revoke() {
        router.post(`/school/certificates/${certificate.id}/revoke`,
            { reason } as never, { onFinish: () => setOpen(false) });
    }

    return (
        <SchoolLayout>
            <Head title={`${certificate.reference} · ${student.name}`} />

            <div className="print:hidden flex items-center justify-between mb-6 flex-wrap gap-3">
                <Link href="/school/certificates"
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800">
                    <ArrowLeft className="w-4 h-4" /> All certificates
                </Link>
                <div className="flex items-center gap-2">
                    {student.id && (
                        <Link href={`/school/students/${student.id}`}
                            className="text-xs font-bold px-3.5 py-2 rounded-2xl text-slate-500 bg-slate-100 hover:bg-slate-200">
                            {student.name}
                        </Link>
                    )}
                    <button onClick={() => window.print()}
                        className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-slate-600 bg-slate-100 hover:bg-slate-200">
                        <Printer className="w-4 h-4" /> Print
                    </button>
                    {is_admin && !certificate.is_revoked && (
                        <button onClick={() => setOpen(true)}
                            className="flex items-center gap-2 text-sm font-bold px-4 py-2 rounded-2xl text-red-600 bg-red-50 hover:bg-red-100">
                            <ShieldOff className="w-4 h-4" /> Revoke
                        </button>
                    )}
                </div>
            </div>

            {open && is_admin && (
                <div className="print:hidden mb-5 p-5 rounded-3xl border border-slate-200 bg-white max-w-3xl mx-auto">
                    <div className="flex items-center justify-between mb-3">
                        <p className="text-sm font-bold text-slate-800">Revoke {certificate.reference}</p>
                        <button onClick={() => setOpen(false)} className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100">
                            <X className="w-4 h-4" />
                        </button>
                    </div>
                    <p className="text-[11px] text-slate-400 mb-3">
                        The letter stays on file with its wording intact, marked as revoked. The reference is never reused.
                    </p>
                    <input value={reason} onChange={e => setReason(e.target.value)}
                        placeholder="Why is this being revoked?"
                        className="w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm mb-3" />
                    <button onClick={revoke}
                        className="px-5 py-2.5 rounded-2xl text-sm font-bold text-white bg-red-600">
                        Revoke certificate
                    </button>
                </div>
            )}

            <div className="print:hidden flex items-center justify-center gap-3 mb-4 text-[11px] text-slate-400">
                <span className="font-mono font-semibold text-slate-600">{certificate.reference}</span>
                <span>·</span>
                <span>{certificate.type_label}</span>
                <span>·</span>
                <span>issued {certificate.issued_on}</span>
                {certificate.issued_by && <><span>·</span><span>{certificate.issued_by}</span></>}
            </div>

            <CertificateLetter
                snapshot={certificate.snapshot}
                accent={accent}
                reference={certificate.reference}
                issuedOn={certificate.issued_on}
                addressedTo={certificate.addressed_to}
                isRevoked={certificate.is_revoked}
                revokedReason={certificate.revoked_reason}
            />
        </SchoolLayout>
    );
}
