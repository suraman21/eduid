import React from 'react';

/**
 * Single shared, template-driven ID card renderer.
 *
 * This is the ONE component that renders a card from a CardTemplate
 * (designer background image + positioned fields). It is used by:
 *   - the school dashboard preview (School/IdCards/Show + Batch)
 *   - the public landing page hero (via a bundled sample template)
 *   - the super-admin designer's "real data" preview (Admin/CardTemplates/Edit)
 *
 * It renders the full canvas at native pixel size (the same coordinate space the
 * designer drags fields in) and scales the whole thing down with a CSS transform,
 * so the on-screen layout matches the printed PDF/PNG field-for-field.
 */

export type TemplateFieldType = 'text' | 'image' | 'qr';

export interface TemplateField {
    field_type: TemplateFieldType;
    field_key: string;
    side: 'front' | 'back';
    x: number;            // px in canvas space
    y: number;
    w: number;
    h: number;
    font_size: number;    // px in canvas space
    font_color: string;
    font_family: string;
    text_align: 'left' | 'center' | 'right';
    font_weight: 'normal' | 'bold';
    border_radius: number;
    custom_value: string | null;
}

export interface CardTemplateData {
    frontUrl: string | null;
    backUrl: string | null;
    canvasW: number;
    canvasH: number;
    backW?: number;
    backH?: number;
    fields: TemplateField[];
}

/** Real data substituted into the positioned fields. */
export interface TemplateValues {
    full_name?: string;
    full_name_am?: string | null;
    uid?: string;
    class_name?: string | null;   // grade_class
    section?: string | null;
    date_of_birth?: string | null;
    blood_type?: string | null;
    academic_year?: string | null;
    school_name?: string;
    photo_url?: string | null;
    qr_svg?: string;              // raw inline SVG markup for the QR
}

const FONT_STACK: Record<string, string> = {
    Inter: "'Outfit', 'Inter', sans-serif",
    Outfit: "'Outfit', 'Inter', sans-serif",
    Roboto: "'Outfit', 'Inter', sans-serif",
    NotoSansEthiopic: "'Noto Sans Ethiopic', 'Outfit', sans-serif",
};

function textValue(field: TemplateField, values: TemplateValues): string {
    switch (field.field_key) {
        case 'full_name':     return values.full_name ?? '';
        case 'full_name_am':  return values.full_name_am ?? '';
        case 'uid':           return values.uid ?? '';
        case 'grade_class':   return values.class_name ?? '';
        case 'section':       return values.section ?? '';
        case 'date_of_birth': return values.date_of_birth ?? '';
        case 'blood_type':    return values.blood_type ?? '';
        case 'academic_year': return values.academic_year ?? '';
        case 'school_name':   return values.school_name ?? '';
        case 'custom_1':
        case 'custom_2':
        case 'custom_3':      return field.custom_value ?? '';
        default:              return '';
    }
}

function FieldNode({ field, values }: { field: TemplateField; values: TemplateValues }) {
    const base: React.CSSProperties = {
        position: 'absolute',
        left: field.x,
        top: field.y,
        width: field.w || undefined,
        height: field.h || undefined,
        borderRadius: field.border_radius || 0,
        overflow: 'hidden',
        boxSizing: 'border-box',
    };

    if (field.field_type === 'image') {
        return (
            <div style={{ ...base, background: '#f1f5f9', border: '1px solid #e2e8f0' }}>
                {values.photo_url ? (
                    <img src={values.photo_url} alt="" style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                ) : (
                    <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                        <svg width="40%" height="40%" viewBox="0 0 24 24" fill="#94a3b8">
                            <circle cx="12" cy="8" r="4" />
                            <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                        </svg>
                    </div>
                )}
            </div>
        );
    }

    if (field.field_type === 'qr') {
        return (
            <div style={{ ...base, background: '#fff' }}>
                {values.qr_svg
                    ? <div style={{ width: '100%', height: '100%' }} dangerouslySetInnerHTML={{ __html: values.qr_svg }} />
                    : <div style={{ width: '100%', height: '100%', background: '#f3f4f6' }} />}
            </div>
        );
    }

    // text
    const justify = field.text_align === 'center' ? 'center' : field.text_align === 'right' ? 'flex-end' : 'flex-start';
    return (
        <div style={{ ...base, display: 'flex', alignItems: 'center', justifyContent: justify }}>
            <span
                style={{
                    fontFamily: FONT_STACK[field.font_family] ?? FONT_STACK.Inter,
                    fontSize: field.font_size || 14,
                    color: field.font_color || '#000000',
                    fontWeight: field.font_weight === 'bold' ? 700 : 400,
                    lineHeight: 1.15,
                    whiteSpace: 'nowrap',
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    maxWidth: '100%',
                }}
            >
                {textValue(field, values)}
            </span>
        </div>
    );
}

export default function TemplateCard({
    template,
    values,
    side = 'front',
    displayWidth = 360,
    preview = true,
    accent = '#6366f1',
    className,
}: {
    template: CardTemplateData;
    values: TemplateValues;
    side?: 'front' | 'back';
    displayWidth?: number;
    preview?: boolean;
    accent?: string;
    className?: string;
}) {
    const isBack = side === 'back';
    const cw = isBack ? (template.backW ?? template.canvasW) : template.canvasW;
    const ch = isBack ? (template.backH ?? template.canvasH) : template.canvasH;
    const url = isBack ? template.backUrl : template.frontUrl;
    const scale = displayWidth / cw;
    const dispH = Math.round(ch * scale);
    const fields = template.fields.filter(f => (f.side ?? 'front') === side);

    return (
        <div
            className={className}
            style={{
                position: 'relative',
                width: displayWidth,
                height: dispH,
                borderRadius: 10,
                overflow: 'hidden',
                boxShadow: '0 10px 30px rgba(15,23,42,0.16)',
                background: '#fff',
                flexShrink: 0,
            }}
        >
            <div
                style={{
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    width: cw,
                    height: ch,
                    transformOrigin: 'top left',
                    transform: `scale(${scale})`,
                }}
            >
                {/* Background */}
                {url
                    ? <img src={url} alt="" style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'fill' }} />
                    : <div style={{ position: 'absolute', inset: 0, background: '#fff' }} />}

                {/* Positioned fields */}
                {fields.map((f, i) => <FieldNode key={i} field={f} values={values} />)}
            </div>

            {/* Golden-Rule preview watermark — schools only ever see a watermarked preview */}
            {preview && (
                <div
                    aria-hidden="true"
                    style={{
                        position: 'absolute', inset: 0, zIndex: 5, pointerEvents: 'none',
                        backgroundImage: `repeating-linear-gradient(-45deg, ${accent}14 0, ${accent}14 2px, transparent 2px, transparent 60px)`,
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                    }}
                >
                    <span style={{ transform: 'rotate(-22deg)', fontSize: Math.round(displayWidth * 0.07), fontWeight: 800, letterSpacing: 4, color: `${accent}40`, textTransform: 'uppercase', whiteSpace: 'nowrap' }}>
                        Preview
                    </span>
                </div>
            )}
        </div>
    );
}
