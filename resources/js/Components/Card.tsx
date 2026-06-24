import React from 'react';
import { motion } from 'framer-motion';

interface Props {
    children: React.ReactNode;
    className?: string;
    hover?: boolean;
    padding?: boolean;
}

/** Glass card. Light in school theme, translucent in dark admin theme. */
export default function Card({ children, className = '', hover = false, padding = true }: Props) {
    const base =
        'rounded-2xl border bg-white border-slate-200 shadow-sm ' +
        'dark:bg-white/[0.025] dark:border-white/5 dark:shadow-none';
    const pad = padding ? 'p-5' : '';

    if (hover) {
        return (
            <motion.div
                whileHover={{ y: -2, transition: { duration: 0.2 } }}
                className={`${base} ${pad} ${className}`}
            >
                {children}
            </motion.div>
        );
    }
    return <div className={`${base} ${pad} ${className}`}>{children}</div>;
}
