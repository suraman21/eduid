import { Head, useForm, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

/**
 * Super-admin 2FA step: enter the 6-digit code emailed after the password
 * check. The session is not authenticated until this succeeds (PRD §15).
 */
export default function AdminOtp() {
    const form = useForm({ otp: '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post('/admin/login/verify');
    }

    return (
        <div className="min-h-screen bg-[#060a14] flex items-center justify-center p-6">
            <Head title="Verify — Admin" />
            <div className="w-full max-w-sm">
                <div className="bg-white/[0.03] border border-white/10 rounded-2xl p-8">
                    <div className="w-11 h-11 rounded-xl bg-purple-600/20 border border-purple-500/30 flex items-center justify-center mb-5">
                        <ShieldCheck className="w-5 h-5 text-purple-400" />
                    </div>
                    <h1 className="text-lg font-bold text-white mb-1">Check your email</h1>
                    <p className="text-sm text-slate-400 mb-6">
                        We sent a 6-digit verification code to your admin email. It expires in 10 minutes.
                    </p>

                    <form onSubmit={submit}>
                        <input
                            type="text"
                            inputMode="numeric"
                            autoComplete="one-time-code"
                            maxLength={6}
                            autoFocus
                            value={form.data.otp}
                            onChange={e => form.setData('otp', e.target.value.replace(/\D/g, ''))}
                            placeholder="••••••"
                            className="w-full text-center tracking-[0.6em] text-xl font-bold bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white placeholder-slate-600 focus:outline-none focus:border-purple-500 mb-2"
                        />
                        {form.errors.otp && <p className="text-xs text-rose-400 mb-2">{form.errors.otp}</p>}

                        <button
                            type="submit"
                            disabled={form.processing || form.data.otp.length !== 6}
                            className="w-full bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 disabled:opacity-40 text-white text-sm font-semibold py-3 rounded-xl transition mt-2"
                        >
                            {form.processing ? 'Verifying…' : 'Verify & Sign in'}
                        </button>
                    </form>

                    <p className="text-xs text-slate-500 mt-5 text-center">
                        Didn’t get it? <Link href="/admin/login" className="text-purple-400 hover:text-purple-300">Sign in again</Link> to receive a new code.
                    </p>
                </div>
            </div>
        </div>
    );
}
