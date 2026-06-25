import React, { useState, useEffect } from 'react';
import { usePage, Link } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    LayoutDashboard, Users, GraduationCap, BookOpen, UserCog,
    ClipboardCheck, FileBarChart, Menu, LogOut, Upload,
} from 'lucide-react';
import type { PageProps } from '@/types';
import Toast from '@/Components/Toast';

interface NavItem { label: string; href: string; icon: React.ElementType; inertia?: boolean; }

const navItems: NavItem[] = [
    { label: 'Dashboard',   href: '/school/dashboard',        icon: LayoutDashboard, inertia: true },
    { label: 'Students',    href: '/school/students',         icon: Users,           inertia: true },
    { label: 'Classes',     href: '/school/classes',          icon: GraduationCap },
    { label: 'Subjects',    href: '/school/subjects',         icon: BookOpen },
    { label: 'Staff',       href: '/school/staff',            icon: UserCog },
    { label: 'Attendance',  href: '/school/attendance',       icon: ClipboardCheck },
    { label: 'Grades',      href: '/school/grades',           icon: FileBarChart },
    { label: 'Import',      href: '/school/students/import',  icon: Upload },
];

function hex(color: string, alpha: number) {
    const a = Math.round(alpha * 255).toString(16).padStart(2, '0');
    return `${color}${a}`;
}

export default function SchoolLayout({ children }: { children: React.ReactNode }) {
    const { url, props } = usePage<PageProps>();
    const school = props.school;
    const accent: string = (school as any)?.accent_color || '#6366f1';
    const [sidebarOpen, setSidebarOpen] = useState(false);

    useEffect(() => {
        document.documentElement.classList.remove('dark');
    }, []);

    const isActive = (href: string) => url.startsWith(href);
    const initials = (school?.name ?? 'S').slice(0, 1).toUpperCase();

    const SidebarContent = () => (
        <div className="flex flex-col h-full">
            {/* School header */}
            <div
                className="px-4 py-5"
                style={{ borderBottom: `1px solid ${hex(accent, 0.14)}` }}
            >
                <div className="flex items-center gap-3">
                    {/* Glowing avatar */}
                    <div className="relative flex-shrink-0">
                        <div
                            className="w-10 h-10 rounded-xl flex items-center justify-center text-white text-base font-extrabold"
                            style={{
                                background: accent,
                                boxShadow: `0 0 0 3px ${hex(accent, 0.18)}, 0 0 16px ${hex(accent, 0.35)}`,
                            }}
                        >
                            {initials}
                        </div>
                        {/* online dot */}
                        <div
                            className="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-green-400 border-2 border-white"
                            style={{ boxShadow: '0 0 6px #22c55e80' }}
                        />
                    </div>
                    <div className="min-w-0">
                        <p className="text-sm font-extrabold text-slate-900 leading-tight truncate">
                            {school?.name ?? 'School'}
                        </p>
                        <p
                            className="text-[10px] font-mono tracking-wider uppercase mt-0.5"
                            style={{ color: accent }}
                        >
                            {(school as any)?.code ?? '—'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Nav */}
            <nav className="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
                {navItems.map(item => {
                    const active = isActive(item.href);

                    const style: React.CSSProperties = active ? {
                        background: hex(accent, 0.10),
                        color: accent,
                        border: `1px solid ${hex(accent, 0.22)}`,
                        boxShadow: `0 0 14px ${hex(accent, 0.14)}, inset 0 0 8px ${hex(accent, 0.06)}`,
                    } : {
                        border: '1px solid transparent',
                    };

                    const cls = [
                        'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold',
                        'transition-all duration-150',
                        active ? '' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-100/70',
                    ].join(' ');

                    const inner = (
                        <>
                            <item.icon
                                className="w-4 h-4 flex-shrink-0"
                                style={active ? { color: accent } : { color: '#94a3b8' }}
                                strokeWidth={active ? 2 : 1.75}
                            />
                            <span className="flex-1">{item.label}</span>
                            {active && (
                                <div
                                    className="w-1.5 h-1.5 rounded-full"
                                    style={{ background: accent, boxShadow: `0 0 6px ${accent}` }}
                                />
                            )}
                        </>
                    );

                    return item.inertia ? (
                        <Link key={item.href} href={item.href} className={cls} style={style}>{inner}</Link>
                    ) : (
                        <a key={item.href} href={item.href} className={cls} style={style}>{inner}</a>
                    );
                })}
            </nav>

            {/* Sign out */}
            <div className="px-3 pb-4 pt-3" style={{ borderTop: `1px solid ${hex(accent, 0.10)}` }}>
                <form method="POST" action="/logout" style={{ margin: 0 }}>
                    <input
                        type="hidden"
                        name="_token"
                        value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''}
                    />
                    <button
                        type="submit"
                        className="flex items-center gap-3 w-full px-3 py-2.5 rounded-xl text-sm font-semibold text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all duration-150"
                    >
                        <LogOut className="w-4 h-4" />
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen text-slate-900 flex" style={{ background: '#f8f9fc' }}>
            <Toast />

            {/* Desktop sidebar */}
            <aside
                className="hidden lg:flex flex-col w-60 fixed inset-y-0 left-0 z-30 bg-white"
                style={{
                    borderRight: `1px solid ${hex(accent, 0.12)}`,
                    boxShadow: `2px 0 24px ${hex(accent, 0.06)}`,
                }}
            >
                <SidebarContent />
            </aside>

            {/* Mobile overlay + sidebar */}
            <AnimatePresence>
                {sidebarOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 lg:hidden"
                            onClick={() => setSidebarOpen(false)}
                        />
                        <motion.aside
                            initial={{ x: -260 }} animate={{ x: 0 }} exit={{ x: -260 }}
                            transition={{ duration: 0.22, ease: 'easeOut' }}
                            className="fixed inset-y-0 left-0 w-60 bg-white z-50 lg:hidden"
                            style={{
                                borderRight: `1px solid ${hex(accent, 0.12)}`,
                                boxShadow: `4px 0 32px ${hex(accent, 0.10)}`,
                            }}
                        >
                            <SidebarContent />
                        </motion.aside>
                    </>
                )}
            </AnimatePresence>

            {/* Main */}
            <div className="flex-1 lg:ml-60 flex flex-col min-h-screen">
                {/* Top bar */}
                <header
                    className="sticky top-0 z-20 h-14 flex items-center px-4 lg:px-6"
                    style={{
                        background: 'rgba(248,249,252,0.85)',
                        backdropFilter: 'blur(12px)',
                        borderBottom: `1px solid ${hex(accent, 0.10)}`,
                        boxShadow: `0 1px 12px ${hex(accent, 0.06)}`,
                    }}
                >
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="lg:hidden p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition"
                    >
                        <Menu className="w-5 h-5" />
                    </button>
                    <div className="flex-1" />
                    {/* Subtle accent pill in topbar */}
                    <div
                        className="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest"
                        style={{
                            background: hex(accent, 0.08),
                            color: accent,
                            border: `1px solid ${hex(accent, 0.16)}`,
                        }}
                    >
                        <div className="w-1.5 h-1.5 rounded-full" style={{ background: accent, boxShadow: `0 0 6px ${accent}` }} />
                        {school?.name ?? 'School'}
                    </div>
                </header>

                <main className="flex-1 px-4 lg:px-8 py-7">{children}</main>
            </div>
        </div>
    );
}
