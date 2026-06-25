import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import PageHeader from '@/Components/PageHeader';

interface School {
    id?: number;
    name: string;
    code: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    plan_tier: string;
    max_students: number;
    max_teachers: number;
    is_active: boolean;
    subscription_status: string;
    calendar_system: string;
}

interface Props {
    school: School | null;
}

const inputClass =
    'w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-purple-500 focus:ring-1 focus:ring-purple-500 transition';
const labelClass = 'block text-xs font-medium text-slate-400 mb-1';
const errorClass = 'text-rose-400 text-xs mt-1';

function Field({ label, error, children }: { label: string; error?: string; children: React.ReactNode }) {
    return (
        <div>
            <label className={labelClass}>{label}</label>
            {children}
            {error && <p className={errorClass}>{error}</p>}
        </div>
    );
}

export default function Form({ school }: Props) {
    const isEdit = !!school?.id;

    const { data, setData, post, put, processing, errors } = useForm({
        name:                school?.name ?? '',
        code:                school?.code ?? '',
        email:               school?.email ?? '',
        phone:               school?.phone ?? '',
        address:             school?.address ?? '',
        plan_tier:           school?.plan_tier ?? 'starter',
        max_students:        school?.max_students ?? 500,
        max_teachers:        school?.max_teachers ?? 50,
        is_active:           school?.is_active ?? true,
        subscription_status: school?.subscription_status ?? 'active',
        calendar_system:     school?.calendar_system ?? 'ethiopian',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (isEdit) {
            put(`/admin/schools/${school!.id}`);
        } else {
            post('/admin/schools');
        }
    }

    return (
        <AdminLayout>
            <Head title={isEdit ? 'Edit School' : 'Add School'} />

            <PageHeader
                title={isEdit ? 'Edit School' : 'Add School'}
                subtitle={isEdit ? school!.name : 'Register a new school'}
            />

            <form onSubmit={submit} className="max-w-2xl">
                <div className="bg-white/[0.025] rounded-xl border border-white/10 divide-y divide-white/10">

                    {/* Basic Info */}
                    <div className="p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">Basic Information</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div className="sm:col-span-2">
                                <Field label="School Name *" error={errors.name}>
                                    <input
                                        type="text"
                                        value={data.name}
                                        onChange={e => setData('name', e.target.value)}
                                        required
                                        className={inputClass}
                                    />
                                </Field>
                            </div>
                            <Field label="School Code *" error={errors.code}>
                                <input
                                    type="text"
                                    value={data.code}
                                    onChange={e => setData('code', e.target.value)}
                                    required
                                    maxLength={20}
                                    className={`${inputClass} font-mono`}
                                />
                            </Field>
                            <Field label="Calendar System *" error={errors.calendar_system}>
                                <select
                                    value={data.calendar_system}
                                    onChange={e => setData('calendar_system', e.target.value)}
                                    required
                                    className={inputClass}
                                >
                                    <option value="ethiopian">Ethiopian</option>
                                    <option value="gregorian">Gregorian</option>
                                </select>
                            </Field>
                            <Field label="Email" error={errors.email}>
                                <input
                                    type="email"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                    className={inputClass}
                                />
                            </Field>
                            <Field label="Phone" error={errors.phone}>
                                <input
                                    type="text"
                                    value={data.phone}
                                    onChange={e => setData('phone', e.target.value)}
                                    className={inputClass}
                                />
                            </Field>
                            <div className="sm:col-span-2">
                                <Field label="Address" error={errors.address}>
                                    <textarea
                                        value={data.address}
                                        onChange={e => setData('address', e.target.value)}
                                        rows={2}
                                        className={`${inputClass} resize-none`}
                                    />
                                </Field>
                            </div>
                        </div>
                    </div>

                    {/* Plan & Limits */}
                    <div className="p-6">
                        <h3 className="text-sm font-semibold text-slate-100 mb-4">Plan & Limits</h3>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <Field label="Plan Tier *" error={errors.plan_tier}>
                                <select
                                    value={data.plan_tier}
                                    onChange={e => setData('plan_tier', e.target.value)}
                                    required
                                    className={inputClass}
                                >
                                    <option value="starter">Starter</option>
                                    <option value="standard">Standard</option>
                                    <option value="premium">Premium</option>
                                </select>
                            </Field>
                            <Field label="Max Students *" error={errors.max_students}>
                                <input
                                    type="number"
                                    value={data.max_students}
                                    onChange={e => setData('max_students', parseInt(e.target.value))}
                                    required
                                    min={1}
                                    className={inputClass}
                                />
                            </Field>
                            <Field label="Max Teachers *" error={errors.max_teachers}>
                                <input
                                    type="number"
                                    value={data.max_teachers}
                                    onChange={e => setData('max_teachers', parseInt(e.target.value))}
                                    required
                                    min={1}
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                    </div>

                    {/* Subscription — edit only */}
                    {isEdit && (
                        <div className="p-6">
                            <h3 className="text-sm font-semibold text-slate-100 mb-4">Subscription</h3>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <Field label="Status" error={errors.subscription_status}>
                                    <select
                                        value={data.subscription_status}
                                        onChange={e => setData('subscription_status', e.target.value)}
                                        className={inputClass}
                                    >
                                        <option value="active">Active</option>
                                        <option value="grace">Grace Period</option>
                                        <option value="expired">Expired</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </Field>
                                <div className="flex items-end pb-1">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.is_active}
                                            onChange={e => setData('is_active', e.target.checked)}
                                            className="w-4 h-4 rounded border-white/20 bg-white/5 text-purple-500 focus:ring-purple-500 focus:ring-offset-0"
                                        />
                                        <span className="text-sm text-slate-300">School is active</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Actions */}
                <div className="flex items-center gap-3 mt-6">
                    <button
                        type="submit"
                        disabled={processing}
                        className="inline-flex items-center justify-center px-6 py-2.5 text-sm rounded-xl font-medium bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 text-white shadow-lg shadow-purple-500/20 transition-all duration-200 disabled:opacity-50"
                    >
                        {processing ? 'Saving…' : isEdit ? 'Update School' : 'Create School'}
                    </button>
                    <a href="/admin/schools" className="text-slate-400 hover:text-slate-200 px-4 py-2.5 text-sm transition">
                        Cancel
                    </a>
                </div>
            </form>
        </AdminLayout>
    );
}
