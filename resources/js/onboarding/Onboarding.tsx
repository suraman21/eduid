import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { X, ChevronLeft, ChevronRight, Sparkles, HelpCircle } from 'lucide-react';
import type { PageProps } from '@/types';
import { coachForRole, type CoachStep, type OnboardingState } from './tours';

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

/**
 * The onboarding welcome tour: a short, skippable coach-mark walkthrough that
 * auto-runs once on first login and can be replayed anytime from a small help
 * button. No checklist, no gamification, no task nagging.
 */
export default function Onboarding() {
    const { props } = usePage<PageProps>();
    const onboarding = (props as { onboarding?: OnboardingState | null }).onboarding ?? null;
    const role = (props.auth?.user as { role?: string } | undefined)?.role ?? '';
    const accent = normHex(props.accent);

    // Only school roles get onboarding; super admin / guests get null.
    if (!onboarding) return null;

    const steps = coachForRole(role);
    const [tourOpen, setTourOpen] = useState(false);
    const firstRun = useRef(true);

    // Auto-launch the welcome tour once, on the dashboard, for a fresh user.
    useEffect(() => {
        if (!firstRun.current) return;
        firstRun.current = false;
        const onDashboard = window.location.pathname.startsWith('/school/dashboard');
        if (!onboarding.tour_seen && !onboarding.dismissed && onDashboard) {
            const t = setTimeout(() => setTourOpen(true), 650);
            return () => clearTimeout(t);
        }
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    function closeTour(finished: boolean) {
        setTourOpen(false);
        // Mark seen whether they finished or skipped — either way we won't
        // auto-pop it again. The help button lets them reopen deliberately.
        if (!onboarding.tour_seen) patchState({ tour_seen: true, dismissed: !finished });
    }

    return (
        <>
            {tourOpen && (
                <CoachMarks
                    steps={steps}
                    accent={accent}
                    onClose={() => closeTour(false)}
                    onFinish={() => closeTour(true)}
                />
            )}

            {/* Persistent, unobtrusive "replay tour" help button. */}
            {!tourOpen && (
                <button
                    onClick={() => setTourOpen(true)}
                    className="fixed bottom-5 right-5 z-40 flex items-center gap-2 px-4 py-2.5 rounded-full bg-white shadow-lg border border-slate-200/70 text-sm font-bold text-slate-600 hover:text-slate-900 hover:shadow-xl transition-all"
                    aria-label="Take a quick tour"
                >
                    <HelpCircle className="w-4 h-4" style={{ color: accent }} />
                    <span className="hidden sm:inline">Tour</span>
                </button>
            )}
        </>
    );
}

/* ═══════════════ Coach-mark spotlight tour ═══════════════ */

function CoachMarks({
    steps, accent, onClose, onFinish,
}: {
    steps: CoachStep[];
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
                            {last ? 'Done' : 'Next'}
                            {!last && <ChevronRight className="w-3.5 h-3.5" />}
                        </button>
                    </div>
                </div>
            </motion.div>
        </div>
    );
}
