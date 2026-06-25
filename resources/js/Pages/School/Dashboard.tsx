import { Head, Link } from '@inertiajs/react';
import { Users, UserCog, Building2, ClipboardCheck, Package, AlertTriangle, XCircle } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';

interface Student {
    id: number;
    full_name: string;
    uid: string;
    photo_url: string | null;
    initials: string;
    created_at_human: string;
}

interface Stats {
    total_students: number;
    active_students: number;
    total_staff: number;
    total_classes: number;
    pending_orders: number;
    today_sessions: number;
}

interface School {
    name: string;
    academic_year: string | null;
    plan_tier: string;
    calendar_system: string;
    subscription_status: string;
    subscription_expires_at: string | null;
    max_students: number;
}

interface Props {
    stats: Stats;
    school: School;
    recent_students: Student[];
}

function CapacityBar({ used, max }: { used: number; max: number }) {
    const pct = max > 0 ? Math.min(100, (used / max) * 100) : 0;
    const color = pct > 90 ? 'bg-red-500' : pct > 70 ? 'bg-amber-500' : 'bg-indigo-500';
    return (
        <div className="mt-2">
            <div className="flex justify-between text-xs mb-1">
                <span className="text-gray-400">Capacity</span>
                <span className="text-gray-600">{used} / {max}</span>
            </div>
            <div className="w-full bg-gray-100 rounded-full h-1.5">
                <div className={`h-1.5 rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

function StatCard({ icon: Icon, iconBg, iconColor, value, label, children }: {
    icon: React.ElementType; iconBg: string; iconColor: string;
    value: React.ReactNode; label: string; children?: React.ReactNode;
}) {
    return (
        <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow transition">
            <div className="flex items-center justify-between mb-3">
                <div className={`w-10 h-10 rounded-lg ${iconBg} flex items-center justify-center`}>
                    <Icon className={`w-5 h-5 ${iconColor}`} strokeWidth={1.5} />
                </div>
                {children}
            </div>
            <p className="text-2xl font-bold text-gray-900">{value}</p>
            <p className="text-xs text-gray-400 mt-1">{label}</p>
        </div>
    );
}

export default function Dashboard({ stats, school, recent_students }: Props) {
    const isGrace    = school.subscription_status === 'grace';
    const isExpired  = ['expired', 'suspended'].includes(school.subscription_status);

    return (
        <SchoolLayout>
            <Head title="Dashboard" />

            {/* Subscription warnings */}
            {isGrace && (
                <div className="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-start gap-3">
                    <AlertTriangle className="w-5 h-5 text-amber-500 mt-0.5 flex-shrink-0" />
                    <div>
                        <p className="text-sm font-medium text-amber-800">Subscription in Grace Period</p>
                        <p className="text-xs text-amber-600 mt-0.5">New data entry is restricted. Contact Arkeon to renew your subscription.</p>
                    </div>
                </div>
            )}
            {isExpired && (
                <div className="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-start gap-3">
                    <XCircle className="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" />
                    <div>
                        <p className="text-sm font-medium text-red-800">Subscription Expired</p>
                        <p className="text-xs text-red-600 mt-0.5">Your subscription has expired. Data is read-only. Contact Arkeon to reactivate.</p>
                    </div>
                </div>
            )}

            {/* Main stats */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:shadow transition">
                    <div className="flex items-center justify-between mb-3">
                        <div className="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <Users className="w-5 h-5 text-indigo-500" strokeWidth={1.5} />
                        </div>
                        <span className="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600">
                            {stats.active_students} active
                        </span>
                    </div>
                    <p className="text-2xl font-bold text-gray-900">{stats.total_students.toLocaleString()}</p>
                    <p className="text-xs text-gray-400 mt-1">Students</p>
                    <CapacityBar used={stats.total_students} max={school.max_students} />
                </div>
                <StatCard icon={UserCog} iconBg="bg-blue-50" iconColor="text-blue-500" value={stats.total_staff} label="Staff Members" />
                <StatCard icon={Building2} iconBg="bg-purple-50" iconColor="text-purple-500" value={stats.total_classes} label="Classes" />
            </div>

            {/* Second row */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                <div className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
                    <div className="flex items-center gap-3 mb-1">
                        <div className="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                            <ClipboardCheck className="w-4 h-4 text-green-500" strokeWidth={1.5} />
                        </div>
                        <p className="text-xl font-bold text-gray-900">{stats.today_sessions}</p>
                    </div>
                    <p className="text-xs text-gray-400">Attendance Sessions Today</p>
                </div>
                <a href="/school/card-orders" className="bg-white rounded-xl border border-gray-100 p-5 shadow-sm hover:border-orange-200 hover:shadow-md transition block">
                    <div className="flex items-center gap-3 mb-1">
                        <div className="w-8 h-8 rounded-lg bg-orange-50 flex items-center justify-center">
                            <Package className="w-4 h-4 text-orange-500" strokeWidth={1.5} />
                        </div>
                        <p className="text-xl font-bold text-gray-900">{stats.pending_orders}</p>
                    </div>
                    <p className="text-xs text-gray-400">Pending Card Orders</p>
                </a>
            </div>

            {/* Recent students */}
            <div className="bg-white rounded-xl border border-gray-100 shadow-sm">
                <div className="px-5 py-4 border-b border-gray-100">
                    <h3 className="text-sm font-semibold text-gray-900">Recently Added Students</h3>
                </div>
                <div className="divide-y divide-gray-50">
                    {recent_students.length === 0 ? (
                        <div className="px-5 py-8 text-center text-sm text-gray-400">No students registered yet</div>
                    ) : recent_students.map(s => (
                        <div key={s.id} className="px-5 py-3 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                {s.photo_url ? (
                                    <img src={s.photo_url} className="w-8 h-8 rounded-full object-cover" alt="" />
                                ) : (
                                    <div className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-400">
                                        {s.initials}
                                    </div>
                                )}
                                <div>
                                    <p className="text-sm font-medium text-gray-800">{s.full_name}</p>
                                    <p className="text-xs text-gray-400">{s.uid || 'No ID'}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-xs text-gray-400">{s.created_at_human}</span>
                                <a href={`/school/students/${s.id}/id-card`} className="text-xs text-indigo-600 hover:text-indigo-800 font-medium transition">
                                    ID Card
                                </a>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Quick info */}
            <div className="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
                {[
                    { label: 'Plan', value: (school.plan_tier ?? 'Starter').charAt(0).toUpperCase() + (school.plan_tier ?? 'Starter').slice(1) },
                    { label: 'Calendar', value: (school.calendar_system ?? 'Ethiopian').charAt(0).toUpperCase() + (school.calendar_system ?? 'Ethiopian').slice(1) },
                    { label: 'Subscription Expires', value: school.subscription_expires_at ? new Date(school.subscription_expires_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A' },
                ].map(({ label, value }) => (
                    <div key={label} className="bg-white rounded-xl border border-gray-100 p-4 shadow-sm">
                        <p className="text-xs text-gray-400 mb-1">{label}</p>
                        <p className="text-sm font-semibold text-gray-900">{value}</p>
                    </div>
                ))}
            </div>
        </SchoolLayout>
    );
}
