import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';

interface Props {
    status?: string | null;
}

export default function ForgotPassword({ status }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        school_code: '',
        email: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/forgot-password');
    }

    const inputCls = 'w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition';
    const labelCls = 'block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5';
    const errorCls = 'text-red-400 text-xs mt-1';

    return (
        <AuthLayout title="Reset Password" subtitle="We'll email you a 6-digit code">
            <Head title="Forgot Password" />

            {status && (
                <div className="mb-5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 px-4 py-3 text-sm text-emerald-400">
                    {status}
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

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full py-3 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 disabled:opacity-60 transition shadow-lg shadow-purple-500/20"
                >
                    {processing ? 'Sending…' : 'Send Reset Code'}
                </button>
            </form>

            <p className="mt-6 text-center text-xs text-slate-500">
                Remembered it?{' '}
                <Link href="/login" className="text-purple-400 hover:text-purple-300 transition">
                    Back to sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
