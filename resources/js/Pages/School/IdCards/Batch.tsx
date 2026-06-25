import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import IdCard, { type CardStudent, type CardSchool } from '@/Components/IdCard';
import type { PageProps } from '@/types';
import { ArrowLeft } from 'lucide-react';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface Props {
    students: CardStudent[];
    school: CardSchool;
    cardOrder: { id: number; code: string; count: number };
}

export default function Batch({ students, school, cardOrder }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    return (
        <SchoolLayout>
            <Head title={`ID Cards — ${cardOrder.code}`} />

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">ID Card Preview</h1>
                    <p className="text-sm text-slate-400 mt-1">{cardOrder.code} · {cardOrder.count} card{cardOrder.count !== 1 ? 's' : ''}</p>
                </div>
                <Link href={`/school/card-orders/${cardOrder.id}`} className="flex items-center gap-1.5 px-3.5 py-2.5 text-sm font-semibold text-slate-600 border border-slate-200 rounded-2xl bg-white hover:bg-slate-50 transition">
                    <ArrowLeft className="w-4 h-4" />
                    Back to Order
                </Link>
            </div>

            <div
                className="mb-6 rounded-3xl border p-4 text-sm"
                style={{ background: alpha(accent, 0.06), borderColor: alpha(accent, 0.18), color: accent }}
            >
                These are previews of the cards in this order. Arkeon prints the professional PVC
                cards and ships them to your school — you don't need to print anything yourself.
            </div>

            {students.length === 0 ? (
                <div className="text-center py-16 text-slate-400 text-sm">No students found in this order.</div>
            ) : (
                <div className="flex flex-wrap gap-6 justify-center">
                    {students.map(s => (
                        <IdCard key={s.id} student={s} school={school} preview />
                    ))}
                </div>
            )}
        </SchoolLayout>
    );
}
