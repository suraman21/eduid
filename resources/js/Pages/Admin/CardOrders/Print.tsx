import React from 'react';
import { Head, Link } from '@inertiajs/react';
import TemplateCard, { type CardTemplateData, type TemplateValues } from '@/Components/TemplateCard';
import { Printer, ArrowLeft } from 'lucide-react';

interface PrintTemplate extends CardTemplateData {
    cardWmm: number;
    cardHmm: number;
}

interface CardData {
    full_name: string;
    full_name_am: string | null;
    uid: string;
    class_name: string | null;
    date_of_birth: string | null;
    blood_type: string | null;
    photo_url: string | null;
    qr_svg: string;
}

interface Props {
    order: { id: number; order_code: string; count: number };
    school: { name: string; code: string; academic_year: string | null };
    template: PrintTemplate | null;
    cards: CardData[];
}

const MM_PER_IN = 25.4;
const CSS_DPI = 96; // CSS px per inch — a div of (mm/25.4*96)px prints at exactly mm

export default function Print({ order, school, template, cards }: Props) {
    if (!template || !template.frontUrl) {
        return (
            <div className="min-h-screen flex flex-col items-center justify-center gap-4 p-8 text-center">
                <Head title={`Print — ${order.order_code}`} />
                <p className="text-slate-600 max-w-md">
                    This school has no active card template, so there is nothing to print here.
                    Configure a template, or use the server-generated PDF on the order page.
                </p>
                <Link href={`/admin/card-orders/${order.id}`} className="text-sm font-bold text-indigo-600">← Back to order</Link>
            </div>
        );
    }

    const t = template;
    const frontPx = (t.cardWmm / MM_PER_IN) * CSS_DPI;
    const backWmm = (t.backW ?? t.canvasW) / 300 * MM_PER_IN;
    const backHmm = (t.backH ?? t.canvasH) / 300 * MM_PER_IN;
    const backPx = (backWmm / MM_PER_IN) * CSS_DPI;

    const toValues = (c: CardData): TemplateValues => ({
        full_name: c.full_name,
        full_name_am: c.full_name_am,
        uid: c.uid,
        class_name: c.class_name,
        date_of_birth: c.date_of_birth,
        blood_type: c.blood_type,
        academic_year: school.academic_year ?? undefined,
        school_name: school.name,
        photo_url: c.photo_url,
        qr_svg: c.qr_svg,
    });

    return (
        <>
            <Head title={`Print — ${order.order_code}`} />
            <style>{`
                @page { size: ${t.cardWmm}mm ${t.cardHmm}mm; margin: 0; }
                .print-page { margin: 0 auto 20px; box-shadow: 0 6px 24px rgba(15,23,42,.18); }
                @media print {
                    .no-print { display: none !important; }
                    html, body { margin: 0; padding: 0; background: #fff; }
                    .print-sheet { padding: 0; background: #fff; }
                    .print-page { margin: 0 !important; box-shadow: none !important; break-after: page; page-break-after: always; }
                    .print-page:last-child { break-after: auto; page-break-after: auto; }
                }
            `}</style>

            {/* Toolbar (screen only) */}
            <div className="no-print sticky top-0 z-10 bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <Link href={`/admin/card-orders/${order.id}`} className="flex items-center gap-1.5 text-sm font-semibold text-slate-500 hover:text-slate-700">
                        <ArrowLeft className="w-4 h-4" /> Back
                    </Link>
                    <span className="text-sm text-slate-400">·</span>
                    <span className="text-sm font-bold text-slate-700 font-mono">{order.order_code}</span>
                    <span className="text-sm text-slate-400">{school.name} · {order.count} card{order.count !== 1 ? 's' : ''}</span>
                </div>
                <button
                    onClick={() => window.print()}
                    className="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition"
                >
                    <Printer className="w-4 h-4" /> Print / Save as PDF
                </button>
            </div>

            <div className="no-print bg-amber-50 border-b border-amber-200 px-6 py-2.5 text-xs text-amber-800">
                In the print dialog choose <strong>“Save as PDF”</strong>, set Margins to <strong>None</strong> and enable
                <strong> Background graphics</strong> for an accurate, print-ready file. Cards are sized to {t.cardWmm.toFixed(1)} × {t.cardHmm.toFixed(1)} mm.
            </div>

            {/* Cards — one per printed page (front, then back if present) */}
            <div className="print-sheet bg-slate-100 py-8 min-h-screen">
                {cards.map((c, i) => (
                    <React.Fragment key={i}>
                        <div className="print-page" style={{ width: `${t.cardWmm}mm`, height: `${t.cardHmm}mm`, overflow: 'hidden', background: '#fff' }}>
                            <TemplateCard template={t} values={toValues(c)} side="front" displayWidth={frontPx} preview={false} />
                        </div>
                        {t.backUrl && (
                            <div className="print-page" style={{ width: `${backWmm}mm`, height: `${backHmm}mm`, overflow: 'hidden', background: '#fff' }}>
                                <TemplateCard template={t} values={toValues(c)} side="back" displayWidth={backPx} preview={false} />
                            </div>
                        )}
                    </React.Fragment>
                ))}
            </div>
        </>
    );
}
