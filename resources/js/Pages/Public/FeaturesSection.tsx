import { useState } from 'react';
import { CreditCard, ScanLine, RefreshCw, LayoutDashboard, LucideIcon } from 'lucide-react';
import { motion } from 'framer-motion';

interface FeatureItem {
  id: string;
  icon: LucideIcon;
  title: string;
  desc: string;
  details: string[];
}

export default function FeaturesSection() {
  const [activeFeature, setActiveFeature] = useState<string | null>(null);

  const features: FeatureItem[] = [
    {
      id: 'pvc-card',
      icon: CreditCard,
      title: 'Smart PVC ID Cards',
      desc: 'Durable, high-quality digital prints with embedded security features for every student.',
      details: [
        'Edge-to-edge premium printing on durable eco-friendly PVC material',
        'Secure embedded holograms & micro-text layers to block duplication',
        'Integrated ultra-frequency magnetic and standard contact options',
        'Option to print custom school color schemes & signature marks',
      ],
    },
    {
      id: 'qr-scan',
      icon: ScanLine,
      title: 'Instant QR Attendance',
      desc: 'Contactless check-ins via mobile scanners. Zero hardware costs for classrooms.',
      details: [
        'Unique dynamic QR codes tied securely to student cloud profiles',
        'No specialized scanner required; scans via any standard smartphone or tablet',
        'Built-in sub-second latency handling even under poor network coverage',
        'Supports self-checkout kiosk or teacher-guided scan processes',
      ],
    },
    {
      id: 'realtime-sync',
      icon: RefreshCw,
      title: 'Real-time Sync',
      desc: 'Data instantly available to parents via SMS/Push notifications and cloud dashboards.',
      details: [
        'Sub-second sync with administrative databases upon card tap/scan',
        'Instant trigger for SMS and Push notifications to registered guardians',
        'Auto-alerts for unexpected absences or unverified departure gates',
        'Secure socket connections keeping web dashboard logs up-to-the-minute',
      ],
    },
    {
      id: 'dashboards',
      icon: LayoutDashboard,
      title: 'Multi-role Dashboards',
      desc: 'Tailored views for teachers, admins, and students to track progress and security.',
      details: [
        'Super-Admin Portal: Complete control over user lists, permissions, and settings',
        'Teacher Panel: High-speed roll call, customized student logs, and note attachments',
        'Parent View: Transparent check-in logs, historical calendar sheets, and direct helpdesk links',
        'Real-time automated reports: Export directly to CSV, Excel, or PDF layouts',
      ],
    },
  ];

  return (
    <section id="features" className="bg-[#F2F4F6] py-20 px-4 sm:px-6 lg:px-8 scroll-mt-16 overflow-hidden">
      <div className="max-w-7xl mx-auto">
        <motion.div
          className="text-center mb-16"
          initial={{ opacity: 0, y: -20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true, margin: '-100px' }}
          transition={{ duration: 0.6 }}
        >
          <h2 className="font-display text-3xl sm:text-4xl md:text-5xl font-extrabold text-primary mb-4 tracking-tight">
            Precision Engineering for Schools
          </h2>
          <p className="font-sans text-base sm:text-lg text-slate-600 max-w-2xl mx-auto">
            Everything you need to modernize your institution's identity management in one unified platform.
          </p>
        </motion.div>

        <motion.div
          className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6"
          variants={{ hidden: {}, visible: { transition: { staggerChildren: 0.1 } } }}
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: '-100px' }}
        >
          {features.map(feat => {
            const IconComponent = feat.icon;
            const isSelected = activeFeature === feat.id;
            return (
              <motion.div
                key={feat.id}
                variants={{ hidden: { opacity: 0, y: 30 }, visible: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 100, damping: 15 } } }}
                whileHover={{ scale: 1.03, y: -4, boxShadow: '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)' }}
                whileTap={{ scale: 0.98 }}
                onClick={() => setActiveFeature(isSelected ? null : feat.id)}
                className={`group bg-white p-8 rounded-2xl border transition-all duration-300 cursor-pointer text-left flex flex-col justify-between ${isSelected ? 'ring-2 ring-secondary border-transparent shadow-xl' : 'border-slate-200/80 shadow-sm'}`}
              >
                <div>
                  <div className="w-12 h-12 rounded-xl bg-secondary/5 flex items-center justify-center mb-6 group-hover:bg-secondary group-hover:text-white text-secondary transition-all duration-300">
                    <IconComponent className="w-6 h-6" />
                  </div>
                  <h3 className="font-display font-extrabold text-lg text-primary mb-3">{feat.title}</h3>
                  <p className="font-sans text-xs text-slate-500 leading-relaxed">{feat.desc}</p>
                  <div className={`overflow-hidden transition-all duration-500 ${isSelected ? 'max-h-72 mt-5 opacity-100' : 'max-h-0 opacity-0'}`}>
                    <div className="border-t border-slate-100 pt-4 space-y-2">
                      <p className="text-[10px] font-bold uppercase tracking-wider text-secondary">Platform Capabilities:</p>
                      {feat.details.map((detail, i) => (
                        <div key={i} className="flex gap-2 items-start text-[11px] text-slate-600 font-medium">
                          <span className="w-1.5 h-1.5 rounded-full bg-secondary mt-1 flex-shrink-0" />
                          <span>{detail}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
                <div className="mt-6 pt-3 border-t border-slate-50 flex items-center justify-between text-[11px] font-semibold text-slate-400 group-hover:text-secondary transition-colors">
                  <span>{isSelected ? 'Click to collapse' : 'Click to see details'}</span>
                  <span className={`transition-transform duration-300 ${isSelected ? 'rotate-180 text-secondary' : 'group-hover:translate-x-1'}`}>↓</span>
                </div>
              </motion.div>
            );
          })}
        </motion.div>
      </div>
    </section>
  );
}
