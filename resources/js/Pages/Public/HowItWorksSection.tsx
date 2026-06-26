import { useState } from 'react';
import { Landmark, Users, Camera, Package, ScanLine, Sparkles } from 'lucide-react';

export default function HowItWorksSection() {
  const [activeStep, setActiveStep] = useState<number>(1);

  const steps = [
    {
      num: 1,
      icon: Landmark,
      title: 'School Onboarding',
      desc: 'Arkeon sets up your school account. You receive login credentials for the web dashboard.',
      details: 'We create your school profile with your name, logo, accent color, and school code. Onboarding takes less than a day. No technical setup required on your side.',
    },
    {
      num: 2,
      icon: Users,
      title: 'Register Students & Staff',
      desc: 'Add students via the web portal: name, Amharic name, date of birth, blood type, guardian info, grade, and section.',
      details: 'You can add students one by one or import them in bulk via CSV. Each student is auto-assigned a unique UID (e.g. AIA-2026-00142). Staff/teacher accounts are created separately.',
    },
    {
      num: 3,
      icon: Camera,
      title: 'Run a Photo Session',
      desc: "Use the web app's built-in camera to photograph students in their classes — no external software needed.",
      details: "Open a photo session for a class section, scan each student's temporary QR slip (or type their UID), and capture their photo. Photos are saved immediately and linked to their profile.",
    },
    {
      num: 4,
      icon: Package,
      title: 'Order Cards — Arkeon Prints & Ships',
      desc: 'Submit a card order from the dashboard. Arkeon prints professional PVC cards and delivers them to your school within 3 business days.',
      details: 'Select the students to include, review the order, and confirm. You get a unique order code (e.g. ORD-AIA-2026-0847) to track status. Schools never print cards themselves — Arkeon handles all printing on commercial PVC printers.',
    },
    {
      num: 5,
      icon: ScanLine,
      title: 'Take QR Attendance Daily',
      desc: 'Teachers use the EduID mobile app to scan student cards at the start of each class session.',
      details: 'The app works offline — scans are queued and synced when the phone reconnects. Attendance logs appear on the web dashboard in real time. Teachers can also mark attendance manually if a student forgot their card.',
    },
  ];

  return (
    <section id="how-it-works" className="py-20 px-4 sm:px-6 lg:px-8 bg-white scroll-mt-16">
      <div className="max-w-5xl mx-auto">

        <div className="text-center mb-14">
          <h2 className="font-display text-3xl sm:text-4xl font-extrabold text-primary mb-4 tracking-tight">
            From Sign-up to First Scan in 5 Steps
          </h2>
          <p className="font-sans text-base sm:text-lg text-slate-600 max-w-2xl mx-auto">
            No hardware to buy. No printer needed. Arkeon handles card production — your school handles data.
          </p>
        </div>

        {/* Golden Rule callout */}
        <div className="mb-10 p-4 rounded-xl bg-amber-50 border border-amber-200 flex gap-3 items-start max-w-2xl mx-auto">
          <span className="text-amber-500 text-lg mt-0.5">★</span>
          <div>
            <p className="text-sm font-bold text-amber-800">The Golden Rule</p>
            <p className="text-xs text-amber-700 mt-0.5 leading-relaxed">
              Schools <strong>never</strong> print cards. You manage data; Arkeon prints on professional PVC printers and ships to you. This keeps card quality consistent and removes the cost of maintaining printers.
            </p>
          </div>
        </div>

        <div className="space-y-4">
          {steps.map(step => {
            const StepIcon = step.icon;
            const isActive = activeStep === step.num;
            return (
              <div
                key={step.num}
                onClick={() => setActiveStep(step.num)}
                className={`p-5 rounded-xl border transition-all duration-300 cursor-pointer flex gap-5 items-start ${isActive ? 'bg-slate-50 border-slate-300 shadow-sm' : 'border-slate-100 hover:bg-slate-50/50 hover:border-slate-200'}`}
              >
                <div className={`flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center font-display font-extrabold text-sm transition-all duration-300 ${isActive ? 'bg-primary text-white shadow-md' : 'bg-slate-100 text-slate-500'}`}>
                  {step.num}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2.5 flex-wrap">
                    <StepIcon className={`w-4 h-4 flex-shrink-0 transition-colors ${isActive ? 'text-secondary' : 'text-slate-400'}`} />
                    <h3 className="font-display font-extrabold text-sm sm:text-base text-primary">{step.title}</h3>
                    {isActive && (
                      <span className="inline-flex items-center gap-1 text-[10px] font-bold text-secondary uppercase bg-secondary/10 px-2 py-0.5 rounded">
                        <Sparkles className="w-2.5 h-2.5" /> Active
                      </span>
                    )}
                  </div>
                  <p className="font-sans text-xs text-slate-500 mt-1.5 leading-relaxed">{step.desc}</p>
                  <div className={`overflow-hidden transition-all duration-500 ${isActive ? 'max-h-40 mt-3 opacity-100' : 'max-h-0 opacity-0'}`}>
                    <div className="p-3 bg-white border border-slate-200/80 rounded-lg text-xs text-slate-600 leading-relaxed shadow-inner">
                      {step.details}
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
