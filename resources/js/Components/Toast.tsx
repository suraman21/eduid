import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { CheckCircle2, AlertCircle, X } from 'lucide-react';
import type { PageProps } from '@/types';

/** Reads shared flash data and renders a transient toast in the corner. */
export default function Toast() {
    const { flash } = usePage<PageProps>().props;
    const [visible, setVisible] = useState<{ type: 'success' | 'error'; msg: string } | null>(null);

    useEffect(() => {
        if (flash?.success) setVisible({ type: 'success', msg: flash.success });
        else if (flash?.error) setVisible({ type: 'error', msg: flash.error });
    }, [flash?.success, flash?.error]);

    useEffect(() => {
        if (!visible) return;
        const t = setTimeout(() => setVisible(null), 4000);
        return () => clearTimeout(t);
    }, [visible]);

    const isError = visible?.type === 'error';

    return (
        <AnimatePresence>
            {visible && (
                <motion.div
                    initial={{ opacity: 0, x: 40 }}
                    animate={{ opacity: 1, x: 0 }}
                    exit={{ opacity: 0, x: 40 }}
                    transition={{ duration: 0.25, ease: 'easeOut' }}
                    className="fixed top-4 right-4 z-[60] max-w-sm"
                >
                    <div className={`flex items-start gap-3 px-4 py-3 rounded-xl border shadow-lg ${
                        isError
                            ? 'bg-rose-50 border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/20'
                            : 'bg-emerald-50 border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20'
                    }`}>
                        {isError
                            ? <AlertCircle className="w-5 h-5 text-rose-500 flex-shrink-0 mt-0.5" />
                            : <CheckCircle2 className="w-5 h-5 text-emerald-500 flex-shrink-0 mt-0.5" />}
                        <p className={`text-sm font-medium ${isError ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'}`}>
                            {visible.msg}
                        </p>
                        <button onClick={() => setVisible(null)} className="ml-auto text-slate-400 hover:text-slate-600 dark:hover:text-white transition">
                            <X className="w-4 h-4" />
                        </button>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
