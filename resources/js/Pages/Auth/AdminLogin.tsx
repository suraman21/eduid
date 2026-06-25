import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';

interface Props {
  errors?: Record<string, string>;
}

export default function AdminLogin({ errors = {} }: Props) {
  const { data, setData, post, processing } = useForm({ email: '', password: '', remember: false });
  const [showPw, setShowPw] = useState(false);

  const submit = (e: React.FormEvent) => { e.preventDefault(); post('/admin/login'); };

  return (
    <>
      <Head title="Super Admin Login" />
      <div className="min-h-screen bg-[#060a14] text-slate-200 flex items-center justify-center px-4 py-12 relative overflow-hidden">
        <div className="absolute top-0 left-1/4 w-96 h-96 bg-amber-600/10 rounded-full blur-3xl pointer-events-none" />
        <div className="absolute bottom-0 right-1/4 w-96 h-96 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none" />

        <div className="w-full max-w-md relative z-10">
          <div className="bg-white/5 backdrop-blur-xl border border-white/10 rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden">
            <div className="absolute top-0 left-0 right-0 h-[2px] bg-gradient-to-r from-transparent via-amber-500 to-transparent opacity-80" />

            <div className="mb-8 text-center">
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-600 to-indigo-600 shadow-lg shadow-amber-500/30 mb-4">
                <svg className="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
              </div>
              <h1 className="text-3xl font-extrabold bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">Arkeon Panel</h1>
              <p className="text-slate-400 text-sm mt-1.5 font-light">Super Admin Control Center</p>
            </div>

            {Object.keys(errors).length > 0 && (
              <div className="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm flex items-start gap-3">
                <svg className="w-5 h-5 shrink-0 text-rose-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                  <span className="font-semibold">Authentication failed</span>
                  <ul className="list-disc list-inside mt-1 font-light text-rose-300/95">
                    {Object.values(errors).map((err, i) => <li key={i}>{err}</li>)}
                  </ul>
                </div>
              </div>
            )}

            <form onSubmit={submit} className="space-y-5">
              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Admin Email</label>
                <div className="relative">
                  <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                    </svg>
                  </span>
                  <input type="email" required value={data.email} onChange={e => setData('email', e.target.value)} autoFocus
                    className="w-full pl-11 pr-4 py-3 rounded-xl text-sm bg-white/5 border border-white/10 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500/40"
                    placeholder="admin@arkeon.com" autoComplete="email" />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                <div className="relative">
                  <span className="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                  </span>
                  <input type={showPw ? 'text' : 'password'} required value={data.password} onChange={e => setData('password', e.target.value)}
                    className="w-full pl-11 pr-10 py-3 rounded-xl text-sm bg-white/5 border border-white/10 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500/40"
                    placeholder="••••••••" autoComplete="current-password" />
                  <button type="button" onClick={() => setShowPw(p => !p)}
                    className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300">
                    {showPw
                      ? <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>
                      : <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    }
                  </button>
                </div>
              </div>

              <div className="flex items-center justify-between text-xs text-slate-400 font-light pt-1">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input type="checkbox" checked={data.remember} onChange={e => setData('remember', e.target.checked)}
                    className="w-4 h-4 rounded border-slate-700 bg-slate-800/50 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900" />
                  <span>Remember me</span>
                </label>
              </div>

              <button type="submit" disabled={processing}
                className="w-full py-3.5 px-4 bg-gradient-to-r from-amber-600 to-indigo-600 hover:from-amber-500 hover:to-indigo-500 text-white font-medium rounded-xl text-sm shadow-lg disabled:opacity-60 transition-all">
                {processing ? 'Signing in…' : 'Sign In to Panel'}
              </button>
            </form>
          </div>

          <div className="mt-6 text-center">
            <a href="/login" className="text-xs text-slate-500 hover:text-slate-300 hover:underline transition">
              Go back to School Portal login →
            </a>
          </div>
        </div>
      </div>
    </>
  );
}
