import { Head } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Building2, Users, CreditCard, Mail, DollarSign, GraduationCap } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatCard from '@/Components/StatCard';
import { staggerContainer, fadeInUp } from '@/lib/animations';

interface Stats {
    total_schools: number;
    active_schools: number;
    total_students: number;
    total_staff: number;
    pending_leads: number;
    total_revenue: number;
    pending_orders: number;
}

interface RecentSchool {
    id: number;
    name: string;
    code: string;
    plan_tier: string;
    is_active: boolean;
}

interface RecentLead {
    id: number;
    contact_name: string;
    school_name: string;
    email: string;
    status: string;
}

interface Props {
    stats: Stats;
    recentSchools: RecentSchool[];
    recentLeads: RecentLead[];
    schoolsByPlan: Record<string, number>;
}

const planColors: Record<string, string> = {
    starter:      'bg-slate-500/20 text-slate-400',
    professional: 'bg-blue-500/20 text-blue-400',
    enterprise:   'bg-purple-500/20 text-purple-400',
};

export default function Dashboard({ stats, recentSchools, recentLeads }: Props) {
    return (
        <AdminLayout>
            <Head title="Dashboard" />

            <motion.div {...fadeInUp} className="mb-6">
                <h1 className="text-2xl font-extrabold text-white">Dashboard</h1>
                <p className="text-sm text-slate-500 mt-0.5">Overview of all schools and activity.</p>
            </motion.div>

            {/* Stat grid */}
            <motion.div
                variants={staggerContainer}
                initial="initial"
                animate="animate"
                className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8"
            >
                <StatCard icon={Building2}     label="Total Schools"   value={stats.total_schools}  badge={`${stats.active_schools} active`} badgeColor="green" />
                <StatCard icon={Users}         label="Students"        value={stats.total_students} />
                <StatCard icon={CreditCard}    label="Pending Orders"  value={stats.pending_orders} badge={stats.pending_orders > 0 ? 'needs attention' : undefined} badgeColor="amber" />
                <StatCard icon={Mail}          label="New Leads"       value={stats.pending_leads}  badge={stats.pending_leads > 0 ? 'new' : undefined} badgeColor="blue" />
                <StatCard icon={GraduationCap} label="Staff"           value={stats.total_staff} />
                <StatCard icon={DollarSign}    label="Revenue (ETB)"   value={stats.total_revenue.toLocaleString()} />
            </motion.div>

            <div className="grid lg:grid-cols-2 gap-6">
                {/* Recent Schools */}
                <motion.div {...fadeInUp} className="bg-white/[0.025] border border-white/5 rounded-2xl overflow-hidden">
                    <div className="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-white">Recent Schools</h2>
                        <a href="/admin/schools" className="text-xs text-purple-400 hover:text-purple-300 transition">View all →</a>
                    </div>
                    <div className="divide-y divide-white/5">
                        {recentSchools.length === 0 ? (
                            <p className="px-5 py-8 text-sm text-slate-500 text-center">No schools yet.</p>
                        ) : recentSchools.map(school => (
                            <a
                                key={school.id}
                                href={`/admin/schools/${school.id}`}
                                className="flex items-center justify-between px-5 py-3 hover:bg-white/[0.02] transition"
                            >
                                <div>
                                    <p className="text-sm font-medium text-slate-200">{school.name}</p>
                                    <p className="text-xs text-slate-500 font-mono mt-0.5">{school.code}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className={`text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full ${planColors[school.plan_tier] ?? 'bg-slate-500/20 text-slate-400'}`}>
                                        {school.plan_tier}
                                    </span>
                                    <span className={`w-1.5 h-1.5 rounded-full ${school.is_active ? 'bg-emerald-400' : 'bg-rose-400'}`} />
                                </div>
                            </a>
                        ))}
                    </div>
                </motion.div>

                {/* Recent Leads */}
                <motion.div {...fadeInUp} className="bg-white/[0.025] border border-white/5 rounded-2xl overflow-hidden">
                    <div className="px-5 py-4 border-b border-white/5 flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-white">Recent Leads</h2>
                        <a href="/admin/leads" className="text-xs text-purple-400 hover:text-purple-300 transition">View all →</a>
                    </div>
                    <div className="divide-y divide-white/5">
                        {recentLeads.length === 0 ? (
                            <p className="px-5 py-8 text-sm text-slate-500 text-center">No leads yet.</p>
                        ) : recentLeads.map(lead => (
                            <a
                                key={lead.id}
                                href={`/admin/leads/${lead.id}`}
                                className="flex items-center justify-between px-5 py-3 hover:bg-white/[0.02] transition"
                            >
                                <div>
                                    <p className="text-sm font-medium text-slate-200">{lead.school_name}</p>
                                    <p className="text-xs text-slate-500 mt-0.5">{lead.contact_name} · {lead.email}</p>
                                </div>
                                <span className="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-400">
                                    {lead.status}
                                </span>
                            </a>
                        ))}
                    </div>
                </motion.div>
            </div>
        </AdminLayout>
    );
}
