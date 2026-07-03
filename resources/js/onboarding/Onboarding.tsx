import React, { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    X, ChevronLeft, ChevronRight, Check, Sparkles, Rocket, PartyPopper,
} from 'lucide-react';
import type { PageProps } from '@/types';
import { planForRole, type OnboardingState } from './tours';

/* ── colour helpers (match the rest of the school panel) ───────── */
function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hex: string, a: number) {
    return `${normHex(hex)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

/** Persist a flag change to the backend without disrupting the page. */
function patchState(payload: Record<string, boolean>) {
    router.patch('/school/onboarding', payload, { preserveScroll: true, preserveState: true });
}

export default function Onboarding() {
    const { props } = usePage<PageProps>();
    const onboarding = (props as { onboarding?: OnboardingState | null }).onboarding ?? null;
    const role = (props.auth?.user as { role?: string } | undefined)?.role ?? '';
    const accent = normHex(props.accent);

    // Only school roles get onboarding; super admin / guests get null.
    if (!onboarding) return null;

    const plan = useMemo(() => planForRole(role), [role]);

    // Checklist completion is derived LIVE — always truthful.
    const tasks = plan.checklist.map((t) => ({ ...t, complete: t.done(onboarding) }));
    const doneCount = tasks.filter((t) => t.complete).length;
    const total = tasks.length;
    const pct = Math.round((doneCount / total) * 100);
    const allDone = doneCount === total;

    const [panelOpen, setPanelOpen] = useState(false);
    const [tourOpen, setTourOpen] = useState(false);
    const [celebrate, setCelebrate] = useState(false);
    const firstRun = useRef(true);

    // Auto-launch the welcome tour on first login, on the dashboard only.
    useEffect(() => {
        if (!firstRun.current) return;
        firstRun.current = false;
        const onDashboard = window.location.pathname.startsWith('/school/dashboard');
        if (!onboarding.tour_seen && !onboarding.dismissed && !onboarding.finished_at && onDashboard) {
            const t = setTimeout(() => setTourOpen(true), 650);
            return () => clearTimeout(t);
        }
        // Returning user who hasn't finished and hasn't dismissed → nudge the panel.
        if (onboarding.tour_seen && !onboarding.dismissed && !onboarding.finished_at) {
            const t = setTimeout(() => setPanelOpen(true), 500);
            return () => clearTimeout(t);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // Fire the celebration once, the first time every task is complete.
    useEffect(() => {
        if (allDone && !onboarding.finished_at) {
            setCelebrate(true);
            setPanelOpen(false);
            setTourOpen(false);
            patchState({ finished: true });
        }
    }, [allDone, onboarding.finished_at]);

    function closeTour(seen: boolean) {
        setTourOpen(false);
        if (seen && !onboarding.tour_seen) patchState({ tour_seen: true });
        // After the welcome tour, reveal the checklist so they know where to go.
        if (seen && !onboarding.finished_at) setTimeout(() => setPanelOpen(true), 250);
    }

    return (
        <>
            {tourOpen && (
                <CoachMarks
                    steps={plan.coach}
                    accent={accent}
                    onClose={() => closeTour(false)}
                    onFinish={() => closeTour(true)}
                />
            )}

            <Launcher
                accent={accent}
                pct={pct}
                doneCount={doneCount}
                total={total}
                open={panelOpen}
                finished={!!onboarding.finished_at}
                dismissed={onboarding.dismissed}
                tasks={tasks}
                onToggle={() => setPanelOpen((o) => !o)}
                onDismiss={() => { setPanelOpen(false); patchState({ dismissed: true }); }}
                onReplayTour={() => { setPanelOpen(false); setTourOpen(true); }}
            />

            <AnimatePresence>
                {celebrate && (
                    <Celebration accent={accent} onClose={() => setCelebrate(false)} />
                )}
            </AnimatePresence>
        </>
    );
}

/* ═══════════════ Coach-mark spotlight tour ═══════════════ */

function CoachMarks({
    steps, accent, onClose, onFinish,
}: {
    steps: ReturnType<typeof planForRole>['coach'];
    accent: string;
    onClose: () => void;
    onFinish: () => void;
}) {
    const [i, setI] = useState(0);
    const [rect, setRect] = useState<DOMRect | null>(null);
    const step = steps[i];
    const last = i === steps.length - 1;

    // Measure the anchored element (or null → centered card).
    useLayoutEffect(() => {
        let raf = 0;
        function measure() {
            if (!step.anchor) { setRect(null); return; }
            const el = document.querySelector<HTMLElement>(`[data-tour="${step.anchor}"]`);
            if (el) {
                el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                setRect(el.getBoundingClientRect());
            } else {
                setRect(null); // anchor hidden (e.g. mobile drawer) → center fallback
            }
        }
        measure();
        // Re-measure a couple of frames later so smooth-scroll settles.
        raf = requestAnimationFrame(() => setTimeout(measure, 260));
        window.addEventListener('resize', measure);
        window.addEventListener('scroll', measure, true);
        return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', measure, true);
        };
    }, [i, step.anchor]);

    // Keyboard: Esc skips, ←/→ navigate.
    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            if (e.key === 'Escape') onClose();
            if (e.key === 'ArrowRight') setI((n) => Math.min(n + 1, steps.length - 1));
            if (e.key === 'ArrowLeft') setI((n) => Math.max(n - 1, 0));
        }
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [steps.length, onClose]);

    const pad = 8;
    const hole = rect
        ? { top: rect.top - pad, left: rect.left - pad, width: rect.width + pad * 2, height: rect.height + pad * 2 }
        : null;

    // Tooltip placement: to the right of a sidebar item when there's room,
    // otherwise below it; centered when there's no anchor.
    const CARD_W = 320;
    let cardStyle: React.CSSProperties;
    if (!hole) {
        cardStyle = { top: '50%', left: '50%', transform: 'translate(-50%,-50%)' };
    } else if (hole.left + hole.width + 16 + CARD_W < window.innerWidth) {
        cardStyle = { top: Math.max(16, hole.top), left: hole.left + hole.width + 16 };
    } else if (hole.top + hole.height + 16 + 180 < window.innerHeight) {
        cardStyle = { top: hole.top + hole.height + 16, left: Math.min(hole.left, window.innerWidth - CARD_W - 16) };
    } else {
        cardStyle = { top: Math.max(16, hole.top - 200), left: Math.min(hole.left, window.innerWidth - CARD_W - 16) };
    }

    return (
        <div className="fixed inset-0 z-[70]" role="dialog" aria-modal="true">
            {/* Dim + spotlight hole via a big box-shadow around the cut-out. */}
            <motion.div
                key={i}
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                className="absolute rounded-2xl pointer-events-none"
                style={hole
                    ? {
                        top: hole.top, left: hole.left, width: hole.width, height: hole.height,
                        boxShadow: `0 0 0 9999px ${alpha('#0b1020', 0.72)}`,
                        outline: `2px solid ${accent}`,
                        outlineOffset: 2,
                    }
                    : { inset: 0, boxShadow: `inset 0 0 0 9999px ${alpha('#0b1020', 0.72)}`, borderRadius: 0 }}
            />

            {/* Click-catcher so the underlying UI can't be touched mid-tour. */}
            <div className="absolute inset-0" onClick={onClose} />

            {/* Tooltip card */}
            <motion.div
                key={`card-${i}`}
                initial={{ opacity: 0, y: 8, scale: 0.98 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                transition={{ duration: 0.18 }}
                className="absolute w-[320px] max-w-[calc(100vw-32px)] bg-white rounded-3xl p-5 shadow-2xl"
                style={cardStyle}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start justify-between mb-2">
                    <div className="flex items-center gap-2">
                        {step.emoji
                            ? <span className="text-2xl leading-none">{step.emoji}</span>
                            : <span className="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                                style={{ background: alpha(accent, 0.12), color: accent }}>
                                <Sparkles className="w-4 h-4" />
                            </span>}
                        <h3 className="text-base font-extrabold text-slate-900 leading-tight">{step.title}</h3>
                    </div>
                    <button onClick={onClose} className="p-1 -m-1 rounded-lg text-slate-400 hover:text-slate-700" aria-label="Skip tour">
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <p className="text-sm text-slate-600 leading-relaxed">{step.body}</p>

                {/* Progress dots */}
                <div className="flex items-center gap-1.5 mt-4 mb-3">
                    {steps.map((_, n) => (
                        <span key={n} className="h-1.5 rounded-full transition-all"
                            style={{
                                width: n === i ? 20 : 6,
                                background: n === i ? accent : n < i ? alpha(accent, 0.4) : '#e2e8f0',
                            }} />
                    ))}
                </div>

                <div className="flex items-center justify-between">
                    <button
                        onClick={onClose}
                        className="text-xs font-semibold text-slate-400 hover:text-slate-600"
                    >
                        Skip for now
                    </button>
                    <div className="flex items-center gap-2">
                        {i > 0 && (
                            <button
                                onClick={() => setI((n) => n - 1)}
                                className="flex items-center gap-1 px-3 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100"
                            >
                                <ChevronLeft className="w-3.5 h-3.5" /> Back
                            </button>
                        )}
                        <button
                            onClick={() => (last ? onFinish() : setI((n) => n + 1))}
                            className="flex items-center gap-1 px-4 py-2 rounded-xl text-xs font-bold text-white"
                            style={{ background: accent, boxShadow: `0 4px 12px ${alpha(accent, 0.35)}` }}
                        >
                            {last ? 'Finish' : 'Next'}
                            {!last && <ChevronRight className="w-3.5 h-3.5" />}
                        </button>
                    </div>
                </div>
            </motion.div>
        </div>
    );
}

/* ═══════════════ Getting-Started launcher + checklist ═══════════════ */

function Launcher({
    accent, pct, doneCount, total, open, finished, dismissed, tasks,
    onToggle, onDismiss, onReplayTour,
}: {
    accent: string;
    pct: number;
    doneCount: number;
    total: number;
    open: boolean;
    finished: boolean;
    dismissed: boolean;
    tasks: Array<{ id: string; label: string; desc: string; icon: React.ElementType; href?: string; replayTour?: boolean; complete: boolean }>;
    onToggle: () => void;
    onDismiss: () => void;
    onReplayTour: () => void;
}) {
    const R = 20, C = 2 * Math.PI * R;
    const nudge = !finished && !dismissed && !open;

    return (
        <>
            {/* Floating progress-ring button (always available). */}
            <motion.button
                initial={{ scale: 0, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ type: 'spring', stiffness: 320, damping: 22, delay: 0.3 }}
                onClick={onToggle}
                data-tour="onboarding-launcher"
                className="fixed bottom-5 right-5 z-[55] w-14 h-14 rounded-full flex items-center justify-center bg-white shadow-xl border border-slate-200/70"
                style={nudge ? { boxShadow: `0 0 0 0 ${alpha(accent, 0.5)}`, animation: 'eduid-pulse 2s infinite' } : undefined}
                title="Getting started"
                aria-label="Getting started checklist"
            >
                <svg width="52" height="52" viewBox="0 0 52 52" className="absolute -rotate-90">
                    <circle cx="26" cy="26" r={R} fill="none" stroke="#eef0f7" strokeWidth="3.5" />
                    <circle cx="26" cy="26" r={R} fill="none" stroke={accent} strokeWidth="3.5"
                        strokeLinecap="round" strokeDasharray={C}
                        strokeDashoffset={C - (C * pct) / 100}
                        style={{ transition: 'stroke-dashoffset .5s ease' }} />
                </svg>
                {finished
                    ? <Check className="w-5 h-5" style={{ color: accent }} strokeWidth={3} />
                    : <Rocket className="w-5 h-5" style={{ color: accent }} />}
            </motion.button>

            <AnimatePresence>
                {open && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                            className="fixed inset-0 z-[55]" onClick={onToggle}
                        />
                        <motion.div
                            initial={{ opacity: 0, y: 16, scale: 0.98 }}
                            animate={{ opacity: 1, y: 0, scale: 1 }}
                            exit={{ opacity: 0, y: 16, scale: 0.98 }}
                            transition={{ duration: 0.2 }}
                            className="fixed bottom-24 right-5 z-[56] w-[360px] max-w-[calc(100vw-24px)] bg-white rounded-3xl shadow-2xl border border-slate-200/60 overflow-hidden"
                        >
                            {/* Header with progress */}
                            <div className="p-5 pb-4" style={{ background: `linear-gradient(135deg, ${accent}, ${alpha(accent, 0.82)})` }}>
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-white font-extrabold text-lg leading-tight">
                                            {finished ? 'All done! 🎉' : 'Getting started'}
                                        </p>
                                        <p className="text-white/80 text-xs mt-0.5">
                                            {finished ? "You've set everything up." : `${doneCount} of ${total} complete`}
                                        </p>
                                    </div>
                                    <button onClick={onToggle} className="p-1 rounded-lg text-white/80 hover:bg-white/20" aria-label="Close">
                                        <X className="w-4 h-4" />
                                    </button>
                                </div>
                                <div className="mt-3 h-2 rounded-full bg-white/25 overflow-hidden">
                                    <div className="h-full rounded-full bg-white" style={{ width: `${pct}%`, transition: 'width .5s ease' }} />
                                </div>
                            </div>

                            {/* Tasks */}
                            <div className="p-3 max-h-[52vh] overflow-y-auto">
                                {tasks.map((t) => {
                                    const clickable = !t.complete && (t.href || t.replayTour);
                                    const onClick = () => {
                                        if (t.replayTour) return onReplayTour();
                                        if (t.href) router.visit(t.href);
                                    };
                                    return (
                                        <button
                                            key={t.id}
                                            onClick={clickable ? onClick : t.replayTour ? onReplayTour : undefined}
                                            disabled={!clickable && !t.replayTour}
                                            className={`w-full flex items-center gap-3 p-3 rounded-2xl text-left transition ${clickable || t.replayTour ? 'hover:bg-slate-50' : ''}`}
                                        >
                                            <span
                                                className="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                                                style={t.complete
                                                    ? { background: '#dcfce7', color: '#16a34a' }
                                                    : { background: alpha(accent, 0.10), color: accent }}
                                            >
                                                {t.complete ? <Check className="w-4 h-4" strokeWidth={3} /> : <t.icon className="w-4 h-4" />}
                                            </span>
                                            <span className="flex-1 min-w-0">
                                                <span className={`block text-sm font-bold ${t.complete ? 'text-slate-400 line-through' : 'text-slate-800'}`}>
                                                    {t.label}
                                                </span>
                                                <span className="block text-[11px] text-slate-400 truncate">{t.desc}</span>
                                            </span>
                                            {!t.complete && (t.href || t.replayTour) && (
                                                <ChevronRight className="w-4 h-4 text-slate-300 flex-shrink-0" />
                                            )}
                                        </button>
                                    );
                                })}
                            </div>

                            {/* Footer */}
                            <div className="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
                                <button onClick={onReplayTour} className="text-xs font-bold" style={{ color: accent }}>
                                    ↻ Replay the tour
                                </button>
                                {!finished && (
                                    <button onClick={onDismiss} className="text-xs font-semibold text-slate-400 hover:text-slate-600">
                                        Hide for now
                                    </button>
                                )}
                            </div>
                        </motion.div>
                    </>
                )}
            </AnimatePresence>

            {/* Pulse keyframes (scoped, injected once). */}
            <style>{`@keyframes eduid-pulse{0%{box-shadow:0 0 0 0 ${alpha(accent, 0.45)}}70%{box-shadow:0 0 0 14px ${alpha(accent, 0)}}100%{box-shadow:0 0 0 0 ${alpha(accent, 0)}}}`}</style>
        </>
    );
}

/* ═══════════════ Completion celebration + confetti ═══════════════ */

function Celebration({ accent, onClose }: { accent: string; onClose: () => void }) {
    // Auto-dismiss after a beat so it never blocks the user.
    useEffect(() => {
        const t = setTimeout(onClose, 6000);
        return () => clearTimeout(t);
    }, [onClose]);

    const colors = [accent, '#34d399', '#fbbf24', '#fb7185', '#22d3ee', '#a78bfa'];
    const pieces = useMemo(
        () => Array.from({ length: 90 }).map((_, i) => ({
            id: i,
            left: Math.random() * 100,
            delay: Math.random() * 0.6,
            duration: 2.4 + Math.random() * 1.8,
            color: colors[i % colors.length],
            size: 6 + Math.random() * 7,
            rotate: Math.random() * 360,
        })),
        [], // eslint-disable-line react-hooks/exhaustive-deps
    );

    return (
        <motion.div
            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
            className="fixed inset-0 z-[80] flex items-center justify-center p-4"
            style={{ background: alpha('#0b1020', 0.45) }}
            onClick={onClose}
        >
            {/* Confetti — CSP-safe DOM pieces, no external libs. */}
            <div className="absolute inset-0 overflow-hidden pointer-events-none">
                {pieces.map((p) => (
                    <motion.span
                        key={p.id}
                        initial={{ y: -40, opacity: 1, rotate: p.rotate }}
                        animate={{ y: '110vh', opacity: [1, 1, 0.9, 0], rotate: p.rotate + 360 }}
                        transition={{ duration: p.duration, delay: p.delay, ease: 'easeIn' }}
                        className="absolute rounded-[2px]"
                        style={{ left: `${p.left}%`, width: p.size, height: p.size * 1.4, background: p.color }}
                    />
                ))}
            </div>

            <motion.div
                initial={{ scale: 0.85, opacity: 0, y: 10 }}
                animate={{ scale: 1, opacity: 1, y: 0 }}
                transition={{ type: 'spring', stiffness: 260, damping: 20 }}
                className="relative bg-white rounded-3xl p-8 text-center shadow-2xl max-w-sm"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="w-16 h-16 rounded-2xl mx-auto flex items-center justify-center mb-4"
                    style={{ background: alpha(accent, 0.12), color: accent }}>
                    <PartyPopper className="w-8 h-8" />
                </div>
                <h2 className="text-2xl font-extrabold text-slate-900">You're all set up! 🎉</h2>
                <p className="text-sm text-slate-500 mt-2">
                    You've completed every setup step. Your school is ready to run on EduID —
                    nicely done.
                </p>
                <button
                    onClick={onClose}
                    className="mt-6 px-6 py-2.5 rounded-2xl text-sm font-bold text-white"
                    style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}
                >
                    Let's go
                </button>
            </motion.div>
        </motion.div>
    );
}
