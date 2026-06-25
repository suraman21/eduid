import React from 'react';

export type Tone = 'green' | 'amber' | 'rose' | 'blue' | 'purple' | 'slate';

interface Props {
    tone?: Tone;
    children: React.ReactNode;
    dot?: boolean;
    className?: string;
}

const tones: Record<Tone, string> = {
    green:  'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    amber:  'bg-amber-500/15 text-amber-600 dark:text-amber-400',
    rose:   'bg-rose-500/15 text-rose-600 dark:text-rose-400',
    blue:   'bg-blue-500/15 text-blue-600 dark:text-blue-400',
    purple: 'bg-purple-500/15 text-purple-600 dark:text-purple-400',
    slate:  'bg-slate-500/15 text-slate-600 dark:text-slate-400',
};

const dots: Record<Tone, string> = {
    green: 'bg-emerald-500', amber: 'bg-amber-500', rose: 'bg-rose-500',
    blue: 'bg-blue-500', purple: 'bg-purple-500', slate: 'bg-slate-500',
};

export default function Badge({ tone = 'slate', children, dot, className = '' }: Props) {
    return (
        <span className={`inline-flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full ${tones[tone]} ${className}`}>
            {dot && <span className={`w-1.5 h-1.5 rounded-full ${dots[tone]}`} />}
            {children}
        </span>
    );
}

/** Maps common domain statuses to a tone for consistency across pages. */
export function statusTone(status: string): Tone {
    const s = status.toLowerCase();
    if (['active', 'paid', 'present', 'published', 'completed', 'approved'].includes(s)) return 'green';
    if (['pending', 'new', 'late', 'processing', 'draft'].includes(s)) return 'amber';
    if (['inactive', 'absent', 'failed', 'cancelled', 'rejected', 'overdue'].includes(s)) return 'rose';
    if (['excused', 'enterprise'].includes(s)) return 'purple';
    if (['professional'].includes(s)) return 'blue';
    return 'slate';
}
