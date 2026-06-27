import React, { useState, useRef, useEffect, useMemo } from 'react';
import { ArrowRight, Sparkles, Check } from 'lucide-react';
import { motion } from 'framer-motion';
import QRCode from 'qrcode';
import TemplateCard, { type TemplateValues } from '@/Components/TemplateCard';
import { buildSampleTemplate, buildAvatar, SAMPLE_FIELDS } from './sampleCardTemplate';

interface HeroSectionProps {
  onOpenDemo: () => void;
}

interface DemoState {
  name: string;
  nameAm: string;
  uid: string;
  grade: string;
  blood: string;
  schoolName: string;
  schoolCode: string;
  year: string;
  accent: string;
  avatarIndex: number;
}

const AVATARS = [
  { initials: 'AG', bg: '#d1fae5', fg: '#065f46' },
  { initials: 'TB', bg: '#dbeafe', fg: '#1e3a5f' },
  { initials: 'SH', bg: '#fce7f3', fg: '#9d174d' },
  { initials: 'YA', bg: '#fef3c7', fg: '#92400e' },
];

const BLOOD_TYPES = ['A+', 'B+', 'O+', 'AB+', 'A−', 'B−', 'O−', 'AB−'];
const ACCENTS = [
  { label: 'Emerald', value: '#059669' },
  { label: 'Indigo',  value: '#4f46e5' },
  { label: 'Amber',   value: '#d97706' },
  { label: 'Navy',    value: '#1e3a5f' },
];

export default function HeroSection({ onOpenDemo }: HeroSectionProps) {
  const [s, setS] = useState<DemoState>({
    name: 'Abebe Girma',
    nameAm: 'አበበ ግርማ',
    uid: 'AIA-2026-00142',
    grade: 'Grade 10 – Section A',
    blood: 'O+',
    schoolName: 'Abyssinia International Academy',
    schoolCode: 'AIA',
    year: '2025/26',
    accent: '#059669',
    avatarIndex: 0,
  });
  const [qrSvg, setQrSvg] = useState('');

  const cardRef = useRef<HTMLDivElement>(null);
  const [tilt, setTilt] = useState<React.CSSProperties>({ transform: 'perspective(1100px) rotateX(0deg) rotateY(0deg) scale(1)', transition: 'transform .5s ease-out' });

  // Real, scannable QR — same UID|token payload format used on printed cards.
  useEffect(() => {
    QRCode.toString(`${s.uid}|DEMO-TOKEN-${s.uid}`, { type: 'svg', margin: 0, color: { dark: '#0f172a', light: '#ffffff' } })
      .then(setQrSvg).catch(() => setQrSvg(''));
  }, [s.uid]);

  // Build the sample template (background follows the accent) with live blood type.
  const template = useMemo(() => {
    const t = buildSampleTemplate(s.accent, s.schoolCode);
    t.fields = SAMPLE_FIELDS.map(f =>
      f.field_key === 'custom_2' ? { ...f, custom_value: `Blood: ${s.blood}` } : f
    );
    return t;
  }, [s.accent, s.schoolCode, s.blood]);

  const avatar = AVATARS[s.avatarIndex];
  const values: TemplateValues = {
    full_name: s.name,
    full_name_am: s.nameAm,
    uid: s.uid,
    class_name: s.grade,
    academic_year: s.year,
    school_name: s.schoolName,
    photo_url: buildAvatar(avatar.initials, avatar.bg, avatar.fg),
    qr_svg: qrSvg,
  };

  const onMove = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!cardRef.current) return;
    const r = cardRef.current.getBoundingClientRect();
    const rx = (r.height / 2 - (e.clientY - r.top)) / 16;
    const ry = ((e.clientX - r.left) - r.width / 2) / 16;
    setTilt({ transform: `perspective(1100px) rotateX(${rx}deg) rotateY(${ry}deg) scale(1.04)`, transition: 'transform .1s ease-out' });
  };
  const onLeave = () => setTilt({ transform: 'perspective(1100px) rotateX(0deg) rotateY(0deg) scale(1)', transition: 'transform .6s ease-out' });

  const set = (k: keyof DemoState, v: string | number) => setS(prev => ({ ...prev, [k]: v }));

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
                <span className="inline-flex items-center gap-1.5 text-secondary font-semibold"><Check className="w-3.5 h-3.5" /> Arkeon prints &amp; ships cards</span>
                <span className="text-slate-300">·</span>
                <span className="inline-flex items-center gap-1.5 text-secondary font-semibold"><Check className="w-3.5 h-3.5" /> Delivered in 3 business days</span>
              </div>
            </motion.div>
          </motion.div>

          {/* Right: real sample card via the shared TemplateCard renderer */}
          <div className="lg:col-span-5 flex flex-col items-center justify-center gap-6 z-10 w-full">
            <div className="relative w-full max-w-[380px]">
              <div className="absolute -inset-4 rounded-[28px] blur-2xl opacity-60 transition-all duration-300" style={{ background: `${s.accent}22` }} />
              <div ref={cardRef} onMouseMove={onMove} onMouseLeave={onLeave} style={{ ...tilt, cursor: 'grab' }}>
                <TemplateCard template={template} values={values} displayWidth={380} accent={s.accent} preview={false} />
              </div>
            </div>

            {/* Compact customizer — drives the same renderer/values */}
            <div className="w-full bg-white rounded-2xl p-5 border border-slate-200 shadow-lg max-w-[380px]">
              <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                <Sparkles className="w-4 h-4 text-secondary" />
                <h5 className="font-display font-bold text-xs uppercase tracking-wider text-slate-500">Try the real card layout</h5>
              </div>
              <div className="space-y-3">
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Student Name</label>
                    <input type="text" value={s.name} onChange={e => set('name', e.target.value.slice(0, 28))} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="e.g. Abebe Girma" />
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Amharic Name</label>
                    <input type="text" value={s.nameAm} onChange={e => set('nameAm', e.target.value.slice(0, 28))} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="e.g. አበበ ግርማ" />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Grade / Class</label>
                    <input type="text" value={s.grade} onChange={e => set('grade', e.target.value.slice(0, 24))} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="Grade 10 – A" />
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Blood Type</label>
                    <select value={s.blood} onChange={e => set('blood', e.target.value)} className="w-full text-xs px-2 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary">
                      {BLOOD_TYPES.map(b => <option key={b} value={b}>{b}</option>)}
                    </select>
                  </div>
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">School Name</label>
                  <input type="text" value={s.schoolName} onChange={e => set('schoolName', e.target.value.slice(0, 40))} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary font-semibold" placeholder="e.g. Abyssinia International Academy" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Card Color</label>
                    <div className="flex gap-1.5 items-center h-9 px-2 bg-slate-50 rounded-lg border border-slate-200">
                      {ACCENTS.map(p => (
                        <button key={p.value} onClick={() => set('accent', p.value)} title={p.label} className="w-5 h-5 rounded-full transition-all"
                          style={{ background: p.value, outline: s.accent === p.value ? `2px solid ${p.value}` : 'none', outlineOffset: 2, transform: s.accent === p.value ? 'scale(1.2)' : 'scale(1)' }} />
                      ))}
                    </div>
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Avatar</label>
                    <div className="flex gap-1.5 items-center h-9 px-1 bg-slate-50 rounded-lg border border-slate-200">
                      {AVATARS.map((av, i) => (
                        <button key={i} onClick={() => set('avatarIndex', i)} className={`w-7 h-7 rounded-md text-[11px] font-bold transition-all flex items-center justify-center ${s.avatarIndex === i ? 'ring-2 ring-secondary scale-110' : ''}`} style={{ background: av.bg, color: av.fg }}>
                          {av.initials}
                        </button>
                      ))}
                    </div>
                  </div>
                </div>
              </div>
              <p className="mt-3 text-[10px] text-slate-400 text-center">
                This is the real card template &amp; field layout. The QR is genuinely scannable — it encodes the student UID exactly like a printed card.
              </p>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
