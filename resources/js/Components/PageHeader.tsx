import React from 'react';
import { motion } from 'framer-motion';
import { fadeInUp } from '@/lib/animations';

interface Props {
    title: string;
    subtitle?: string;
    actions?: React.ReactNode;
}

export default function PageHeader({ title, subtitle, actions }: Props) {
    return (
        <motion.div {...fadeInUp} className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 className="text-2xl font-extrabold text-slate-900 dark:text-white">{title}</h1>
                {subtitle && <p className="text-sm text-slate-500 mt-0.5">{subtitle}</p>}
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </motion.div>
    );
}
