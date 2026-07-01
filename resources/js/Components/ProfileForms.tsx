import { useForm } from '@inertiajs/react';
import { UserCircle, KeyRound, ShieldCheck } from 'lucide-react';

export interface ProfileData {
    name: string;
    email: string;
    phone: string | null;
    role: string;
    job_title: string | null;
    employee_id: string | null;
    school: { name: string; code: string } | null;
    last_login: string | null;
    member_since: string | null;
}

/**
 * Shared profile management UI for both portals. `dark` switches between the
 * admin (dark) and school (light) design languages so each page stays native
 * to its layout while the behaviour stays identical.
 */
export default function ProfileForms({ profile, updateUrl, passwordUrl, dark }: {
    profile: ProfileData;
    updateUrl: string;
    passwordUrl: string;
    dark: boolean;
}) {
    const t = dark
        ? {
            card: 'bg-white/[0.025] border border-white/10 rounded-xl p-6',
            title: 'text-slate-200 font-semibold flex items-center gap-2',
            sub: 'text-xs text-slate-500 mt-0.5 mb-4',
            label: 'block text-xs text-slate-400 mb-1',
            input: 'w-full bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-purple-500',
            btn: 'bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg transition',
            err: 'text-xs text-rose-400 mt-1',
            meta: 'text-slate-500', metaVal: 'text-slate-300', icon: 'text-purple-400',
        }
        : {
            card: 'bg-white border border-slate-200 rounded-xl p-6 shadow-sm',
            title: 'text-slate-800 font-semibold flex items-center gap-2',
            sub: 'text-xs text-slate-500 mt-0.5 mb-4',
            label: 'block text-xs text-slate-600 mb-1',
            input: 'w-full bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-500',
            btn: 'bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-medium px-5 py-2 rounded-lg transition',
            err: 'text-xs text-rose-600 mt-1',
            meta: 'text-slate-500', metaVal: 'text-slate-800', icon: 'text-indigo-500',
        };

    const info = useForm({
        name: profile.name, email: profile.email, phone: profile.phone ?? '', current_password: '',
    });
    const pass = useForm({ current_password: '', password: '', password_confirmation: '' });

    const emailChanged = info.data.email.toLowerCase() !== profile.email.toLowerCase();

    const saveInfo = (e: React.FormEvent) => {
        e.preventDefault();
        info.patch(updateUrl, { preserveScroll: true, onSuccess: () => info.setData('current_password', '') });
    };
    const savePass = (e: React.FormEvent) => {
        e.preventDefault();
        pass.put(passwordUrl, { preserveScroll: true, onSuccess: () => pass.reset() });
    };

    const field = (form: any, key: string, label: string, type = 'text', props: any = {}) => (
        <div>
            <label className={t.label}>{label}</label>
            <input type={type} value={form.data[key]} onChange={e => form.setData(key, e.target.value)} className={t.input} {...props} />
            {form.errors[key] && <p className={t.err}>{form.errors[key]}</p>}
        </div>
    );

    return (
        <div className="max-w-2xl space-y-6">
            {/* Account overview */}
            <div className={t.card}>
                <p className={t.title}><ShieldCheck className={`w-4 h-4 ${t.icon}`} /> Account</p>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm mt-4">
                    <div><p className={`text-xs uppercase ${t.meta}`}>Role</p><p className={`${t.metaVal} capitalize mt-0.5`}>{profile.role.replace(/_/g, ' ')}</p></div>
                    <div><p className={`text-xs uppercase ${t.meta}`}>{profile.school ? 'School' : 'Organization'}</p><p className={`${t.metaVal} mt-0.5`}>{profile.school ? `${profile.school.name}` : 'Arkeon (Platform)'}</p></div>
                    <div><p className={`text-xs uppercase ${t.meta}`}>Last sign-in</p><p className={`${t.metaVal} mt-0.5`}>{profile.last_login ?? '—'}</p></div>
                    <div><p className={`text-xs uppercase ${t.meta}`}>Member since</p><p className={`${t.metaVal} mt-0.5`}>{profile.member_since ?? '—'}</p></div>
                </div>
                {profile.employee_id && (
                    <p className={`text-xs mt-3 ${t.meta}`}>Employee ID: <span className="font-mono">{profile.employee_id}</span>{profile.job_title ? ` · ${profile.job_title}` : ''}</p>
                )}
            </div>

            {/* Profile information */}
            <form onSubmit={saveInfo} className={t.card}>
                <p className={t.title}><UserCircle className={`w-4 h-4 ${t.icon}`} /> Profile information</p>
                <p className={t.sub}>Your name, sign-in email and contact phone.</p>
                <div className="grid sm:grid-cols-2 gap-4">
                    {field(info, 'name', 'Full name')}
                    {field(info, 'email', 'Email address', 'email')}
                    {field(info, 'phone', 'Phone (optional)', 'tel', { placeholder: '+2519…' })}
                    {emailChanged && field(info, 'current_password', 'Current password (required to change email)', 'password', { autoComplete: 'current-password' })}
                </div>
                {emailChanged && (
                    <p className={`text-xs mt-3 ${t.meta}`}>Changing your email changes how you sign in — confirm with your current password.</p>
                )}
                <button type="submit" disabled={info.processing} className={`${t.btn} mt-5`}>
                    {info.processing ? 'Saving…' : 'Save changes'}
                </button>
            </form>

            {/* Change password */}
            <form onSubmit={savePass} className={t.card}>
                <p className={t.title}><KeyRound className={`w-4 h-4 ${t.icon}`} /> Change password</p>
                <p className={t.sub}>Minimum 8 characters. Changing it signs out any mobile app sessions.</p>
                <div className="grid sm:grid-cols-3 gap-4">
                    {field(pass, 'current_password', 'Current password', 'password', { autoComplete: 'current-password' })}
                    {field(pass, 'password', 'New password', 'password', { autoComplete: 'new-password' })}
                    {field(pass, 'password_confirmation', 'Confirm new password', 'password', { autoComplete: 'new-password' })}
                </div>
                <button type="submit" disabled={pass.processing} className={`${t.btn} mt-5`}>
                    {pass.processing ? 'Updating…' : 'Update password'}
                </button>
            </form>
        </div>
    );
}
