import { IdCard } from 'lucide-react';

export default function Footer() {
  const currentYear = new Date().getFullYear();

  return (
    <footer className="bg-primary text-white py-16 px-4 sm:px-6 lg:px-8 border-t border-slate-800">
      <div className="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-8">
        <div className="flex flex-col items-center md:items-start gap-2 text-center md:text-left">
          <a href="/" className="flex items-center gap-2">
            <div className="p-1.5 bg-white/10 rounded-lg text-emerald-400 flex items-center justify-center">
              <IdCard className="w-5 h-5" />
            </div>
            <span className="font-display text-lg font-extrabold tracking-tight">
              Edu<span className="text-emerald-400">ID</span>
            </span>
          </a>
          <p className="font-sans text-xs text-slate-400 font-medium">
            © {currentYear} EduID Systems. All rights reserved. Precision identity for modern campuses.
          </p>
        </div>
        <div className="flex flex-wrap justify-center gap-8 text-xs font-semibold text-slate-400">
          <a href="#" className="hover:text-white transition-colors">Privacy Policy</a>
          <a href="#" className="hover:text-white transition-colors">Terms of Service</a>
        </div>
      </div>
    </footer>
  );
}
