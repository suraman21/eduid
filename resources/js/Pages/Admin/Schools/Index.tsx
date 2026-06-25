import { Head } from '@inertiajs/react';
import { Plus, Eye, Pencil } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { type Tone } from '@/Components/Badge';
import DataTable, { type Column } from '@/Components/DataTable';

interface School {
    id: number;
    name: string;
    code: string;
    email: string | null;
    plan_tier: string;
    students_count: number;
    users_count: number;
    is_active: boolean;
    subscription_status: string;
}

interface Props {
    schools: School[];
}

const planTone: Record<string, Tone> = {
    premium:  'amber',
    standard: 'blue',
    starter:  'slate',
};

const subTone: Record<string, Tone> = {
    active:    'green',
    grace:     'amber',
    expired:   'rose',
    suspended: 'rose',
};

export default function Index({ schools }: Props) {
    const columns: Column<School>[] = [
        {
            key: 'name',
            header: 'School',
            sortable: true,
            render: s => (
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center text-xs font-bold text-purple-400">
                        {s.name.slice(0, 2).toUpperCase()}
                    </div>
                    <div>
                        <p className="font-medium text-slate-100">{s.name}</p>
                        {s.email && <p className="text-xs text-slate-500">{s.email}</p>}
                    </div>
                </div>
            ),
        },
        {
            key: 'code',
            header: 'Code',
            sortable: true,
            render: s => <span className="font-mono text-xs text-slate-400">{s.code}</span>,
        },
        {
            key: 'plan_tier',
            header: 'Plan',
            sortable: true,
            render: s => <Badge tone={planTone[s.plan_tier] ?? 'slate'}>{s.plan_tier}</Badge>,
        },
        {
            key: 'students_count',
            header: 'Students',
            sortable: true,
            render: s => <span className="text-slate-300">{s.students_count}</span>,
        },
        {
            key: 'users_count',
            header: 'Staff',
            sortable: true,
            render: s => <span className="text-slate-300">{s.users_count}</span>,
        },
        {
            key: 'is_active',
            header: 'Status',
            sortable: true,
            render: s => (
                <Badge tone={s.is_active ? 'green' : 'rose'} dot>
                    {s.is_active ? 'Active' : 'Inactive'}
                </Badge>
            ),
        },
        {
            key: 'subscription_status',
            header: 'Subscription',
            sortable: true,
            render: s => <Badge tone={subTone[s.subscription_status] ?? 'slate'}>{s.subscription_status}</Badge>,
        },
        {
            key: 'actions',
            header: '',
            render: s => (
                <div className="flex items-center gap-2" onClick={e => e.stopPropagation()}>
                    <a href={`/admin/schools/${s.id}`} title="View" className="text-slate-500 hover:text-purple-400 transition">
                        <Eye className="w-4 h-4" />
                    </a>
                    <a href={`/admin/schools/${s.id}/edit`} title="Edit" className="text-slate-500 hover:text-purple-400 transition">
                        <Pencil className="w-4 h-4" />
                    </a>
                </div>
            ),
        },
    ];

    return (
        <AdminLayout>
            <Head title="Schools" />

            <PageHeader
                title="Schools"
                subtitle="Manage all registered schools."
                actions={
                    <a
                        href="/admin/schools/create"
                        className="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm rounded-xl font-medium bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white shadow-lg shadow-purple-500/20 transition-all duration-200"
                    >
                        <Plus className="w-4 h-4" strokeWidth={2} />
                        Add School
                    </a>
                }
            />

            <DataTable
                columns={columns}
                rows={schools}
                searchKeys={['name', 'code', 'email']}
                rowHref={s => `/admin/schools/${s.id}`}
                emptyMessage="No schools found."
            />
        </AdminLayout>
    );
}
