import React from 'react';
import { motion } from 'framer-motion';
import { GraduationCap } from 'lucide-react';

interface Props {
    children: React.ReactNode;
    title?: string;
    subtitle?: string;
}

/** Centered glass card on a dark gradient — shared by all auth pages. */
export default function AuthLayout({ children, title, subtitle }: Props) {
    return (
        <div className="min-h-screen bg-[#060a14] text-slate-200 flex items-center justify-center px-4 py-12 relative overflow-hidden">
            {/* Ambient glows */}
            <div className="absolute top-0 left-1/4 w-96 h-96 bg-purple-600/10 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute bottom-0 right-1/4 w-96 h-96 bg-blue-600/10 rounded-full blur-3xl pointer-events-none" />

            <motion.div
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, ease: 'easeOut' }}
                className="w-full max-w-md relative z-10"
            >
                <div className="mb-6 text-center">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-purple-600 to-blue-500 shadow-lg shadow-purple-500/30 mb-4">
                        <GraduationCap className="w-8 h-8 text-white" />
                    </div>
                    {title && (
                        <h1 className="text-3xl font-extrabold bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">
                            {title}
                        </h1>
                    )}
                    {subtitle && <p className="text-slate-400 text-sm mt-1.5 font-light">{subtitle}</p>}
                </div>

                <div className="rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden bg-white/[0.03] border border-white/10 backdrop-blur-xl">
                    <div className="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-purple-500 to-transparent opacity-80" />
                    {children}
                </div>
            </motion.div>
        </div>
    );
}
