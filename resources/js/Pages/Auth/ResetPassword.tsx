import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Eye, EyeOff } from 'lucide-react';

interface Props {
    school_code: string;
    email: string;
    status?: string | null;
}

export default function ResetPassword({ school_code, email, status }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        school_code,
        email,
        otp: '',
        password: '',
        password_confirmation: '',
    });

    const [showPassword, setShowPassword] = React.useState(false);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/reset-password');
    }

    const inputCls = 'w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition';
    const labelCls = 'block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5';
    const errorCls = 'text-red-400 text-xs mt-1';

    return (
        <AuthLayout title="Enter Reset Code" subtitle={`Code sent to ${email}`}>
            <Head title="Reset Password" />

            {status && (
                <div className="mb-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm text-emerald-400">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                {/* Hidden identity fields */}
                <input type="hidden" value={data.school_code} />
                <input type="hidden" value={data.email} />

                <div>
                    <label className={labelCls}>6-Digit Code</label>
                    <input
                        type="text"
                        inputMode="numeric"
                        maxLength={6}
                        className={`${inputCls} text-center text-2xl font-bold tracking-[0.5em] font-mono`}
                        placeholder="——————"
                        value={data.otp}
                        onChange={e => setData('otp', e.target.value.replace(/\D/g, '').slice(0, 6))}
                        autoFocus
                        autoComplete="one-time-code"
                    />
                    {errors.otp && <p className={errorCls}>{errors.otp}</p>}
                </div>

                <div>
                    <label className={labelCls}>New Password</label>
                    <div className="relative">
                        <input
                            type={showPassword ? 'text' : 'password'}
                            className={`${inputCls} pr-11`}
                            placeholder="Min 8 characters"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            autoComplete="new-password"
                        />
                        <button
                            type="button"
                            className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition"
                            onClick={() => setShowPassword(v => !v)}
                        >
                            {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                    </div>
                    {errors.password && <p className={errorCls}>{errors.password}</p>}
                </div>

                <div>
                    <label className={labelCls}>Confirm New Password</label>
                    <input
                        type={showPassword ? 'text' : 'password'}
                        className={inputCls}
                        placeholder="Repeat password"
                        value={data.password_confirmation}
                        onChange={e => setData('password_confirmation', e.target.value)}
                        autoComplete="new-password"
                    />
                    {errors.password_confirmation && <p className={errorCls}>{errors.password_confirmation}</p>}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full py-3 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 disabled:opacity-60 transition shadow-lg shadow-purple-500/20"
                >
                    {processing ? 'Resetting…' : 'Reset Password'}
                </button>
            </form>

            <p className="mt-6 text-center text-xs text-slate-500">
                Didn't get the code?{' '}
                <Link href="/forgot-password" className="text-purple-400 hover:text-purple-300 transition">
                    Request a new one
                </Link>
            </p>
        </AuthLayout>
    );
}
