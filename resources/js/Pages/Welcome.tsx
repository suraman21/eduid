import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { ArrowRight, Command } from 'lucide-react';
import Navbar from './Public/Navbar';
import HeroSection from './Public/HeroSection';
import FeaturesSection from './Public/FeaturesSection';
import HowItWorksSection from './Public/HowItWorksSection';
import PricingSection from './Public/PricingSection';
import DemoSection from './Public/DemoSection';
import FAQSection from './Public/FAQSection';
import Footer from './Public/Footer';
import AdminPanel from './Public/AdminPanel';
import LoginModal from './Public/LoginModal';

interface Props {
  success?: string | null;
  error?: string | null;
}

export default function Welcome({ success, error }: Props) {
  const [isAdminOpen, setIsAdminOpen] = useState(false);
  const [isLoginOpen, setIsLoginOpen] = useState(false);
  const [toastMessage, setToastMessage] = useState<string | null>(null);
  const [mousePosition, setMousePosition] = useState({ x: -100, y: -100 });
  const [isHovered, setIsHovered] = useState(false);
  const [isMobile, setIsMobile] = useState(false);

  useEffect(() => {
    const checkTouch = () => setIsMobile(window.matchMedia('(pointer: coarse)').matches);
    checkTouch();
    window.addEventListener('resize', checkTouch);
    const handleMouseMove = (e: MouseEvent) => setMousePosition({ x: e.clientX, y: e.clientY });
    const handleMouseOver = (e: MouseEvent) => {
      const target = e.target as HTMLElement;
      setIsHovered(!!(target.tagName === 'BUTTON' || target.tagName === 'A' || target.closest('button') || target.closest('a') || target.closest('[role="button"]') || target.closest('.cursor-pointer')));
    };
    window.addEventListener('mousemove', handleMouseMove);
    window.addEventListener('mouseover', handleMouseOver);
    return () => {
      window.removeEventListener('resize', checkTouch);
      window.removeEventListener('mousemove', handleMouseMove);
      window.removeEventListener('mouseover', handleMouseOver);
    };
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => {
      showToast("💡 Welcome! Customize the card sandbox or click 'School Login' to sign in to your real portal.");
    }, 1500);
    return () => clearTimeout(timer);
  }, []);

  const showToast = (message: string) => {
    setToastMessage(message);
    setTimeout(() => setToastMessage(null), 6000);
  };

  const handleOpenDemoForm = () => {
    document.getElementById('contact')?.scrollIntoView({ behavior: 'smooth' });
    showToast("📝 Fill out the request form below to try our premium PVC cards!");
  };

  const handleSelectPlan = (planName: string) => {
    showToast(`💎 Selected the ${planName} plan! Opening the administrative portal sandbox...`);
    setIsAdminOpen(true);
  };

  return (
    <div className="relative min-h-screen bg-[#F8FAFC] overflow-x-hidden selection:bg-secondary/20 selection:text-primary">
      <Head title="EduID — Smart Student Identity & Attendance System">
        <meta name="description" content="Professional PVC student ID cards with QR attendance tracking. Built for Ethiopian schools. Setup in 48 hours." />
      </Head>

      {/* Custom cursor (desktop only) */}
      {!isMobile && (
        <>
          <div
            className={`fixed top-0 left-0 rounded-full border pointer-events-none z-[9999] transition-all duration-150 ease-out ${isHovered ? 'w-12 h-12 bg-secondary/10 border-secondary/60 scale-110' : 'w-8 h-8 border-secondary/40'}`}
            style={{ transform: `translate3d(calc(${mousePosition.x}px - 50%), calc(${mousePosition.y}px - 50%), 0)` }}
          />
          <div
            className="fixed top-0 left-0 w-2 h-2 bg-secondary rounded-full pointer-events-none z-[9999] transition-transform duration-75"
            style={{ transform: `translate3d(calc(${mousePosition.x}px - 50%), calc(${mousePosition.y}px - 50%), 0)` }}
          />
        </>
      )}

      {/* Fixed header */}
      <header className="fixed top-0 left-0 w-full z-50 flex flex-col">
        {/* Demo sandbox banner */}
        <div className="bg-primary text-white py-2.5 px-4 text-center text-[11px] sm:text-xs font-semibold flex items-center justify-center gap-2.5 shadow-sm relative">
          <span className="bg-secondary text-white text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded animate-pulse">Live Sandbox</span>
          <p className="opacity-95 hidden sm:block">Customize cards live & scan them instantly in the active school dashboard!</p>
          <button
            onClick={() => setIsAdminOpen(true)}
            className="bg-white/10 hover:bg-white/20 text-white font-bold px-2.5 py-1 rounded text-[10px] sm:text-[11px] transition-colors flex items-center gap-1 cursor-pointer"
          >
            <span>Launch Dashboard</span>
            <ArrowRight className="w-3 h-3" />
          </button>
        </div>
        <Navbar onOpenDemo={handleOpenDemoForm} />
      </header>

      <main className="relative">
        <HeroSection onOpenDemo={handleOpenDemoForm} />
        <FeaturesSection />
        <HowItWorksSection />
        <PricingSection onSelectPlan={handleSelectPlan} />
        <FAQSection />
        <DemoSection flash={{ success: success ?? undefined, error: error ?? undefined }} />
      </main>

      <Footer />

      {isAdminOpen && <AdminPanel onClose={() => setIsAdminOpen(false)} />}
      {isLoginOpen && (
        <LoginModal
          onClose={() => setIsLoginOpen(false)}
          onLaunchAdmin={() => { setIsLoginOpen(false); setIsAdminOpen(true); showToast("🔑 Logged into demo. Explore student check-ins."); }}
        />
      )}

      {toastMessage && (
        <div className="fixed bottom-6 right-6 z-50 max-w-sm bg-primary text-white p-4 rounded-xl shadow-[0_12px_30px_rgba(15,23,42,0.25)] border border-slate-800/80 animate-fadeIn flex items-start gap-3">
          <div className="p-1 bg-secondary rounded text-white flex-shrink-0">
            <Command className="w-4 h-4" />
          </div>
          <p className="text-xs font-semibold leading-relaxed flex-1">{toastMessage}</p>
          <button onClick={() => setToastMessage(null)} className="text-slate-400 hover:text-white font-bold text-xs flex-shrink-0">×</button>
        </div>
      )}
    </div>
  );
}
