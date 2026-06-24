import React from 'react';
import { Dialog } from '@headlessui/react';
import { motion, AnimatePresence } from 'framer-motion';
import { X } from 'lucide-react';

interface Props {
    open: boolean;
    onClose: () => void;
    title?: string;
    children: React.ReactNode;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl';
}

const widths = { sm: 'max-w-sm', md: 'max-w-md', lg: 'max-w-lg', xl: 'max-w-xl' };

export default function Modal({ open, onClose, title, children, maxWidth = 'md' }: Props) {
    return (
        <AnimatePresence>
            {open && (
                <Dialog static open={open} onClose={onClose} className="relative z-50">
                    <motion.div
                        initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                        className="fixed inset-0 bg-black/60 backdrop-blur-sm"
                    />
                    <div className="fixed inset-0 flex items-center justify-center p-4">
                        <motion.div
                            initial={{ scale: 0.95, opacity: 0 }}
                            animate={{ scale: 1, opacity: 1 }}
                            exit={{ scale: 0.95, opacity: 0 }}
                            transition={{ duration: 0.2 }}
                            className={`w-full ${widths[maxWidth]}`}
                        >
                            <Dialog.Panel className="rounded-2xl border bg-white border-slate-200 dark:bg-[#0d1221] dark:border-white/10 shadow-2xl">
                                {title && (
                                    <div className="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-white/10">
                                        <Dialog.Title className="text-sm font-semibold text-slate-900 dark:text-white">{title}</Dialog.Title>
                                        <button onClick={onClose} className="text-slate-400 hover:text-slate-600 dark:hover:text-white transition">
                                            <X className="w-4 h-4" />
                                        </button>
                                    </div>
                                )}
                                <div className="p-5">{children}</div>
                            </Dialog.Panel>
                        </motion.div>
                    </div>
                </Dialog>
            )}
        </AnimatePresence>
    );
}
