import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';
import Badge, { type Tone } from '@/Components/Badge';

interface Lead {
    id: number;
    name: string;
    school_name: string;
    phone: string;
    email: string | null;
    message: string | null;
    status: string;
    admin_notes: string | null;
    created_at: string;
    contacted_at: string | null;
}

interface Props {
    lead: Lead;
}

const statusTone: Record<string, Tone> = {
    new:            'amber',
    contacted:      'blue',
    demo_scheduled: 'purple',
    converted:      'green',
    lost:           'slate',
};

const statusLabel: Record<string, string> = {
    new:            'New',
    contacted:      'Contacted',
    demo_scheduled: 'Demo Scheduled',
    converted:      'Converted',
    lost:           'Lost',
};

const inputClass = 'w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition';

function Row({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="py-3 grid grid-cols-3 gap-4 border-b border-white/5 last:border-0">
            <dt className="text-xs font-medium text-slate-500 uppercase">{label}</dt>
            <dd className="col-span-2 text-sm text-slate-200">{value ?? '—'}</dd>
        </div>
    );
}

export default function Show({ lead }: Props) {
    const { data, setData, patch, processing } = useForm({
        status:      lead.status,
        admin_notes: lead.admin_notes ?? '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch(`/admin/leads/${lead.id}`);
    }

    const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

    return (
        <AdminLayout>
            <Head title={`Lead — ${lead.name}`} />

            <div className="mb-4">
                <a href="/admin/leads" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-purple-400 transition">
                    <ArrowLeft className="w-4 h-4" />
                    Back to Leads
                </a>
            </div>

            <PageHeader
                title={lead.name}
                subtitle="Lead detail"
                actions={<Badge tone={statusTone[lead.status] ?? 'slate'}>{statusLabel[lead.status] ?? lead.status}</Badge>}
            />

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Detail */}
                <div className="lg:col-span-2">
                    <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">Lead Information</h3>
                        <dl>
                            <Row label="Name"      value={lead.name} />
                            <Row label="School"    value={lead.school_name} />
                            <Row label="Phone"     value={lead.phone} />
                            <Row label="Email"     value={lead.email} />
                            <Row label="Message"   value={<span className="whitespace-pre-wrap">{lead.message}</span>} />
                            <Row label="Submitted" value={fmt(lead.created_at)} />
                            {lead.contacted_at && <Row label="Contacted" value={fmt(lead.contacted_at)} />}
                        </dl>
                    </div>
                </div>

                {/* Status update */}
                <div>
                    <div className="bg-white/[0.025] rounded-xl border border-white/10 p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">Update Status</h3>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-slate-400 mb-1">Status</label>
                                <select
                                    value={data.status}
                                    onChange={e => setData('status', e.target.value)}
                                    className={inputClass}
                                >
                                    <option value="new">New</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="demo_scheduled">Demo Scheduled</option>
                                    <option value="converted">Converted</option>
                                    <option value="lost">Lost</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-slate-400 mb-1">Admin Notes</label>
                                <textarea
                                    value={data.admin_notes}
                                    onChange={e => setData('admin_notes', e.target.value)}
                                    rows={5}
                                    className={`${inputClass} resize-none`}
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full inline-flex items-center justify-center px-4 py-2.5 text-sm rounded-xl font-medium bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white shadow-lg shadow-purple-500/20 transition-all duration-200 disabled:opacity-50"
                            >
                                {processing ? 'Saving…' : 'Save Changes'}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
