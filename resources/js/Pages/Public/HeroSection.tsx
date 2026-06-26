import React, { useState, useRef, useEffect } from 'react';
import { ArrowRight, Sparkles, Check, UserRound } from 'lucide-react';
import { motion } from 'framer-motion';
import QRCode from 'qrcode';

interface HeroSectionProps {
  onOpenDemo: () => void;
}

function normHex(c: string) {
  return /^#[0-9a-fA-F]{6}$/.test(c) ? c : '#6366f1';
}

// Deterministic UID from a demo token — matches the PRD payload format: UID|HMAC-token
function makeDemoPayload(uid: string) {
  // In production the token is an HMAC-SHA256 of UID+secret.
  // For the public demo we use a fixed demo token so the QR is always scannable
  // and decodes to something meaningful, but labels it clearly as DEMO.
  return `${uid}|DEMO-TOKEN-${uid}`;
}

interface DemoCard {
  name: string;
  nameAm: string;
  uid: string;
  grade: string;
  dob: string;
  blood: string;
  schoolName: string;
  schoolCode: string;
  year: string;
  accent: string;
  avatarIndex: number;
}

const AVATARS = [
  { initial: 'AM', bg: '#d1fae5' },
  { initial: 'BK', bg: '#dbeafe' },
  { initial: 'SL', bg: '#fce7f3' },
  { initial: 'YG', bg: '#fef3c7' },
];

const BLOOD_TYPES = ['A+', 'B+', 'O+', 'AB+', 'A−', 'B−', 'O−', 'AB−'];
const ACCENT_PALETTE = [
  { label: 'Emerald', value: '#059669' },
  { label: 'Indigo',  value: '#4f46e5' },
  { label: 'Amber',   value: '#d97706' },
  { label: 'Navy',    value: '#1e3a5f' },
];

export default function HeroSection({ onOpenDemo }: HeroSectionProps) {
  const [card, setCard] = useState<DemoCard>({
    name: 'Abebe Girma',
    nameAm: 'አበበ ግርማ',
    uid: 'AIA-2026-00142',
    grade: 'Grade 10 – Section A',
    dob: '2010-04-14',
    blood: 'O+',
    schoolName: 'Abyssinia International Academy',
    schoolCode: 'AIA',
    year: '2025/26',
    accent: '#059669',
    avatarIndex: 0,
  });
  const [qrSvg, setQrSvg] = useState('');

  const cardRef = useRef<HTMLDivElement>(null);
  const [tiltStyle, setTiltStyle] = useState<React.CSSProperties>({
    transform: 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)',
    transition: 'transform 0.5s ease-out',
  });
  const [shimmerStyle, setShimmerStyle] = useState<React.CSSProperties>({ opacity: 0.3, backgroundPosition: '50% 50%' });

  // Regenerate QR whenever UID changes
  useEffect(() => {
    const payload = makeDemoPayload(card.uid);
    QRCode.toString(payload, { type: 'svg', width: 52, margin: 0, color: { dark: '#111827', light: '#ffffff' } })
      .then(setQrSvg)
      .catch(() => setQrSvg(''));
  }, [card.uid]);

  const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!cardRef.current) return;
    const rect = cardRef.current.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const rotX = (rect.height / 2 - y) / 14;
    const rotY = (x - rect.width / 2) / 14;
    setTiltStyle({ transform: `perspective(1000px) rotateX(${rotX}deg) rotateY(${rotY}deg) scale(1.04)`, transition: 'transform 0.1s ease-out' });
    setShimmerStyle({ backgroundPosition: `${(x / rect.width) * 100}% ${(y / rect.height) * 100}%`, opacity: 0.55 });
  };
  const handleMouseLeave = () => {
    setTiltStyle({ transform: 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)', transition: 'transform 0.6s ease-out' });
    setShimmerStyle({ opacity: 0.3, backgroundPosition: '50% 50%' });
  };

  const set = (key: keyof DemoCard, val: string | number) =>
    setCard(prev => ({ ...prev, [key]: val }));

  const accent = normHex(card.accent);
  const avatar = AVATARS[card.avatarIndex];

  return (
    <section className="relative pt-36 pb-16 md:pt-40 md:pb-24 lg:pt-48 lg:pb-32 overflow-hidden bg-gradient-to-b from-white via-slate-50/50 to-white">
      <div className="absolute top-0 right-0 w-1/2 h-1/2 bg-secondary/5 blur-[120px] rounded-full -translate-y-1/4 translate-x-1/4 pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-1/3 h-1/2 bg-primary/5 blur-[100px] rounded-full translate-y-1/4 -translate-x-1/4 pointer-events-none" />

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-12 gap-12 lg:gap-16 items-center">

          {/* Left copy */}
          <motion.div
            className="lg:col-span-7 text-center lg:text-left z-10"
            variants={{ hidden: { opacity: 0 }, visible: { opacity: 1, transition: { staggerChildren: 0.1, delayChildren: 0.1 } } }}
            initial="hidden"
            animate="visible"
          >
            <motion.div
              variants={{ hidden: { opacity: 0, y: 15 }, visible: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 100 } } }}
              className="inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-secondary/10 text-secondary border border-secondary/20 shadow-sm"
            >
              <span className="flex h-2 w-2 rounded-full bg-secondary" />
              <span className="font-display text-[10px] font-bold uppercase tracking-[0.2em]">Built for Ethiopian Schools</span>
            </motion.div>

            <motion.h1
              variants={{ hidden: { opacity: 0, y: 25 }, visible: { opacity: 1, y: 0, transition: { duration: 0.7, ease: 'easeOut' } } }}
              className="font-display text-[38px] sm:text-[48px] md:text-[62px] lg:text-[68px] xl:text-[74px] mb-6 text-primary leading-[1.08] tracking-tight font-extrabold"
            >
              Smart PVC ID Cards &amp;{' '}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-secondary to-emerald-600">QR Attendance</span>{' '}
              for Your School.
            </motion.h1>

            <motion.p
              variants={{ hidden: { opacity: 0, y: 20 }, visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: 'easeOut' } } }}
              className="font-sans text-base sm:text-lg md:text-xl text-slate-600 mb-10 max-w-xl mx-auto lg:mx-0 leading-relaxed"
            >
              Register your students, order professional PVC cards, and take QR-based attendance with our teacher mobile app — all in one platform. No paper registers. No manual data entry.
            </motion.p>

            <motion.div
              variants={{ hidden: { opacity: 0, y: 20 }, visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: 'easeOut' } } }}
              className="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start items-center"
            >
              <button
                onClick={onOpenDemo}
                className="group w-full sm:w-auto bg-primary hover:bg-slate-800 text-white px-8 py-4 rounded-xl font-display text-sm font-semibold flex items-center justify-center gap-2.5 transition-all shadow-xl hover:scale-[1.02] active:scale-95 duration-150"
              >
                <span>Request a Free Demo</span>
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </button>
              <a
                href="#how-it-works"
                className="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-4 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 font-sans text-sm font-semibold transition-all shadow-sm active:scale-95 duration-150"
              >
                See How It Works
              </a>
            </motion.div>

            <motion.div
              variants={{ hidden: { opacity: 0 }, visible: { opacity: 1, transition: { delay: 0.5, duration: 0.6 } } }}
              className="mt-12 pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center gap-3 justify-center lg:justify-start"
            >
              <div className="flex items-center gap-3 text-sm text-slate-500">
                <span className="inline-flex items-center gap-1.5 text-secondary font-semibold"><Check className="w-3.5 h-3.5" /> Arkeon prints & ships cards</span>
                <span className="text-slate-300">·</span>
                <span className="inline-flex items-center gap-1.5 text-secondary font-semibold"><Check className="w-3.5 h-3.5" /> Delivered in 3 business days</span>
              </div>
            </motion.div>
          </motion.div>

          {/* Right: real ID card preview + customizer */}
          <div className="lg:col-span-5 flex flex-col items-center justify-center gap-6 z-10 w-full">

            {/* Card preview — mirrors the real IdCard.tsx layout exactly */}
            <div className="relative w-full max-w-[360px]">
              <div
                className="absolute -inset-4 rounded-[28px] blur-2xl opacity-60 transition-all duration-300"
                style={{ background: `${accent}22` }}
              />
              <div
                ref={cardRef}
                onMouseMove={handleMouseMove}
                onMouseLeave={handleMouseLeave}
                style={{
                  ...tiltStyle,
                  position: 'relative',
                  width: '100%',
                  aspectRatio: '342 / 216',
                  borderRadius: 8,
                  boxShadow: '0 20px 50px rgba(15,23,42,0.18)',
                  overflow: 'hidden',
                  fontFamily: "'Outfit', sans-serif",
                  display: 'flex',
                  flexDirection: 'column',
                  background: 'white',
                  cursor: 'grab',
                  userSelect: 'none',
                }}
              >
                {/* Preview watermark */}
                <div
                  aria-hidden="true"
                  style={{
                    position: 'absolute', inset: 0, zIndex: 5, pointerEvents: 'none',
                    backgroundImage: `repeating-linear-gradient(-45deg, ${accent}1a 0, ${accent}1a 2px, transparent 2px, transparent 60px)`,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                  }}
                >
                  <span style={{ transform: 'rotate(-22deg)', fontSize: 22, fontWeight: 800, letterSpacing: 4, color: `${accent}47`, textTransform: 'uppercase', whiteSpace: 'nowrap' }}>
                    Preview
                  </span>
                </div>

                {/* Header stripe */}
                <div style={{ background: accent, padding: '8px 12px', display: 'flex', alignItems: 'center', gap: 8, flexShrink: 0 }}>
                  <div style={{ width: 28, height: 28, borderRadius: 4, background: 'rgba(255,255,255,.25)', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 11, fontWeight: 700, color: 'white', flexShrink: 0 }}>
                    {card.schoolCode.slice(0, 2).toUpperCase()}
                  </div>
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <p style={{ color: 'white', fontSize: 9, fontWeight: 700, lineHeight: 1.2, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{card.schoolName}</p>
                    <p style={{ color: 'rgba(255,255,255,.75)', fontSize: 7, lineHeight: 1.2 }}>{card.year}</p>
                  </div>
                  <p style={{ color: 'rgba(255,255,255,.85)', fontSize: 7, fontWeight: 600, letterSpacing: 0.5, textTransform: 'uppercase', whiteSpace: 'nowrap' }}>Student ID</p>
                </div>

                {/* Body */}
                <div style={{ flex: 1, display: 'flex', padding: '10px 12px', gap: 10, overflow: 'hidden' }}>
                  {/* Avatar */}
                  <div style={{ flexShrink: 0 }}>
                    <div style={{ width: 64, height: 80, borderRadius: 4, border: '1.5px solid #e5e7eb', background: avatar.bg, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 20, fontWeight: 700, color: '#374151' }}>
                      {avatar.initial}
                    </div>
                  </div>

                  {/* Info */}
                  <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 3 }}>
                    <p style={{ fontSize: 12, fontWeight: 700, color: '#111827', lineHeight: 1.3, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {card.name || 'Student Name'}
                    </p>
                    {card.nameAm && (
                      <p style={{ fontSize: 9, color: '#6b7280', lineHeight: 1.3, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                        {card.nameAm}
                      </p>
                    )}
                    <p style={{ fontSize: 9, color: '#374151', marginTop: 2 }}>
                      <span style={{ color: '#9ca3af' }}>Class: </span>{card.grade || '—'}
                    </p>
                    <p style={{ fontSize: 9, color: '#374151' }}>
                      <span style={{ color: '#9ca3af' }}>DOB: </span>{card.dob}
                    </p>
                    <p style={{ fontSize: 9, color: '#374151' }}>
                      <span style={{ color: '#9ca3af' }}>Blood: </span><strong>{card.blood}</strong>
                    </p>
                  </div>

                  {/* Real QR */}
                  <div style={{ flexShrink: 0, display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', gap: 3 }}>
                    {qrSvg
                      ? <div style={{ width: 52, height: 52 }} dangerouslySetInnerHTML={{ __html: qrSvg }} />
                      : <div style={{ width: 52, height: 52, background: '#f3f4f6', borderRadius: 4 }} />
                    }
                    <p style={{ fontSize: 6, color: '#9ca3af', fontFamily: 'monospace', letterSpacing: 0.3, textAlign: 'center' }}>{card.uid}</p>
                  </div>
                </div>

                {/* Footer */}
                <div style={{ background: '#f9fafb', borderTop: '1px solid #f3f4f6', padding: '4px 12px', display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexShrink: 0 }}>
                  <p style={{ fontSize: 7, color: '#9ca3af' }}>{card.schoolCode}</p>
                  <p style={{ fontSize: 7, color: '#9ca3af' }}>EduID · Arkeon Ethiopia</p>
                </div>

                {/* Shimmer */}
                <div
                  className="absolute inset-0 pointer-events-none mix-blend-overlay transition-opacity duration-300"
                  style={{ background: 'linear-gradient(135deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.15) 50%, rgba(255,255,255,0) 100%)', backgroundSize: '200% 200%', ...shimmerStyle }}
                />
              </div>
            </div>

            {/* Customizer panel */}
            <div className="w-full bg-white rounded-2xl p-5 border border-slate-200 shadow-lg max-w-[360px]">
              <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                <Sparkles className="w-4 h-4 text-secondary" />
                <h5 className="font-display font-bold text-xs uppercase tracking-wider text-slate-500">Card Preview — Try It Live</h5>
              </div>
              <div className="space-y-3">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Student Name</label>
                    <input
                      type="text"
                      value={card.name}
                      onChange={e => set('name', e.target.value.slice(0, 28))}
                      className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary"
                      placeholder="e.g. Abebe Girma"
                    />
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Amharic Name</label>
                    <input
                      type="text"
                      value={card.nameAm}
                      onChange={e => set('nameAm', e.target.value.slice(0, 28))}
                      className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary"
                      placeholder="e.g. አበበ ግርማ"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Grade / Class</label>
                    <input
                      type="text"
                      value={card.grade}
                      onChange={e => set('grade', e.target.value.slice(0, 24))}
                      className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary"
                      placeholder="Grade 10 – A"
                    />
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Blood Type</label>
                    <select
                      value={card.blood}
                      onChange={e => set('blood', e.target.value)}
                      className="w-full text-xs px-2 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary"
                    >
                      {BLOOD_TYPES.map(b => <option key={b} value={b}>{b}</option>)}
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">School Name</label>
                  <input
                    type="text"
                    value={card.schoolName}
                    onChange={e => set('schoolName', e.target.value.slice(0, 40))}
                    className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary font-semibold"
                    placeholder="e.g. Abyssinia International Academy"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Card Color</label>
                    <div className="flex gap-1.5 items-center h-9 px-2 bg-slate-50 rounded-lg border border-slate-200">
                      {ACCENT_PALETTE.map(p => (
                        <button
                          key={p.value}
                          onClick={() => set('accent', p.value)}
                          title={p.label}
                          className="w-5 h-5 rounded-full border transition-all"
                          style={{
                            background: p.value,
                            outline: card.accent === p.value ? `2px solid ${p.value}` : 'none',
                            outlineOffset: 2,
                            transform: card.accent === p.value ? 'scale(1.2)' : 'scale(1)',
                          }}
                        />
                      ))}
                    </div>
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Avatar</label>
                    <div className="flex gap-1.5 items-center h-9 px-1 bg-slate-50 rounded-lg border border-slate-200">
                      {AVATARS.map((av, i) => (
                        <button
                          key={i}
                          onClick={() => set('avatarIndex', i)}
                          className={`w-7 h-7 rounded-md text-xs font-bold transition-all flex items-center justify-center border ${card.avatarIndex === i ? 'ring-2 ring-secondary scale-110 border-secondary/20' : 'border-transparent'}`}
                          style={{ background: av.bg }}
                        >
                          {av.initial}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
              <p className="mt-3 text-[10px] text-slate-400 text-center">
                The QR above is real and scannable — it encodes the student UID in the same format used on printed cards.
              </p>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
