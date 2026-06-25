import React, { useState } from 'react';
import { IdCard, X, Lock, Mail, ArrowRight, Sparkles } from 'lucide-react';

interface Props {
  onClose: () => void;
  onLaunchAdmin: () => void;
}

export default function LoginModal({ onClose, onLaunchAdmin }: Props) {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setTimeout(() => { setIsLoading(false); onLaunchAdmin(); }, 1000);
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-md flex items-center justify-center p-4">
      <div className="bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-slate-200/80 animate-fadeIn">

        <div className="bg-primary text-white px-6 py-5 flex justify-between items-center border-b border-slate-800">
          <div className="flex items-center gap-2">
            <div className="p-1.5 bg-secondary rounded-lg flex items-center justify-center">
              <IdCard className="w-5 h-5 text-white" />
            </div>
            <span className="font-display font-extrabold text-base tracking-tight text-white">EduID Demo System</span>
          </div>
          <button onClick={onClose} className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6 sm:p-8 space-y-6">
          <div className="text-center">
            <h4 className="font-display font-extrabold text-lg text-primary">Demo Sandbox Access</h4>
            <p className="text-xs text-slate-500 font-sans mt-1">Explore the admin dashboard sandbox or use your real school login</p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-1">
              <label className="block text-[10px] font-bold text-slate-500 uppercase">Email</label>
              <div className="relative">
                <Mail className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                <input type="email" required value={email} onChange={e => setEmail(e.target.value)} className="pl-9 pr-3 py-2.5 w-full text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="admin@school.edu" />
              </div>
            </div>
            <div className="space-y-1">
              <label className="block text-[10px] font-bold text-slate-500 uppercase">Password</label>
              <div className="relative">
                <Lock className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                <input type="password" required value={password} onChange={e => setPassword(e.target.value)} className="pl-9 pr-3 py-2.5 w-full text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary" placeholder="••••••••" />
              </div>
            </div>
            <button type="submit" disabled={isLoading} className="w-full bg-secondary hover:bg-emerald-700 text-white py-3 rounded-lg font-display font-bold text-xs transition-all shadow-sm flex items-center justify-center gap-2">
              {isLoading ? (
                <><div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" /><span>Loading...</span></>
              ) : (
                <><span>Launch Demo Admin</span><ArrowRight className="w-3.5 h-3.5" /></>
              )}
            </button>
          </form>

          <div className="relative flex items-center justify-center py-2">
            <div className="absolute inset-0 flex items-center"><div className="w-full border-t border-slate-200" /></div>
            <span className="relative px-3 bg-white text-[10px] font-bold text-slate-400 uppercase tracking-wider">Or skip to sandbox</span>
          </div>

          <div className="bg-slate-50 border border-slate-200 p-4 rounded-xl text-center space-y-3">
            <p className="text-[11px] text-slate-500 font-medium leading-relaxed">
              Instantly launch our interactive demo dashboard with 250+ students pre-loaded!
            </p>
            <button
              onClick={() => { onClose(); onLaunchAdmin(); }}
              className="w-full bg-secondary hover:brightness-110 text-white font-display font-extrabold text-xs py-2.5 rounded-lg shadow-md flex items-center justify-center gap-2"
            >
              <Sparkles className="w-4 h-4" />
              <span>Launch Demo Admin Suite</span>
            </button>
          </div>
        </div>

        <div className="bg-slate-100/60 p-4 text-center border-t border-slate-100 text-[10px] text-slate-400 font-medium">
          This is a demo — for real school access <a href="/login" className="text-secondary font-bold underline">sign in here</a>.
        </div>
      </div>
    </div>
  );
}
