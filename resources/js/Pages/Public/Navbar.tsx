import { useState } from 'react';
import { IdCard, Menu, X, ArrowRight } from 'lucide-react';
import { Link } from '@inertiajs/react';

interface NavbarProps {
  onOpenDemo: () => void;
}

export default function Navbar({ onOpenDemo }: NavbarProps) {
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);

  return (
    <nav id="navbar" className="relative w-full z-50 bg-white/85 backdrop-blur-md border-b border-slate-200 transition-all duration-300">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-16 items-center">
          <a href="/" className="flex items-center gap-2">
            <div className="p-1.5 bg-secondary rounded-lg text-white flex items-center justify-center">
              <IdCard className="w-6 h-6" />
            </div>
            <span className="font-display text-xl font-extrabold text-primary tracking-tight">
              Edu<span className="text-secondary font-bold">ID</span>
            </span>
          </a>

          <div className="hidden md:flex items-center gap-8">
            <a href="#features" className="font-sans text-sm font-medium text-slate-600 hover:text-secondary transition-colors duration-200">Features</a>
            <a href="#how-it-works" className="font-sans text-sm font-medium text-slate-600 hover:text-secondary transition-colors duration-200">How It Works</a>
            <a href="#pricing" className="font-sans text-sm font-medium text-slate-600 hover:text-secondary transition-colors duration-200">Pricing</a>
            <a href="#faq" className="font-sans text-sm font-medium text-slate-600 hover:text-secondary transition-colors duration-200">FAQ</a>
            <Link
              href="/login"
              className="bg-secondary text-white hover:bg-emerald-700 px-5 py-2.5 rounded-lg font-sans text-sm font-semibold transition-all shadow-sm active:scale-[0.98] duration-150 border border-emerald-800/20"
            >
              School Login
            </Link>
          </div>

          <div className="md:hidden">
            <button
              onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
              className="inline-flex items-center justify-center p-2 rounded-md text-slate-500 hover:text-primary hover:bg-slate-100 focus:outline-none transition-colors"
            >
              <span className="sr-only">Open main menu</span>
              {isMobileMenuOpen ? <X className="block h-6 w-6" /> : <Menu className="block h-6 w-6" />}
            </button>
          </div>
        </div>
      </div>

      {isMobileMenuOpen && (
        <div className="md:hidden bg-white border-b border-slate-200 shadow-lg animate-fadeIn">
          <div className="px-2 pt-2 pb-3 space-y-1 sm:px-3">
            <a href="#features" onClick={() => setIsMobileMenuOpen(false)} className="block px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:text-secondary hover:bg-slate-50 transition-colors">Features</a>
            <a href="#how-it-works" onClick={() => setIsMobileMenuOpen(false)} className="block px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:text-secondary hover:bg-slate-50 transition-colors">How It Works</a>
            <a href="#pricing" onClick={() => setIsMobileMenuOpen(false)} className="block px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:text-secondary hover:bg-slate-50 transition-colors">Pricing</a>
            <a href="#faq" onClick={() => setIsMobileMenuOpen(false)} className="block px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:text-secondary hover:bg-slate-50 transition-colors">FAQ</a>
            <div className="pt-4 pb-2 px-3">
              <Link
                href="/login"
                className="w-full flex justify-center items-center gap-2 bg-secondary text-white py-3 px-4 rounded-lg font-semibold hover:bg-emerald-700 transition-all shadow-md border border-emerald-800/20"
              >
                School Login <ArrowRight className="w-4 h-4" />
              </Link>
            </div>
          </div>
        </div>
      )}
    </nav>
  );
}
