import React from 'react';

export interface CardStudent {
    id: number;
    full_name: string;
    full_name_am: string | null;
    uid: string;
    photo_url: string | null;
    class_name: string | null;
    date_of_birth: string | null;
    blood_type: string | null;
    qr_svg: string;
}

export interface CardSchool {
    name: string;
    code: string;
    academic_year: string | null;
    accent_color: string | null;
    logo_url: string | null;
    phone: string | null;
}

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}

/**
 * Physical CR80 card preview (85.6mm × 54mm), rendered at 2× (342×216 px).
 * Watermarked on the school side per the PRD Golden Rule — schools only ever
 * see a low-res preview; Arkeon prints the clean print-ready card.
 */
export default function IdCard({
    student, school, preview = true,
}: { student: CardStudent; school: CardSchool; preview?: boolean }) {
    const accent = normHex(school.accent_color);

    return (
        <div
            className="id-card"
            style={{
                position: 'relative',
                width: 342, height: 216,
                borderRadius: 8,
                boxShadow: '0 4px 24px rgba(0,0,0,.18)',
                overflow: 'hidden',
                fontFamily: "'Outfit', sans-serif",
                display: 'flex',
                flexDirection: 'column',
                background: 'white',
                flexShrink: 0,
            }}
        >
            {/* Preview watermark — repeating diagonal text the school cannot remove. */}
            {preview && (
                <div
                    aria-hidden="true"
                    style={{
                        position: 'absolute', inset: 0, zIndex: 5, pointerEvents: 'none',
                        backgroundImage: `repeating-linear-gradient(-45deg, ${accent}1a 0, ${accent}1a 2px, transparent 2px, transparent 60px)`,
                        display: 'flex', alignItems: 'center', justifyContent: 'center',
                    }}
                >
                    <span style={{
                        transform: 'rotate(-22deg)',
                        fontSize: 26, fontWeight: 800, letterSpacing: 4,
                        color: `${accent}47`, textTransform: 'uppercase',
                        whiteSpace: 'nowrap',
                    }}>Preview</span>
                </div>
            )}

            {/* Header stripe */}
            <div style={{ background: accent, padding: '8px 12px', display: 'flex', alignItems: 'center', gap: 8, flexShrink: 0 }}>
                {school.logo_url ? (
                    <img src={school.logo_url} style={{ width: 28, height: 28, borderRadius: 4, objectFit: 'cover' }} alt="" />
                ) : (
                    <div style={{ width: 28, height: 28, borderRadius: 4, background: 'rgba(255,255,255,.25)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 700, color: 'white' }}>
                        {school.name.slice(0, 2).toUpperCase()}
                    </div>
                )}
                <div style={{ flex: 1, minWidth: 0 }}>
                    <p style={{ color: 'white', fontSize: 9, fontWeight: 700, lineHeight: 1.2, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{school.name}</p>
                    <p style={{ color: 'rgba(255,255,255,.75)', fontSize: 7, lineHeight: 1.2 }}>{school.academic_year}</p>
                </div>
                <p style={{ color: 'rgba(255,255,255,.85)', fontSize: 7, fontWeight: 600, letterSpacing: .5, textTransform: 'uppercase', whiteSpace: 'nowrap' }}>Student ID</p>
            </div>

            {/* Body */}
            <div style={{ flex: 1, display: 'flex', padding: '10px 12px', gap: 10, overflow: 'hidden' }}>
                {/* Photo */}
                <div style={{ flexShrink: 0 }}>
                    {student.photo_url ? (
                        <img src={student.photo_url} alt={student.full_name}
                            style={{ width: 64, height: 80, objectFit: 'cover', borderRadius: 4, border: '1.5px solid #e5e7eb' }} />
                    ) : (
                        <div style={{ width: 64, height: 80, borderRadius: 4, border: '1.5px dashed #d1d5db', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#f9fafb' }}>
                            <svg style={{ width: 24, height: 24, color: '#9ca3af' }} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zm-4 7a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    )}
                </div>

                {/* Info */}
                <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 3 }}>
                    <p style={{ fontSize: 12, fontWeight: 700, color: '#111827', lineHeight: 1.3, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {student.full_name}
                    </p>
                    {student.full_name_am && (
                        <p style={{ fontSize: 9, color: '#6b7280', lineHeight: 1.3, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                            {student.full_name_am}
                        </p>
                    )}
                    <p style={{ fontSize: 9, color: '#374151', marginTop: 2 }}>
                        <span style={{ color: '#9ca3af' }}>Class: </span>{student.class_name ?? '—'}
                    </p>
                    {student.date_of_birth && (
                        <p style={{ fontSize: 9, color: '#374151' }}>
                            <span style={{ color: '#9ca3af' }}>DOB: </span>{student.date_of_birth}
                        </p>
                    )}
                    {student.blood_type && (
                        <p style={{ fontSize: 9, color: '#374151' }}>
                            <span style={{ color: '#9ca3af' }}>Blood: </span><strong>{student.blood_type}</strong>
                        </p>
                    )}
                </div>

                {/* QR Code */}
                <div style={{ flexShrink: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 3 }}>
                    <div style={{ width: 52, height: 52 }} dangerouslySetInnerHTML={{ __html: student.qr_svg }} />
                    <p style={{ fontSize: 6, color: '#9ca3af', fontFamily: 'monospace', letterSpacing: .3, textAlign: 'center' }}>{student.uid}</p>
                </div>
            </div>

            {/* Footer stripe */}
            <div style={{ background: '#f9fafb', borderTop: '1px solid #f3f4f6', padding: '4px 12px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexShrink: 0 }}>
                <p style={{ fontSize: 7, color: '#9ca3af' }}>{school.code}</p>
                <p style={{ fontSize: 7, color: '#9ca3af' }}>{school.phone ?? ''}</p>
            </div>
        </div>
    );
}
