import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';
import { Eye, EyeOff } from 'lucide-react';

interface Props {
    reset_status?: string | null;
}

export default function Login({ reset_status }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        school_code: '',
        email: '',
        password: '',
        remember: false as boolean,
    });

    const [showPassword, setShowPassword] = React.useState(false);

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/login');
    }

    const inputCls = 'w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition';
    const labelCls = 'block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5';
    const errorCls = 'text-red-400 text-xs mt-1';

    return (
        <AuthLayout title="EduID Portal" subtitle="Sign in to your school account">
            <Head title="Sign In" />

            {reset_status && (
                <div className="mb-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm text-emerald-400">
                    {reset_status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <label className={labelCls}>School Code</label>
                    <input
                        type="text"
                        className={inputCls}
                        placeholder="e.g. ABC123"
                        value={data.school_code}
                        onChange={e => setData('school_code', e.target.value)}
                        autoComplete="organization"
                        autoFocus
                    />
                    {errors.school_code && <p className={errorCls}>{errors.school_code}</p>}
                </div>

                <div>
                    <label className={labelCls}>Email Address</label>
                    <input
                        type="email"
                        className={inputCls}
                        placeholder="you@school.edu.et"
                        value={data.email}
                        onChange={e => setData('email', e.target.value)}
                        autoComplete="email"
                    />
                    {errors.email && <p className={errorCls}>{errors.email}</p>}
                </div>

                <div>
                    <label className={labelCls}>Password</label>
                    <div className="relative">
                        <input
                            type={showPassword ? 'text' : 'password'}
                            className={`${inputCls} pr-11`}
                            placeholder="••••••••"
                            value={data.password}
                            onChange={e => setData('password', e.target.value)}
                            autoComplete="current-password"
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

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            className="rounded border-white/20 bg-white/5 text-purple-500 focus:ring-purple-500/50"
                            checked={data.remember}
                            onChange={e => setData('remember', e.target.checked)}
                        />
                        <span className="text-sm text-slate-400">Remember me</span>
                    </label>
                    <Link href="/forgot-password" className="text-sm text-purple-400 hover:text-purple-300 transition">
                        Forgot password?
                    </Link>
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full py-3 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 disabled:opacity-60 transition shadow-lg shadow-purple-500/20"
                >
                    {processing ? 'Signing in…' : 'Sign In'}
                </button>
            </form>
        </AuthLayout>
    );
}
