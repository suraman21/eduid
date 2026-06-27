import type { CardTemplateData, TemplateField } from '@/Components/TemplateCard';

/**
 * A bundled "sample school" card template for the public landing page.
 *
 * It uses the exact same data model as a real super-admin-designed template
 * (a background image + fields positioned in canvas pixels), so the public hero
 * card is structurally identical to what a real school's printed card looks like.
 *
 * The background is generated procedurally as an inline SVG data-URI (accent
 * header/footer bands + static labels) so the accent colour stays customisable.
 */

// CR80 landscape at 300 DPI — the same default canvas the designer uses.
export const SAMPLE_CANVAS_W = 1012;
export const SAMPLE_CANVAS_H = 638;

const HEADER_H = 132;
const FOOTER_H = 52;

/** Procedural background: accent header + footer bands with baked static labels. */
export function buildSampleBackground(accent: string, schoolCode = 'AIA'): string {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${SAMPLE_CANVAS_W}" height="${SAMPLE_CANVAS_H}" viewBox="0 0 ${SAMPLE_CANVAS_W} ${SAMPLE_CANVAS_H}">
  <rect width="${SAMPLE_CANVAS_W}" height="${SAMPLE_CANVAS_H}" fill="#ffffff"/>
  <rect width="${SAMPLE_CANVAS_W}" height="${HEADER_H}" fill="${accent}"/>
  <rect y="${SAMPLE_CANVAS_H - FOOTER_H}" width="${SAMPLE_CANVAS_W}" height="${FOOTER_H}" fill="#f8fafc"/>
  <rect y="${SAMPLE_CANVAS_H - FOOTER_H}" width="${SAMPLE_CANVAS_W}" height="2" fill="#e2e8f0"/>
  <rect x="40" y="34" width="64" height="64" rx="8" fill="rgba(255,255,255,0.25)"/>
  <text x="72" y="78" font-family="Outfit, Arial, sans-serif" font-size="30" font-weight="800" fill="#ffffff" text-anchor="middle">${schoolCode.slice(0, 2).toUpperCase()}</text>
  <text x="${SAMPLE_CANVAS_W - 40}" y="60" font-family="Outfit, Arial, sans-serif" font-size="20" font-weight="700" letter-spacing="3" fill="rgba(255,255,255,0.9)" text-anchor="end">STUDENT ID</text>
  <text x="40" y="${SAMPLE_CANVAS_H - 18}" font-family="Outfit, Arial, sans-serif" font-size="20" fill="#94a3b8">${schoolCode}</text>
  <text x="${SAMPLE_CANVAS_W - 40}" y="${SAMPLE_CANVAS_H - 18}" font-family="Outfit, Arial, sans-serif" font-size="20" fill="#94a3b8" text-anchor="end">EduID · Arkeon Ethiopia</text>
</svg>`;
    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

/** Procedural avatar (initials block) so the photo field renders without a real image. */
export function buildAvatar(initials: string, bg = '#dbeafe', fg = '#1e3a5f'): string {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="190" height="240" viewBox="0 0 190 240">
  <rect width="190" height="240" fill="${bg}"/>
  <text x="95" y="138" font-family="Outfit, Arial, sans-serif" font-size="86" font-weight="700" fill="${fg}" text-anchor="middle">${initials}</text>
</svg>`;
    return `data:image/svg+xml;utf8,${encodeURIComponent(svg)}`;
}

/** The positioned fields — mirrors the real default card layout using real field keys. */
export const SAMPLE_FIELDS: TemplateField[] = [
    { field_type: 'text',  field_key: 'school_name',   side: 'front', x: 120, y: 36,  w: 620, h: 44, font_size: 30, font_color: '#ffffff', font_family: 'Outfit', text_align: 'left',  font_weight: 'bold',   border_radius: 0, custom_value: null },
    { field_type: 'text',  field_key: 'academic_year', side: 'front', x: 120, y: 82,  w: 300, h: 30, font_size: 20, font_color: 'rgba(255,255,255,0.85)', font_family: 'Outfit', text_align: 'left', font_weight: 'normal', border_radius: 0, custom_value: null },
    { field_type: 'image', field_key: 'photo',         side: 'front', x: 40,  y: 168, w: 190, h: 240, font_size: 14, font_color: '#000000', font_family: 'Outfit', text_align: 'left', font_weight: 'normal', border_radius: 8, custom_value: null },
    { field_type: 'text',  field_key: 'full_name',     side: 'front', x: 262, y: 180, w: 520, h: 50, font_size: 40, font_color: '#0f172a', font_family: 'Outfit', text_align: 'left', font_weight: 'bold',   border_radius: 0, custom_value: null },
    { field_type: 'text',  field_key: 'full_name_am',  side: 'front', x: 262, y: 236, w: 520, h: 36, font_size: 26, font_color: '#64748b', font_family: 'NotoSansEthiopic', text_align: 'left', font_weight: 'normal', border_radius: 0, custom_value: null },
    { field_type: 'text',  field_key: 'grade_class',   side: 'front', x: 262, y: 292, w: 520, h: 32, font_size: 23, font_color: '#334155', font_family: 'Outfit', text_align: 'left', font_weight: 'normal', border_radius: 0, custom_value: null },
    { field_type: 'text',  field_key: 'custom_1',      side: 'front', x: 262, y: 332, w: 520, h: 30, font_size: 21, font_color: '#334155', font_family: 'Outfit', text_align: 'left', font_weight: 'normal', border_radius: 0, custom_value: 'DOB: 14 Apr 2010' },
    { field_type: 'text',  field_key: 'custom_2',      side: 'front', x: 262, y: 368, w: 520, h: 30, font_size: 21, font_color: '#334155', font_family: 'Outfit', text_align: 'left', font_weight: 'normal', border_radius: 0, custom_value: 'Blood: O+' },
    { field_type: 'qr',    field_key: 'qr_code',       side: 'front', x: 818, y: 180, w: 154, h: 154, font_size: 14, font_color: '#000000', font_family: 'Outfit', text_align: 'left', font_weight: 'normal', border_radius: 0, custom_value: null },
    { field_type: 'text',  field_key: 'uid',           side: 'front', x: 798, y: 346, w: 194, h: 26, font_size: 17, font_color: '#94a3b8', font_family: 'Outfit', text_align: 'center', font_weight: 'normal', border_radius: 0, custom_value: null },
];

export function buildSampleTemplate(accent: string, schoolCode = 'AIA'): CardTemplateData {
    return {
        frontUrl: buildSampleBackground(accent, schoolCode),
        backUrl: null,
        canvasW: SAMPLE_CANVAS_W,
        canvasH: SAMPLE_CANVAS_H,
        fields: SAMPLE_FIELDS,
    };
}
