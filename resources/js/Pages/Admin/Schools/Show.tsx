import { Head } from '@inertiajs/react';
import { Pencil, ArrowLeft, ExternalLink } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { statusTone } from '@/Components/Badge';

interface Admin {
    id: number;
    name: string;
    email: string;
}

interface School {
    id: number;
    name: string;
    code: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    plan_tier: string;
    max_students: number;
    max_teachers: number;
    students_count: number;
    users_count: number;
    is_active: boolean;
    subscription_status: string;
    subscription_expires_at: string | null;
    calendar_system: string;
    created_at: string;
    admins: Admin[];
}

interface Props {
    school: School;
}

function UsageBar({ label, used, max, color }: { label: string; used: number; max: number; color: string }) {
    const pct = max > 0 ? Math.min(100, (used / max) * 100) : 0;
    return (
        <div>
            <div className="flex justify-between text-xs mb-1">
                <span className="text-slate-500">{label}</span>
                <span className="text-slate-200">{used} / {max}</span>
            </div>
            <div className="w-full bg-white/10 rounded-full h-1.5">
                <div className={`h-1.5 rounded-full ${color}`} style={{ width: `${pct}%` }} />
            </div>
        </div>
    );
}

function Dl({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className="text-slate-200 mt-0.5 text-sm">{value ?? '—'}</dd>
        </div>
    );
}

export default function Show({ school }: Props) {
    const subTone: Record<string, any> = { active: 'green', grace: 'amber', expired: 'rose', suspended: 'rose' };

    return (
        <AdminLayout>
            <Head title={school.name} />

            <PageHeader
                title={school.name}
                subtitle={`School Code: ${school.code}`}
                actions={
                    <a
                        href={`/admin/schools/${school.id}/edit`}
                        className="inline-flex items-center gap-2 px-4 py-2.5 text-sm rounded-xl font-medium bg-white/5 hover:bg-white/10 text-slate-200 border border-white/10 transition"
                    >
                        <Pencil className="w-4 h-4" />
                        Edit
                    </a>
                }
            />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main details */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">School Details</h3>
                        <dl className="grid grid-cols-2 gap-4">
                            <Dl label="Name" value={school.name} />
                            <Dl label="Code" value={<span className="font-mono">{school.code}</span>} />
                            <Dl label="Email" value={school.email} />
                            <Dl label="Phone" value={school.phone} />
                            <div className="col-span-2">
                                <Dl label="Address" value={school.address} />
                            </div>
                            <Dl label="Calendar" value={<span className="capitalize">{school.calendar_system ?? 'ethiopian'}</span>} />
                            <Dl label="Created" value={new Date(school.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })} />
                        </dl>
                    </div>

                    {/* Admins */}
                    <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">School Admins</h3>
                        {school.admins.length === 0 ? (
                            <p className="text-sm text-slate-500">No school admins assigned</p>
                        ) : (
                            <div className="space-y-2">
                                {school.admins.map(admin => (
                                    <div key={admin.id} className="flex items-center gap-3 py-2">
                                        <div className="w-8 h-8 rounded-full bg-purple-500/20 flex items-center justify-center text-xs font-bold text-purple-400">
                                            {admin.name.slice(0, 1).toUpperCase()}
                                        </div>
                                        <div>
                                            <p className="text-sm text-slate-200">{admin.name}</p>
                                            <p className="text-xs text-slate-500">{admin.email}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    {/* Status */}
                    <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">Status</h3>
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-slate-500">Account</span>
                                <Badge tone={school.is_active ? 'green' : 'rose'} dot>
                                    {school.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-slate-500">Subscription</span>
                                <Badge tone={subTone[school.subscription_status] ?? 'slate'}>
                                    {school.subscription_status}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between">
                                <span className="text-xs text-slate-500">Plan</span>
                                <span className="text-xs capitalize text-slate-200">{school.plan_tier ?? 'starter'}</span>
                            </div>
                            {school.subscription_expires_at && (
                                <div className="flex items-center justify-between">
                                    <span className="text-xs text-slate-500">Expires</span>
                                    <span className="text-xs text-slate-300">
                                        {new Date(school.subscription_expires_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Usage */}
                    <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">Usage</h3>
                        <div className="space-y-4">
                            <UsageBar label="Students" used={school.students_count} max={school.max_students} color="bg-purple-500" />
                            <UsageBar label="Staff" used={school.users_count} max={school.max_teachers} color="bg-blue-500" />
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 flex items-center gap-4">
                <a href="/admin/schools" className="inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-slate-200 transition">
                    <ArrowLeft className="w-4 h-4" />
                    Back to Schools
                </a>
                <a
                    href={`/admin/card-templates/${school.id}/edit`}
                    className="inline-flex items-center gap-1.5 text-sm bg-white/5 border border-white/10 hover:border-purple-500/50 text-slate-300 px-4 py-2 rounded-lg transition"
                >
                    <ExternalLink className="w-3.5 h-3.5" />
                    ID Card Template
                </a>
            </div>
        </AdminLayout>
    );
}
