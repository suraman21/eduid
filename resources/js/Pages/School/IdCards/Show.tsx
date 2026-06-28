import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import IdCard, { type CardStudent, type CardSchool } from '@/Components/IdCard';
import TemplateCard, { type CardTemplateData, type TemplateValues } from '@/Components/TemplateCard';
import type { PageProps } from '@/types';
import { ShoppingCart, ArrowLeft } from 'lucide-react';

// CR80 (85.6 mm) at ~96 CSS px/inch ≈ 324px — renders the preview close to the
// real printed card size rather than the oversized earlier preview.
const CARD_W = 324;

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
    template: CardTemplateData | null;
}

function toValues(student: CardStudent, school: CardSchool): TemplateValues {
    return {
        full_name:     student.full_name,
        full_name_am:  student.full_name_am,
        uid:           student.uid,
        class_name:    student.class_name,
        academic_year: school.academic_year ?? undefined,
        school_name:   school.name,
        photo_url:     student.photo_url,
        qr_svg:        student.qr_svg,
    };
}

export default function Show({ student, school, template }: Props) {
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

            <div className="flex flex-col items-center gap-3 py-6">
                {template ? (
                    <div className="flex flex-wrap items-start justify-center gap-6">
                        <div className="flex flex-col items-center gap-2">
                            <TemplateCard template={template} values={toValues(student, school)} displayWidth={CARD_W} side="front" accent={accent} preview />
                            <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Front</span>
                        </div>
                        {template.backUrl && (
                            <div className="flex flex-col items-center gap-2">
                                <TemplateCard template={template} values={toValues(student, school)} displayWidth={CARD_W} side="back" accent={accent} preview />
                                <span className="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">Back</span>
                            </div>
                        )}
                    </div>
                ) : (
                    <IdCard student={student} school={school} preview />
                )}
                <p className="text-[11px] text-slate-400">Shown at actual card size · CR80 (85.6 × 54 mm)</p>
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
