import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import IdCard, { type CardStudent, type CardSchool } from '@/Components/IdCard';
import type { PageProps } from '@/types';
import { ShoppingCart, ArrowLeft } from 'lucide-react';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface Props {
    student: CardStudent;
    school: CardSchool;
}

export default function Show({ student, school }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);

    return (
        <SchoolLayout>
            <Head title={`${student.full_name} — ID Card`} />

            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">ID Card Preview</h1>
                    <p className="text-sm text-slate-400 mt-1">{student.full_name} · {student.class_name ?? '—'}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Link href={`/school/students/${student.id}`} className="flex items-center gap-1.5 px-3.5 py-2.5 text-sm font-semibold text-slate-600 border border-slate-200 rounded-2xl bg-white hover:bg-slate-50 transition">
                        <ArrowLeft className="w-4 h-4" />
                        Back
                    </Link>
                    <Link
                        href="/school/card-orders/create"
                        className="flex items-center gap-1.5 px-4 py-2.5 text-sm font-bold text-white rounded-2xl transition hover:-translate-y-0.5"
                        style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                    >
                        <ShoppingCart className="w-4 h-4" />
                        Order Cards
                    </Link>
                </div>
            </div>

            <div className="flex justify-center py-6">
                <IdCard student={student} school={school} preview />
            </div>

            {/* Golden-rule explainer */}
            <div
                className="max-w-md mx-auto mt-4 rounded-3xl border p-5 text-center"
                style={{ background: alpha(accent, 0.06), borderColor: alpha(accent, 0.18) }}
            >
                <p className="text-sm font-bold" style={{ color: accent }}>This is a preview of your student's ID card.</p>
                <p className="text-xs text-slate-500 mt-1.5 leading-relaxed">
                    Professional PVC cards are printed and delivered by Arkeon. Place a card order
                    and we'll print and ship the finished cards to your school.
                </p>
                <Link href="/school/card-orders/create" className="inline-flex items-center gap-1 mt-3 text-xs font-bold" style={{ color: accent }}>
                    Order ID Cards →
                </Link>
            </div>
        </SchoolLayout>
    );
}
