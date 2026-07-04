import { useState } from 'react';
import { CreditCard, ScanLine, Package, LayoutDashboard, Camera, BarChart3, LucideIcon } from 'lucide-react';
import { motion } from 'framer-motion';

interface FeatureItem {
  id: string;
  icon: LucideIcon;
  title: string;
  desc: string;
  details: string[];
}

interface Props { cardAnnualPrice?: number; }

export default function FeaturesSection({ cardAnnualPrice = 50 }: Props) {
  const [activeFeature, setActiveFeature] = useState<string | null>(null);
  const cardPrice = Math.round(cardAnnualPrice).toLocaleString();

  const features: FeatureItem[] = [
    {
      id: 'pvc-card',
      icon: CreditCard,
      title: 'Professional PVC ID Cards',
      desc: 'Durable CR80 PVC cards printed by Arkeon and shipped to your school within 3 business days.',
      details: [
        'CR80 PVC standard — same size as a credit card, made to last years',
        'Full-color print: school logo, accent color, student photo, name & Amharic name',
        'Each card carries a unique QR code and the student UID (e.g. AIA-2026-00142)',
        'Schools submit a card order; Arkeon prints and delivers — no in-house printer needed',
        'Annual renewal per card at a fixed rate; replacement orders available anytime',
      ],
    },
    {
      id: 'qr-attendance',
      icon: ScanLine,
      title: 'QR Attendance via Teacher App',
      desc: 'Teachers scan cards with the EduID Flutter app. Records sync to the web dashboard in real time.',
      details: [
        'Teacher opens the app, selects subject and date, scans student cards one by one',
        'Each QR decodes to a UID + HMAC token — tamper-proof and school-specific',
        'Offline-first: scans are queued locally and synced when connectivity returns',
        'Manual fallback: teacher can mark attendance by name if a card is forgotten',
        'Session is saved and visible to the school admin on the web dashboard immediately',
      ],
    },
    {
      id: 'student-registry',
      icon: LayoutDashboard,
      title: 'Student & Staff Registry',
      desc: 'A clean web portal for managing every student and staff member across your school.',
      details: [
        'Add students with full name, Amharic name, date of birth, blood type, guardian contacts',
        'Assign students to grades and class sections; bulk import via CSV',
        'Each student gets a unique UID auto-assigned on creation',
        'Staff accounts for teachers with role-based access (admin vs. teacher)',
        'All data is scoped to your school — no cross-school data leakage',
      ],
    },
    {
      id: 'photo-session',
      icon: Camera,
      title: 'Photo Session Mode',
      desc: 'Capture student photos in bulk using the web app — no external photography software needed.',
      details: [
        'Open a photo session for a class; the web app uses the device camera',
        'Match photos to students by scanning their temporary QR slip or typing their UID',
        'Photos are saved and used automatically for ID card printing',
        'Retake an individual photo at any time without affecting other students',
        'Replaces the need to collect photos via WhatsApp or USB drives',
      ],
    },
    {
      id: 'card-orders',
      icon: Package,
      title: 'Card Order & Fulfillment',
      desc: 'Place a card order from the dashboard; receive a unique confirmation code; Arkeon handles the rest.',
      details: [
        'Select students to include in an order batch; review before submitting',
        'Each order gets a unique code (e.g. ORD-AIA-2026-0847) for tracking',
        'Order status updates: Pending → In Print → Shipped → Delivered',
        'Arkeon prints on professional PVC printers and ships via courier within 3 days',
        `Card printing is billed separately at ${cardPrice} ETB per card per year — only when you order`,
      ],
    },
    {
      id: 'reports',
      icon: BarChart3,
      title: 'Attendance Reports & Dashboard',
      desc: 'Visual attendance analytics across grades, sections, and date ranges — always up to date.',
      details: [
        'Per-student attendance history with daily and monthly views',
        'Class-level summaries showing present / absent / late counts',
        'Filter by date range, grade, section, or individual teacher',
        'Export attendance data to CSV for integration with your school reports',
        'Admin dashboard shows real-time snapshot: total students, cards ordered, sessions today',
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
            Everything Your School Needs
          </h2>
          <p className="font-sans text-base sm:text-lg text-slate-600 max-w-2xl mx-auto">
            From student registration to printed PVC cards to QR attendance — one platform, built for Ethiopian schools.
          </p>
        </motion.div>

        <motion.div
          className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6"
          variants={{ hidden: {}, visible: { transition: { staggerChildren: 0.08 } } }}
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
                whileHover={{ scale: 1.02, y: -3, boxShadow: '0 20px 25px -5px rgb(0 0 0 / 0.1)' }}
                whileTap={{ scale: 0.98 }}
                onClick={() => setActiveFeature(isSelected ? null : feat.id)}
                className={`group bg-white p-7 rounded-2xl border transition-all duration-300 cursor-pointer text-left flex flex-col ${isSelected ? 'ring-2 ring-secondary border-transparent shadow-xl' : 'border-slate-200/80 shadow-sm'}`}
              >
                <div className="flex-1">
                  <div className="w-11 h-11 rounded-xl bg-secondary/5 flex items-center justify-center mb-5 group-hover:bg-secondary group-hover:text-white text-secondary transition-all duration-300">
                    <IconComponent className="w-5 h-5" />
                  </div>
                  <h3 className="font-display font-extrabold text-base text-primary mb-2">{feat.title}</h3>
                  <p className="font-sans text-xs text-slate-500 leading-relaxed">{feat.desc}</p>
                  <div className={`overflow-hidden transition-all duration-500 ${isSelected ? 'max-h-80 mt-5 opacity-100' : 'max-h-0 opacity-0'}`}>
                    <div className="border-t border-slate-100 pt-4 space-y-2">
                      <p className="text-[10px] font-bold uppercase tracking-wider text-secondary mb-2">How it works:</p>
                      {feat.details.map((detail, i) => (
                        <div key={i} className="flex gap-2 items-start text-[11px] text-slate-600 font-medium">
                          <span className="w-1.5 h-1.5 rounded-full bg-secondary mt-1.5 flex-shrink-0" />
                          <span>{detail}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
                <div className="mt-5 pt-3 border-t border-slate-50 flex items-center justify-between text-[11px] font-semibold text-slate-400 group-hover:text-secondary transition-colors">
                  <span>{isSelected ? 'Collapse' : 'See details'}</span>
                  <span className={`transition-transform duration-300 ${isSelected ? 'rotate-180 text-secondary' : ''}`}>↓</span>
                </div>
              </motion.div>
            );
          })}
        </motion.div>
      </div>
    </section>
  );
}
