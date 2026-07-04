import React from 'react';
import { Link } from '@inertiajs/react';
import { Inbox, AlertTriangle, ArrowLeft, RotateCw } from 'lucide-react';

/* ═══════════════════════════════════════════════════════════════
   Shared UI-state building blocks so every screen handles the four
   states consistently and professionally:
     · loading  → <Skeleton> / <SkeletonTable> / <SkeletonCards>
     · empty    → <EmptyState>
     · error    → <ErrorState>
     · success  → the real content (with these as the scaffolding)
   ═══════════════════════════════════════════════════════════════ */

/** A single shimmering placeholder block. */
export function Skeleton({ className = '', style }: { className?: string; style?: React.CSSProperties }) {
    return (
        <div
            className={`animate-pulse rounded-lg bg-slate-200/70 ${className}`}
            style={style}
            aria-hidden="true"
        />
    );
}

/** Placeholder for a data table while it loads. */
export function SkeletonTable({ rows = 6, cols = 4 }: { rows?: number; cols?: number }) {
    return (
        <div className="w-full" role="status" aria-label="Loading">
            <div className="flex gap-4 pb-3 border-b border-slate-100">
                {Array.from({ length: cols }).map((_, i) => <Skeleton key={i} className="h-3 flex-1" />)}
            </div>
            {Array.from({ length: rows }).map((_, r) => (
                <div key={r} className="flex gap-4 py-3.5 border-b border-slate-50">
                    {Array.from({ length: cols }).map((_, c) => (
                        <Skeleton key={c} className={`h-4 flex-1 ${c === 0 ? 'max-w-[40%]' : ''}`} />
                    ))}
                </div>
            ))}
        </div>
    );
}

/** Placeholder grid of cards (dashboards, stat tiles, lists). */
export function SkeletonCards({ count = 3 }: { count?: number }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" role="status" aria-label="Loading">
            {Array.from({ length: count }).map((_, i) => (
                <div key={i} className="rounded-3xl border border-slate-200/60 bg-white p-5">
                    <Skeleton className="h-10 w-10 rounded-2xl mb-4" />
                    <Skeleton className="h-3 w-1/3 mb-2" />
                    <Skeleton className="h-6 w-2/3" />
                </div>
            ))}
        </div>
    );
}

/** Friendly empty state with an optional primary action + guidance. */
export function EmptyState({
    icon: Icon = Inbox, title, description, actionLabel, actionHref, onAction, accent = '#6366f1',
}: {
    icon?: React.ElementType;
    title: string;
    description?: string;
    actionLabel?: string;
    actionHref?: string;
    onAction?: () => void;
    accent?: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center text-center py-14 px-6">
            <div className="w-14 h-14 rounded-2xl flex items-center justify-center mb-4" style={{ background: `${accent}14`, color: accent }}>
                <Icon className="w-7 h-7" />
            </div>
            <h3 className="text-base font-extrabold text-slate-800">{title}</h3>
            {description && <p className="text-sm text-slate-500 mt-1.5 max-w-sm">{description}</p>}
            {actionLabel && (actionHref || onAction) && (
                actionHref ? (
                    <Link href={actionHref} className="mt-5 inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-bold text-white" style={{ background: accent }}>
                        {actionLabel}
                    </Link>
                ) : (
                    <button onClick={onAction} className="mt-5 inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-bold text-white" style={{ background: accent }}>
                        {actionLabel}
                    </button>
                )
            )}
        </div>
    );
}

/** Friendly inline error state with retry / back guidance (no tech details). */
export function ErrorState({
    title = 'Something went wrong', description = 'We couldn’t load this section. Please try again.',
    onRetry, backHref,
}: {
    title?: string;
    description?: string;
    onRetry?: () => void;
    backHref?: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center text-center py-14 px-6">
            <div className="w-14 h-14 rounded-2xl flex items-center justify-center mb-4 bg-red-50 text-red-500">
                <AlertTriangle className="w-7 h-7" />
            </div>
            <h3 className="text-base font-extrabold text-slate-800">{title}</h3>
            <p className="text-sm text-slate-500 mt-1.5 max-w-sm">{description}</p>
            <div className="mt-5 flex items-center gap-2">
                {onRetry && (
                    <button onClick={onRetry} className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-slate-800 hover:bg-slate-700">
                        <RotateCw className="w-4 h-4" /> Try again
                    </button>
                )}
                {backHref && (
                    <Link href={backHref} className="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-bold text-slate-600 hover:bg-slate-100">
                        <ArrowLeft className="w-4 h-4" /> Go back
                    </Link>
                )}
            </div>
        </div>
    );
}
