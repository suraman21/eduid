import React, { useState, useEffect } from 'react';
import { usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    LayoutDashboard, Users, GraduationCap, BookOpen, UserCog,
    ClipboardCheck, FileBarChart, Menu, LogOut, ChevronRight, Upload,
} from 'lucide-react';
import type { PageProps } from '@/types';
import Toast from '@/Components/Toast';

interface NavItem { label: string; href: string; icon: React.ElementType; }

const navItems: NavItem[] = [
    { label: 'Dashboard',   href: '/school/dashboard',        icon: LayoutDashboard },
    { label: 'Students',    href: '/school/students',         icon: Users },
    { label: 'Classes',     href: '/school/classes',          icon: GraduationCap },
    { label: 'Subjects',    href: '/school/subjects',         icon: BookOpen },
    { label: 'Staff',       href: '/school/staff',            icon: UserCog },
    { label: 'Attendance',  href: '/school/attendance',       icon: ClipboardCheck },
    { label: 'Grades',      href: '/school/grades',           icon: FileBarChart },
    { label: 'Import',      href: '/school/students/import',  icon: Upload },
];

export default function SchoolLayout({ children }: { children: React.ReactNode }) {
    const { url, props } = usePage<PageProps>();
    const school = props.school;
    const accent = school?.accent_color || '#6366f1';
    const [sidebarOpen, setSidebarOpen] = useState(false);

    useEffect(() => {
        document.documentElement.classList.remove('dark');
    }, []);

    const isActive = (href: string) => url.startsWith(href);
    const initials = (school?.name ?? 'S').slice(0, 1).toUpperCase();

    const SidebarContent = () => (
        <div className="flex flex-col h-full">
            <div className="flex items-center gap-3 px-4 py-5 border-b border-slate-100">
                <div className="w-9 h-9 rounded-lg flex items-center justify-center text-white text-sm font-bold" style={{ background: accent }}>
                    {initials}
                </div>
                <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-900 leading-none truncate">{school?.name ?? 'School'}</p>
                    <p className="text-[10px] text-slate-400 font-mono tracking-wider uppercase mt-0.5">{school?.code}</p>
                </div>
            </div>

            <nav className="flex-1 px-2 py-4 space-y-0.5 overflow-y-auto">
                {navItems.map(item => {
                    const active = isActive(item.href);
                    return (
                        <a
                            key={item.href}
                            href={item.href}
                            className="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150"
                            style={active
                                ? { background: `${accent}14`, color: accent, borderRight: `3px solid ${accent}` }
                                : undefined}
                        >
                            <item.icon className={`w-4 h-4 flex-shrink-0 ${active ? '' : 'text-slate-400'}`} style={active ? { color: accent } : undefined} />
                            <span className={`flex-1 ${active ? '' : 'text-slate-600'}`}>{item.label}</span>
                            {active && <ChevronRight className="w-3 h-3" style={{ color: accent }} />}
                        </a>
                    );
                })}
            </nav>

            <div className="px-2 pb-4 border-t border-slate-100 pt-3">
                <form method="POST" action="/logout" style={{ margin: 0 }}>
                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''} />
                    <button
                        type="submit"
                        className="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-all duration-150"
                    >
                        <LogOut className="w-4 h-4" />
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 flex" style={{ ['--accent' as any]: accent }}>
            <Toast />
            <aside className="hidden lg:flex flex-col w-56 bg-white border-r border-slate-200 fixed inset-y-0 left-0 z-30">
                <SidebarContent />
            </aside>

            <AnimatePresence>
                {sidebarOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-black/40 z-40 lg:hidden" onClick={() => setSidebarOpen(false)}
                        />
                        <motion.aside
                            initial={{ x: -240 }} animate={{ x: 0 }} exit={{ x: -240 }}
                            transition={{ duration: 0.25, ease: 'easeOut' }}
                            className="fixed inset-y-0 left-0 w-56 bg-white border-r border-slate-200 z-50 lg:hidden"
                        >
                            <SidebarContent />
                        </motion.aside>
                    </>
                )}
            </AnimatePresence>

            <div className="flex-1 lg:ml-56 flex flex-col min-h-screen">
                <header className="sticky top-0 z-20 bg-slate-50/80 backdrop-blur-sm border-b border-slate-200 px-4 lg:px-6 h-14 flex items-center justify-between">
                    <button onClick={() => setSidebarOpen(true)} className="lg:hidden p-1.5 text-slate-500 hover:text-slate-900 transition">
                        <Menu className="w-5 h-5" />
                    </button>
                    <div className="flex-1" />
                </header>
                <main className="flex-1 px-4 lg:px-6 py-6">{children}</main>
            </div>
        </div>
    );
}
