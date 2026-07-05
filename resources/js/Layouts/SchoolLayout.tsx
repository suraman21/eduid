import React, { useState, useEffect } from 'react';
import { usePage, Link, router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    LayoutDashboard, Users, GraduationCap, BookOpen, UserCog,
    ClipboardCheck, FileBarChart, Menu, LogOut, Upload, Search, X,
    UserCircle, CreditCard, Camera, FileText, CalendarRange, Rocket, BarChart3, Wallet, Settings,
} from 'lucide-react';
import type { PageProps } from '@/types';
import Toast from '@/Components/Toast';
import Onboarding from '@/onboarding/Onboarding';

interface NavItem { label: string; href: string; icon: React.ElementType; inertia?: boolean; roles?: string[]; feature?: string; tour?: string; }

// Sidebar is ROLE-AWARE: teachers only see what teachers can do (their
// dashboard, attendance, grades, profile) — the registry, staff management,
// orders and photo tooling are school-admin territory. This mirrors the
// server-side route guards; hiding here is UX, the routes enforce.
const navItems: NavItem[] = [
    { label: 'Dashboard',      href: '/school/dashboard',       icon: LayoutDashboard, inertia: true, tour: 'nav-dashboard' },
    { label: 'Students',       href: '/school/students',        icon: Users,           inertia: true, roles: ['school_admin', 'staff'], tour: 'nav-students' },
    { label: 'Classes',        href: '/school/classes',         icon: GraduationCap,   inertia: true, roles: ['school_admin', 'staff'], tour: 'nav-classes' },
    { label: 'Subjects',       href: '/school/subjects',        icon: BookOpen,        inertia: true, roles: ['school_admin'] },
    { label: 'Staff',          href: '/school/staff',           icon: UserCog,         inertia: true, roles: ['school_admin'], tour: 'nav-staff' },
    { label: 'Attendance',     href: '/school/attendance',      icon: ClipboardCheck,  inertia: true, tour: 'nav-attendance' },
    { label: 'Timetable',      href: '/school/timetable',       icon: CalendarRange,   inertia: true, roles: ['school_admin'], feature: 'timetable' },
    { label: 'Grades',         href: '/school/grades',          icon: FileBarChart,    inertia: true, feature: 'gradebook', tour: 'nav-grades' },
    { label: 'Report Cards',   href: '/school/report-cards',    icon: FileText,        inertia: true, roles: ['school_admin'], feature: 'report_cards' },
    { label: 'Fees',           href: '/school/fees',            icon: Wallet,          inertia: true, roles: ['school_admin'], feature: 'fees' },
    { label: 'Analytics',      href: '/school/analytics',       icon: BarChart3,       inertia: true, roles: ['school_admin'], feature: 'analytics' },
    { label: 'Card Orders',    href: '/school/card-orders',     icon: CreditCard,      inertia: true, roles: ['school_admin'], tour: 'nav-cardorders' },
    { label: 'Photo Sessions', href: '/school/photo-sessions',  icon: Camera,          inertia: true, roles: ['school_admin'] },
    { label: 'Import',         href: '/school/students/import', icon: Upload,                         roles: ['school_admin'], feature: 'csv_import' },
    { label: 'New Year',       href: '/school/year-transition', icon: Rocket,          inertia: true, roles: ['school_admin'], feature: 'year_transition' },
    { label: 'Settings',       href: '/school/settings',        icon: Settings,        inertia: true, roles: ['school_admin'] },
    { label: 'My Profile',     href: '/school/profile',         icon: UserCircle,      inertia: true },
];

/* ── colour helpers ──────────────────────────────────────────── */
function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

export default function SchoolLayout({ children }: { children: React.ReactNode }) {
    const { url, props } = usePage<PageProps>();
    const school = props.school;
    const user = props.auth?.user;
    const accent = normHex(props.accent);
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [search, setSearch] = useState('');

    const role = (user as { role?: string } | undefined)?.role ?? '';
    const features = ((props as Record<string, unknown>).features ?? {}) as Record<string, boolean>;
    const visibleNav = navItems.filter(item =>
        (!item.roles || item.roles.includes(role))
        // Hide modules the school's package doesn't include (routes enforce).
        && (!item.feature || features[item.feature] !== false)
    );
    const canSearchStudents = role === 'school_admin' || role === 'staff';

    useEffect(() => {
        document.documentElement.classList.remove('dark');
    }, []);

    const isActive = (href: string) => url.startsWith(href);
    const initials = (school?.name ?? 'S').slice(0, 1).toUpperCase();

    const sidebarGradient =
        `linear-gradient(180deg, ${accent} 0%, ${alpha(accent, 0.90)} 38%, rgba(8,10,24,0.97) 100%)`;

    function submitSearch(e: React.FormEvent) {
        e.preventDefault();
        router.get('/school/students', { search }, { preserveState: false });
        setSidebarOpen(false);
    }

    /* ── sidebar inner ─────────────────────────────────────── */
    const SidebarContent = ({ onNavigate }: { onNavigate?: () => void }) => (
        <div className="flex flex-col h-full relative overflow-hidden" style={{ background: sidebarGradient }}>
            {/* decorative top glow */}
            <div
                className="absolute -top-10 -right-10 w-40 h-40 rounded-full blur-3xl pointer-events-none"
                style={{ background: alpha(accent, 0.35) }}
            />
            <div
                className="absolute bottom-0 left-0 w-full h-32 blur-2xl pointer-events-none"
                style={{ background: 'linear-gradient(0deg, rgba(0,0,0,0.4), transparent)' }}
            />

            {/* logo / school */}
            <div className="relative flex items-center gap-3 px-5 pt-6 pb-5">
                <div
                    className="w-11 h-11 rounded-2xl flex items-center justify-center text-base font-extrabold flex-shrink-0"
                    style={{
                        background: 'rgba(255,255,255,0.16)',
                        backdropFilter: 'blur(8px)',
                        border: '1px solid rgba(255,255,255,0.25)',
                        color: '#fff',
                        boxShadow: '0 4px 16px rgba(0,0,0,0.25)',
                    }}
                >
                    {initials}
                </div>
                <div className="min-w-0">
                    <p className="text-sm font-extrabold text-white leading-tight truncate">{school?.name ?? 'School'}</p>
                    <p className="text-[10px] font-mono tracking-widest uppercase text-white/55 mt-0.5">{(school as any)?.code ?? '—'}</p>
                </div>
            </div>

            {/* nav */}
            <nav className="relative flex-1 px-3 py-2 space-y-1 overflow-y-auto">
                {visibleNav.map(item => {
                    const active = isActive(item.href);
                    const cls = 'group flex items-center gap-3 px-3.5 py-3 rounded-2xl text-sm font-semibold transition-all duration-200';
                    const style: React.CSSProperties = active
                        ? { background: '#ffffff', color: accent, boxShadow: '0 8px 24px rgba(0,0,0,0.28)' }
                        : { color: 'rgba(255,255,255,0.72)' };

                    const inner = (
                        <>
                            <span
                                className="flex items-center justify-center w-8 h-8 rounded-xl transition-all"
                                style={active
                                    ? { background: alpha(accent, 0.12), color: accent }
                                    : { background: 'rgba(255,255,255,0.10)', color: '#fff' }}
                            >
                                <item.icon className="w-4 h-4" strokeWidth={2} />
                            </span>
                            <span className="flex-1">{item.label}</span>
                            {active && (
                                <span className="w-2 h-2 rounded-full" style={{ background: accent, boxShadow: `0 0 8px ${accent}` }} />
                            )}
                        </>
                    );

                    const common = {
                        className: cls,
                        style,
                        'data-tour': item.tour,
                        onClick: onNavigate,
                        onMouseEnter: (e: React.MouseEvent<HTMLElement>) => {
                            if (!active) (e.currentTarget as HTMLElement).style.background = 'rgba(255,255,255,0.10)';
                        },
                        onMouseLeave: (e: React.MouseEvent<HTMLElement>) => {
                            if (!active) (e.currentTarget as HTMLElement).style.background = 'transparent';
                        },
                    };

                    return item.inertia
                        ? <Link key={item.href} href={item.href} {...common}>{inner}</Link>
                        : <a key={item.href} href={item.href} {...common}>{inner}</a>;
                })}
            </nav>

            {/* sign out */}
            <div className="relative px-3 pb-5">
                <form method="POST" action="/logout" style={{ margin: 0 }}>
                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''} />
                    <button
                        type="submit"
                        className="flex items-center gap-3 w-full px-3.5 py-3 rounded-2xl text-sm font-semibold text-white/70 hover:text-white hover:bg-white/10 transition-all duration-150"
                    >
                        <span className="flex items-center justify-center w-8 h-8 rounded-xl bg-white/10">
                            <LogOut className="w-4 h-4" />
                        </span>
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    );

    const pageBg = `radial-gradient(circle at 12% 0%, ${alpha(accent, 0.16)}, transparent 45%), radial-gradient(circle at 100% 100%, ${alpha(accent, 0.10)}, transparent 40%), #eef0f7`;

    return (
        <div className="min-h-screen flex lg:gap-5 lg:p-5" style={{ background: pageBg }}>
            <Toast />

            {/* desktop sidebar — floating panel */}
            <aside
                className="hidden lg:flex flex-col w-64 flex-shrink-0 rounded-[28px] overflow-hidden sticky top-5 self-start"
                style={{ height: 'calc(100vh - 2.5rem)', boxShadow: `0 20px 50px ${alpha(accent, 0.28)}` }}
            >
                <SidebarContent />
            </aside>

            {/* mobile drawer */}
            <AnimatePresence>
                {sidebarOpen && (
                    <>
                        <motion.div
                            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                            className="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 lg:hidden"
                            onClick={() => setSidebarOpen(false)}
                        />
                        <motion.aside
                            initial={{ x: -300 }} animate={{ x: 0 }} exit={{ x: -300 }}
                            transition={{ duration: 0.25, ease: 'easeOut' }}
                            className="fixed inset-y-3 left-3 w-64 rounded-[28px] overflow-hidden z-50 lg:hidden"
                            style={{ boxShadow: '0 20px 60px rgba(0,0,0,0.4)' }}
                        >
                            <button
                                onClick={() => setSidebarOpen(false)}
                                className="absolute top-4 right-4 z-10 p-1.5 rounded-lg bg-white/10 text-white/80 hover:bg-white/20 transition"
                            >
                                <X className="w-4 h-4" />
                            </button>
                            <SidebarContent onNavigate={() => setSidebarOpen(false)} />
                        </motion.aside>
                    </>
                )}
            </AnimatePresence>

            {/* main column */}
            <div className="flex-1 min-w-0 flex flex-col">
                {/* top bar */}
                <header className="flex items-center gap-3 mb-6 px-1">
                    <button
                        onClick={() => setSidebarOpen(true)}
                        className="lg:hidden p-2.5 rounded-2xl bg-white text-slate-600 shadow-sm border border-slate-200/60"
                    >
                        <Menu className="w-5 h-5" />
                    </button>

                    {/* search — student registry is admin/staff only */}
                    {canSearchStudents && (
                        <form onSubmit={submitSearch} className="flex-1 max-w-md">
                            <div className="relative">
                                <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                                <input
                                    value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    placeholder="Search students…"
                                    className="w-full pl-11 pr-4 py-2.5 rounded-2xl bg-white text-sm text-slate-700 placeholder:text-slate-400 border border-slate-200/70 shadow-sm focus:outline-none focus:ring-2 transition"
                                    style={{ ['--tw-ring-color' as any]: alpha(accent, 0.35) }}
                                />
                            </div>
                        </form>
                    )}

                    <div className="flex-1" />

                    {/* plan pill */}
                    <div
                        className="hidden sm:flex items-center gap-1.5 px-3.5 py-2 rounded-2xl text-xs font-bold capitalize bg-white shadow-sm border border-slate-200/60"
                        style={{ color: accent }}
                    >
                        <span className="w-1.5 h-1.5 rounded-full" style={{ background: accent, boxShadow: `0 0 6px ${accent}` }} />
                        {school?.plan_tier ?? 'Starter'}
                    </div>

                    {/* avatar */}
                    <div
                        className="w-10 h-10 rounded-2xl flex items-center justify-center text-white text-sm font-extrabold flex-shrink-0"
                        style={{ background: accent, boxShadow: `0 6px 18px ${alpha(accent, 0.4)}` }}
                        title={user?.name ?? school?.name ?? ''}
                    >
                        {(user?.name ?? school?.name ?? 'S').slice(0, 1).toUpperCase()}
                    </div>
                </header>

                <main className="flex-1 px-1 pb-8">{children}</main>
            </div>

            {/* First-run guided tour + gamified Getting Started checklist. */}
            <Onboarding />
        </div>
    );
}
