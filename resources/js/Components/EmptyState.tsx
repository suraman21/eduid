import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

interface Props {
    icon: LucideIcon;
    title: string;
    message?: string;
    actionLabel?: string;
    actionHref?: string;
}

export default function EmptyState({ icon: Icon, title, message, actionLabel, actionHref }: Props) {
    return (
        <div className="flex flex-col items-center justify-center text-center py-16 px-4">
            <div className="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-white/5 flex items-center justify-center mb-4">
                <Icon className="w-6 h-6 text-slate-400" strokeWidth={1.5} />
            </div>
            <h3 className="text-sm font-semibold text-slate-700 dark:text-slate-200">{title}</h3>
            {message && <p className="mt-1 text-sm text-slate-400 max-w-sm">{message}</p>}
            {actionLabel && actionHref && (
                <Link href={actionHref} className="mt-4 text-sm font-medium text-purple-600 dark:text-purple-400 hover:underline">
                    {actionLabel} →
                </Link>
            )}
        </div>
    );
}
