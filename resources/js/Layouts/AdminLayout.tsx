import React, { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    LayoutDashboard, Building2, GraduationCap, CreditCard,
    Mail, DollarSign, Layers, FileText, Menu, X, LogOut,
    ChevronRight, ShieldCheck,
} from 'lucide-react';
import type { PageProps } from '@/types';

interface NavItem {
    label: string;
    href: string;
    icon: React.ElementType;
    badge?: number;
}

const navItems: NavItem[] = [
    { label: 'Dashboard',      href: '/admin/dashboard',       icon: LayoutDashboard },
    { label: 'Schools',        href: '/admin/schools',         icon: Building2 },
    { label: 'Leads',          href: '/admin/leads',           icon: Mail },
    { label: 'Payments',       href: '/admin/payments',        icon: DollarSign },
    { label: 'Card Orders',    href: '/admin/card-orders',     icon: CreditCard },
    { label: 'Card Templates', href: '/admin/card-templates',  icon: Layers },
    { label: 'Features',       href: '/admin/features',        icon: ShieldCheck },
    { label: 'Audit Log',      href: '/admin/audit-log',       icon: FileText },
];

export default function AdminLayout({ children }: { children: React.ReactNode }) {
    const { url } = usePage();
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const isActive = (href: string) => url.startsWith(href);

    const SidebarContent = () => (
        <div className="flex flex-col h-full">
            {/* Logo */}
            <div className="flex items-center gap-3 px-4 py-5 border-b border-white/5">
                <div className="w-8 h-8 rounded-lg bg-gradient-to-tr from-purple-600 to-blue-500 flex items-center justify-center shadow-lg shadow-purple-500/30">
                    <GraduationCap className="w-4 h-4 text-white" />
                </div>
                <div>
                    <p className="text-sm font-bold text-white leading-none">EduID</p>
                    <p className="text-[10px] text-purple-400 font-medium tracking-wider uppercase mt-0.5">Super Admin</p>
                </div>
            </div>

            {/* Nav */}
            <nav className="flex-1 px-2 py-4 space-y-0.5 overflow-y-auto">
                {navItems.map(item => {
                    const active = isActive(item.href);
                    return (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group ${
                                active
                                    ? 'bg-purple-600/20 text-purple-300'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-white/5'
                            }`}
                        >
                            <item.icon className={`w-4 h-4 flex-shrink-0 ${active ? 'text-purple-400' : 'text-slate-500 group-hover:text-slate-300'}`} />
                            <span className="flex-1">{item.label}</span>
                            {active && <ChevronRight className="w-3 h-3 text-purple-400" />}
                        </Link>
                    );
                })}
            </nav>

            {/* Footer */}
            <div className="px-2 pb-4 border-t border-white/5 pt-3">
                <Link
                    href="/admin/logout"
                    method="post"
                    as="button"
                    className="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-all duration-150"
                >
                    <LogOut className="w-4 h-4" />
                    <span>Sign out</span>
                </Link>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-[#060a14] text-slate-200 flex">
            {/* Desktop sidebar */}
            <aside className="hidden lg:flex flex-col w-56 bg-[#0d1221] border-r border-white/5 fixed inset-y-0 left-0 z-30">
                <SidebarContent />
            </aside>

            {/* Mobile sidebar */}
            <AnimatePresence>
                {sidebarOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-black/60 z-40 lg:hidden"
                            onClick={() => setSidebarOpen(false)}
                        />
                        <motion.aside
                            initial={{ x: -240 }}
                            animate={{ x: 0 }}
                            exit={{ x: -240 }}
                            transition={{ duration: 0.25, ease: 'easeOut' }}
                            className="fixed inset-y-0 left-0 w-56 bg-[#0d1221] border-r border-white/5 z-50 lg:hidden"
                        >
                            <SidebarContent />
                        </motion.aside>
                    </>
                )}
            </AnimatePresence>

            {/* Main content */}
            <div className="flex-1 lg:ml-56 flex flex-col min-h-screen">
                {/* Top bar */}
                <header className="sticky top-0 z-20 bg-[#060a14]/80 backdrop-blur-sm border-b border-white/5 px-4 lg:px-6 h-14 flex items-center justify-between">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="lg:hidden p-1.5 text-slate-400 hover:text-white transition"
                    >
                        <Menu className="w-5 h-5" />
                    </button>
                    <div className="flex-1" />
                </header>

                {/* Page body */}
                <main className="flex-1 px-4 lg:px-6 py-6">
                    {children}
                </main>
            </div>
        </div>
    );
}
