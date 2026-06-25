import { useState } from 'react';
import { Landmark, Fingerprint, Smartphone, Sparkles } from 'lucide-react';

export default function HowItWorksSection() {
  const [activeStep, setActiveStep] = useState<number>(1);

  const steps = [
    {
      num: 1,
      title: 'Register School',
      desc: 'Simple onboarding process. Upload your student database and customize your brand colors in minutes.',
      details: 'Supported formats: CSV, Excel, Google Sheets, or direct integrations via common school management system APIs.',
      icon: Landmark,
    },
    {
      num: 2,
      title: 'Issue Smart IDs',
      desc: 'Quick generation of high-resolution digital identities. Print on-demand or use our bulk fulfillment service.',
      details: 'Instant high-fidelity rendering, with integrated print-preview controls. Choose premium durable PVC or secure eco-friendly options.',
      icon: Fingerprint,
    },
    {
      num: 3,
      title: 'Tap & Track',
      desc: 'Daily attendance becomes seamless. Staff scan student IDs via the EduID App for instant logging.',
      details: 'Features offline caching support for remote camps, field trips, or weak signal situations. Real-time parent check-in alerts are fired.',
      icon: Smartphone,
    },
  ];

  return (
    <section id="how-it-works" className="py-20 px-4 sm:px-6 lg:px-8 bg-white scroll-mt-16">
      <div className="max-w-7xl mx-auto">
        <div className="grid lg:grid-cols-12 gap-12 lg:gap-16 items-center">

          <div className="lg:col-span-6 relative order-2 lg:order-1">
            <div className="absolute -inset-4 bg-primary/5 rounded-[32px] -skew-y-3 transform pointer-events-none" />
            <div className="relative bg-white p-2.5 rounded-2xl shadow-xl border border-slate-200 overflow-hidden group">
              <img
                className="rounded-xl w-full h-auto object-cover max-h-[480px]"
                alt="3D illustration of school administration steps"
                src="https://lh3.googleusercontent.com/aida-public/AB6AXuCH8qUGMmTj9cat5wcoImlJZjUtFiI9gy4mYEw0K3KRBwMPVIhWrZElg34zl-R2lktC9FJwFPaFACHAZO3YV6Hhex6D4oQJz79E7wuD9By9w0Bze_uuORXYagPPdtNYKeE1S60NyU_oo1FeUulFSK8DIgSoWE80AUkLeJZpIdhyGuLH_FiIIG_1on1JyZGHUeBGwRYP-EsVAD648Vs9_6yu2n7_TY7fUbLsLBfx1Ap4h0qUsEVS2YRhdxOwoZRFIzXyYxLDmJgu_OVq"
                referrerPolicy="no-referrer"
              />
              <div className="absolute bottom-6 right-6 bg-primary/95 text-white p-3 rounded-xl shadow-lg flex items-center gap-2 max-w-xs border border-white/10 backdrop-blur-sm">
                <div className="w-2.5 h-2.5 bg-secondary rounded-full animate-ping flex-shrink-0" />
                <span className="font-mono text-[10px] tracking-wider uppercase font-bold text-slate-300">Active State Logging Verified</span>
              </div>
            </div>
          </div>

          <div className="lg:col-span-6 order-1 lg:order-2">
            <h2 className="font-display text-3xl sm:text-4xl font-extrabold text-primary mb-12 tracking-tight">
              Modernizing your school in 3 steps
            </h2>
            <div className="space-y-6">
              {steps.map(step => {
                const StepIcon = step.icon;
                const isActive = activeStep === step.num;
                return (
                  <div
                    key={step.num}
                    onClick={() => setActiveStep(step.num)}
                    className={`p-6 rounded-xl border transition-all duration-300 cursor-pointer flex gap-5 items-start ${isActive ? 'bg-slate-50 border-slate-300 shadow-sm' : 'border-transparent hover:bg-slate-50/50'}`}
                  >
                    <div className={`flex-shrink-0 w-11 h-11 rounded-full flex items-center justify-center font-display font-extrabold text-sm transition-all duration-300 ${isActive ? 'bg-primary text-white shadow-md' : 'bg-slate-100 text-slate-600'}`}>
                      {step.num}
                    </div>
                    <div className="flex-1">
                      <div className="flex items-center gap-2.5">
                        <h3 className="font-display font-extrabold text-base sm:text-lg text-primary">{step.title}</h3>
                        {isActive && (
                          <span className="inline-flex items-center gap-1 text-[10px] font-bold text-secondary uppercase bg-secondary/10 px-2 py-0.5 rounded">
                            <Sparkles className="w-3 h-3" /> Live Demo Mode
                          </span>
                        )}
                      </div>
                      <p className="font-sans text-xs sm:text-sm text-slate-500 mt-2 leading-relaxed">{step.desc}</p>
                      <div className={`overflow-hidden transition-all duration-500 ${isActive ? 'max-h-40 mt-4 opacity-100' : 'max-h-0 opacity-0'}`}>
                        <div className="p-3.5 bg-white border border-slate-200/80 rounded-lg text-xs text-slate-600 font-medium space-y-1.5 shadow-inner">
                          <p className="text-primary font-bold">Details & Onboarding Support:</p>
                          <p className="leading-relaxed">{step.details}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
