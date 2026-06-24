import React from 'react';
import { motion } from 'framer-motion';
import { fadeInUp } from '@/lib/animations';
import type { LucideIcon } from 'lucide-react';

interface Props {
    icon: LucideIcon;
    label: string;
    value: number | string;
    badge?: string;
    badgeColor?: 'green' | 'amber' | 'rose' | 'blue' | 'purple';
}

const badgeClasses: Record<string, string> = {
    green:  'bg-emerald-500/15 text-emerald-400',
    amber:  'bg-amber-500/15 text-amber-400',
    rose:   'bg-rose-500/15 text-rose-400',
    blue:   'bg-blue-500/15 text-blue-400',
    purple: 'bg-purple-500/15 text-purple-400',
};

export default function StatCard({ icon: Icon, label, value, badge, badgeColor = 'purple' }: Props) {
    return (
        <motion.div
            {...fadeInUp}
            whileHover={{ y: -2, transition: { duration: 0.2 } }}
            className="bg-white/[0.025] border border-white/5 rounded-2xl p-5"
        >
            <div className="flex items-start justify-between">
                <div className="w-9 h-9 rounded-xl bg-purple-600/20 flex items-center justify-center">
                    <Icon className="w-4.5 h-4.5 text-purple-400" strokeWidth={1.75} />
                </div>
                {badge && (
                    <span className={`text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full ${badgeClasses[badgeColor]}`}>
                        {badge}
                    </span>
                )}
            </div>
            <p className="mt-4 text-2xl font-bold text-white tabular-nums">{value.toLocaleString()}</p>
            <p className="text-xs text-slate-500 font-medium mt-0.5">{label}</p>
        </motion.div>
    );
}
