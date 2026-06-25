import React, { useState, useRef } from 'react';
import { IdCard, ArrowRight, Nfc, QrCode, Sparkles, Check } from 'lucide-react';
import { motion } from 'framer-motion';

interface HeroSectionProps {
  onOpenDemo: () => void;
}

export default function HeroSection({ onOpenDemo }: HeroSectionProps) {
  const [studentName, setStudentName] = useState('Sarah Jenkins');
  const [studentRole, setStudentRole] = useState<'STUDENT' | 'STAFF' | 'FACULTY' | 'ADMIN'>('STUDENT');
  const [studentId, setStudentId] = useState('AIA-54321');
  const [academicYear, setAcademicYear] = useState('2025/26');
  const [schoolName, setSchoolName] = useState('Abyssinia Academy');
  const [accentColor, setAccentColor] = useState<'emerald' | 'navy' | 'indigo' | 'amber'>('emerald');
  const [showQr, setShowQr] = useState(true);
  const [avatarIndex, setAvatarIndex] = useState(0);

  const avatars = [
    'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&q=80&w=200&h=200',
    'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&q=80&w=200&h=200',
    'https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&q=80&w=200&h=200',
    'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?auto=format&fit=crop&q=80&w=200&h=200',
  ];

  const cardRef = useRef<HTMLDivElement>(null);
  const [tiltStyle, setTiltStyle] = useState<React.CSSProperties>({
    transform: 'rotateX(0deg) rotateY(0deg) scale(1)',
    transition: 'transform 0.5s ease-out',
  });
  const [shimmerStyle, setShimmerStyle] = useState<React.CSSProperties>({
    opacity: 0.3,
    backgroundPosition: '50% 50%',
  });

  const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
    if (!cardRef.current) return;
    const rect = cardRef.current.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const rotateX = (rect.height / 2 - y) / 12;
    const rotateY = (x - rect.width / 2) / 12;
    setTiltStyle({ transform: `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.05)`, transition: 'transform 0.1s ease-out' });
    setShimmerStyle({ backgroundPosition: `${(x / rect.width) * 100}% ${(y / rect.height) * 100}%`, opacity: 0.6 });
  };

  const handleMouseLeave = () => {
    setTiltStyle({ transform: 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale(1)', transition: 'transform 0.6s ease-out' });
    setShimmerStyle({ backgroundPosition: '50% 50%', opacity: 0.3 });
  };

  const getAccentColorClasses = () => {
    switch (accentColor) {
      case 'navy':   return { text: 'text-primary', bg: 'bg-primary/10', border: 'border-primary/20', badgeText: 'text-white', badgeBg: 'bg-primary', glow: 'bg-primary/10' };
      case 'indigo': return { text: 'text-indigo-600', bg: 'bg-indigo-50', border: 'border-indigo-100', badgeText: 'text-indigo-700', badgeBg: 'bg-indigo-100', glow: 'bg-indigo-500/10' };
      case 'amber':  return { text: 'text-amber-600', bg: 'bg-amber-50', border: 'border-amber-100', badgeText: 'text-amber-700', badgeBg: 'bg-amber-100', glow: 'bg-amber-500/10' };
      default:       return { text: 'text-secondary', bg: 'bg-secondary/10', border: 'border-secondary/20', badgeText: 'text-emerald-800', badgeBg: 'bg-emerald-100', glow: 'bg-secondary/10' };
    }
  };

  const currentAccent = getAccentColorClasses();

  return (
    <section className="relative pt-36 pb-16 md:pt-40 md:pb-24 lg:pt-48 lg:pb-32 overflow-hidden bg-gradient-to-b from-white via-slate-50/50 to-white">
      <div className="absolute top-0 right-0 w-1/2 h-1/2 bg-secondary/5 blur-[120px] rounded-full -translate-y-1/4 translate-x-1/4 pointer-events-none" />
      <div className="absolute bottom-0 left-0 w-1/3 h-1/2 bg-primary/5 blur-[100px] rounded-full translate-y-1/4 -translate-x-1/4 pointer-events-none" />

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid lg:grid-cols-12 gap-12 lg:gap-16 items-center">

          {/* Left content */}
          <motion.div
            className="lg:col-span-7 text-center lg:text-left z-10"
            variants={{ hidden: { opacity: 0 }, visible: { opacity: 1, transition: { staggerChildren: 0.1, delayChildren: 0.1 } } }}
            initial="hidden"
            animate="visible"
          >
            <motion.div
              variants={{ hidden: { opacity: 0, y: 15 }, visible: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 100 } } }}
              className="inline-flex items-center gap-2 px-3 py-1 mb-6 rounded-full bg-secondary/10 text-secondary border border-secondary/20 shadow-sm animate-pulse-slow"
            >
              <span className="flex h-2 w-2 rounded-full bg-secondary" />
              <span className="font-display text-[10px] font-bold uppercase tracking-[0.2em]">Education 4.0 Standard</span>
            </motion.div>

            <motion.h1
              variants={{ hidden: { opacity: 0, y: 25 }, visible: { opacity: 1, y: 0, transition: { duration: 0.7, ease: 'easeOut' } } }}
              className="font-display text-[38px] sm:text-[48px] md:text-[62px] lg:text-[68px] xl:text-[74px] mb-6 text-primary leading-[1.08] tracking-tight font-extrabold"
            >
              Modern Identity for the{' '}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-secondary to-emerald-600">Next Generation</span>{' '}
              of Schools.
            </motion.h1>

            <motion.p
              variants={{ hidden: { opacity: 0, y: 20 }, visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: 'easeOut' } } }}
              className="font-sans text-base sm:text-lg md:text-xl text-slate-600 mb-10 max-w-xl mx-auto lg:mx-0 leading-relaxed"
            >
              Replace manual registers with smart PVC ID cards and instant QR attendance. Secure, real-time, and built for modern campuses.
            </motion.p>

            <motion.div
              variants={{ hidden: { opacity: 0, y: 20 }, visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: 'easeOut' } } }}
              className="flex flex-col sm:flex-row gap-4 justify-center lg:justify-start items-center"
            >
              <button
                onClick={onOpenDemo}
                className="group w-full sm:w-auto bg-primary hover:bg-slate-800 text-white px-8 py-4 rounded-xl font-display text-sm font-semibold flex items-center justify-center gap-2.5 transition-all shadow-xl hover:scale-[1.02] active:scale-95 duration-150"
              >
                <span>Get Started - Free Demo</span>
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
              className="mt-12 pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center gap-4 justify-center lg:justify-start"
            >
              <div className="flex -space-x-3.5">
                {[
                  'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=100&h=100',
                  'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&q=80&w=100&h=100',
                  'https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=100&h=100',
                ].map((url, i) => (
                  <img key={i} src={url} className="w-10 h-10 rounded-full border-2 border-white object-cover shadow-sm" alt="" />
                ))}
                <div className="w-10 h-10 rounded-full bg-secondary/15 border-2 border-white flex items-center justify-center text-xs font-bold text-secondary shadow-sm">+250</div>
              </div>
              <p className="text-sm text-slate-500 font-medium text-center sm:text-left">
                Trusted by <span className="text-primary font-bold">250+</span> forward-thinking institutions nationwide
              </p>
            </motion.div>
          </motion.div>

          {/* Right visual */}
          <div className="lg:col-span-5 flex flex-col items-center justify-center gap-8 z-10 w-full">
            <div className="relative w-full max-w-[380px] aspect-[1.58/1]">
              <div className={`absolute -inset-4 rounded-[28px] ${currentAccent.glow} blur-2xl opacity-70 transition-all duration-300`} />
              <div
                ref={cardRef}
                onMouseMove={handleMouseMove}
                onMouseLeave={handleMouseLeave}
                style={tiltStyle}
                className="relative w-full h-full bg-white rounded-[20px] shadow-[0_20px_50px_rgba(15,23,42,0.15)] overflow-hidden border border-slate-200/60 select-none cursor-grab active:cursor-grabbing"
              >
                <div className="absolute inset-0 p-6 flex flex-col justify-between z-10">
                  <div className="flex justify-between items-start">
                    <div className="flex items-center gap-2">
                      <div className="p-1.5 bg-primary rounded-md text-white flex items-center justify-center">
                        <IdCard className="w-4 h-4" />
                      </div>
                      <span className="font-display font-black text-xs text-primary tracking-tight">EDUID</span>
                    </div>
                    <div className={`w-8 h-8 rounded-full ${currentAccent.bg} flex items-center justify-center transition-all duration-300`}>
                      <Nfc className={`w-4 h-4 ${currentAccent.text}`} />
                    </div>
                  </div>

                  <div className="flex gap-4 items-center">
                    <div className="relative w-20 h-20 rounded-xl bg-slate-100 overflow-hidden border border-slate-200 shadow-inner flex-shrink-0">
                      <img src={avatars[avatarIndex]} alt={studentName} className="w-full h-full object-cover" />
                    </div>
                    <div className="min-w-0 flex-1">
                      <h4 className="font-display font-black text-lg text-primary leading-none truncate tracking-tight">{studentName || 'Your Name'}</h4>
                      <span className={`inline-flex items-center px-2 py-0.5 mt-1 rounded text-[9px] font-black tracking-wider uppercase ${currentAccent.badgeBg} ${currentAccent.badgeText} transition-all duration-300`}>{studentRole}</span>
                      <div className="flex flex-wrap gap-1.5 mt-2.5">
                        <span className="text-[9px] font-mono font-bold text-slate-500 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100 inline-block">ID: {studentId || 'AIA-54321'}</span>
                        <span className="text-[9px] font-mono font-bold text-slate-500 bg-slate-50 px-1.5 py-0.5 rounded border border-slate-100 inline-block">YEAR: {academicYear || '2025/26'}</span>
                      </div>
                    </div>
                  </div>

                  <div className="flex justify-between items-end gap-3">
                    <div className="space-y-0.5 min-w-0 flex-1">
                      <p className="text-[8px] text-slate-400 uppercase font-black tracking-widest leading-none">Institution</p>
                      <p className="text-sm font-black text-primary tracking-tight uppercase leading-none font-display truncate">{schoolName || 'Abyssinia Academy'}</p>
                    </div>
                    {showQr && (
                      <div className="p-2 bg-white rounded-xl shadow-md border border-slate-200 hover:scale-105 transition-transform duration-200 flex-shrink-0">
                        <QrCode className="w-10 h-10 text-slate-800" />
                      </div>
                    )}
                  </div>
                </div>

                <div
                  className="absolute inset-0 pointer-events-none mix-blend-overlay transition-opacity duration-300"
                  style={{ background: `linear-gradient(135deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.15) 50%, rgba(255,255,255,0) 100%)`, backgroundSize: '200% 200%', ...shimmerStyle }}
                />
                <div className="absolute inset-0 bg-gradient-to-tr from-transparent via-slate-50/10 to-slate-100/20 pointer-events-none" />
              </div>
            </div>

            {/* Customizer */}
            <div className="w-full bg-white rounded-2xl p-5 border border-slate-200 shadow-lg max-w-[380px]">
              <div className="flex items-center gap-2 mb-4 border-b border-slate-100 pb-3">
                <Sparkles className="w-4 h-4 text-secondary" />
                <h5 className="font-display font-bold text-xs uppercase tracking-wider text-slate-500">Card Sandbox Simulator</h5>
              </div>
              <div className="space-y-3">
                <div>
                  <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Student / Staff Name</label>
                  <input type="text" value={studentName} onChange={e => setStudentName(e.target.value.slice(0, 24))} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition-all" placeholder="Enter Name" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">ID Number</label>
                    <input type="text" value={studentId} onChange={e => setStudentId(e.target.value.slice(0, 16))} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition-all font-mono" placeholder="e.g. AIA-54321" />
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Academic Year</label>
                    <input type="text" value={academicYear} onChange={e => setAcademicYear(e.target.value.slice(0, 12))} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition-all font-mono" placeholder="e.g. 2025/26" />
                  </div>
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">School / Institution Name</label>
                  <input type="text" value={schoolName} onChange={e => setSchoolName(e.target.value.slice(0, 32))} className="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition-all font-semibold" placeholder="e.g. Abyssinia Academy" />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Role Type</label>
                    <select value={studentRole} onChange={e => setStudentRole(e.target.value as any)} className="w-full text-xs px-2 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition-all font-medium">
                      <option value="STUDENT">Student</option>
                      <option value="STAFF">Staff</option>
                      <option value="FACULTY">Faculty</option>
                      <option value="ADMIN">Admin</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Design Color</label>
                    <div className="flex gap-1.5 items-center justify-between h-9 px-1 bg-slate-50 rounded-lg border border-slate-200">
                      {(['emerald', 'navy', 'indigo', 'amber'] as const).map(color => (
                        <button
                          key={color}
                          onClick={() => setAccentColor(color)}
                          className={`w-5 h-5 rounded-full border transition-all ${
                            color === 'emerald' ? 'bg-secondary' : color === 'navy' ? 'bg-primary' : color === 'indigo' ? 'bg-indigo-600' : 'bg-amber-500'
                          } ${accentColor === color ? 'ring-2 ring-offset-2 ring-slate-400 scale-110 border-white' : 'border-transparent'}`}
                          title={color}
                        />
                      ))}
                    </div>
                  </div>
                </div>
                <div>
                  <label className="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Change Avatar Photo</label>
                  <div className="flex gap-2 items-center">
                    {avatars.map((url, i) => (
                      <button key={i} onClick={() => setAvatarIndex(i)} className={`relative w-8 h-8 rounded-lg overflow-hidden border-2 transition-all ${avatarIndex === i ? 'border-secondary scale-105 shadow-sm' : 'border-transparent hover:border-slate-300'}`}>
                        <img src={url} alt="" className="w-full h-full object-cover" />
                        {avatarIndex === i && (
                          <div className="absolute inset-0 bg-secondary/20 flex items-center justify-center">
                            <Check className="w-4 h-4 text-white font-bold" />
                          </div>
                        )}
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
