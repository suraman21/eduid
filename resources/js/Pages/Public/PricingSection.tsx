import { CheckCircle, Sparkles, Package } from 'lucide-react';
import { motion } from 'framer-motion';

interface Props {
  onSelectPlan: (planName: string) => void;
}

const plans = [
  {
    name: 'Starter',
    setupFee: 12000,
    students: 'Up to 300 students',
    cap: 300,
    isPopular: false,
    features: [
      'Student & staff registry',
      'Photo Session Mode',
      'Card order management',
      'QR attendance (teacher app)',
      'Attendance reports & CSV export',
      'Card design preview',
      'Up to 300 students',
      '1 admin account + unlimited teachers',
    ],
    cta: 'Get Started',
  },
  {
    name: 'Standard',
    setupFee: 22000,
    students: 'Up to 800 students',
    cap: 800,
    isPopular: true,
    features: [
      'Everything in Starter',
      'Up to 800 students',
      'Multi-grade & multi-section support',
      'Bulk CSV import for students',
      'Priority order processing',
      '2 admin accounts + unlimited teachers',
      'Dedicated onboarding support',
    ],
    cta: 'Choose Standard',
  },
  {
    name: 'Premium',
    setupFee: 35000,
    students: 'Unlimited students',
    cap: null,
    isPopular: false,
    features: [
      'Everything in Standard',
      'Unlimited students',
      'Unlimited admin accounts',
      'Custom card template design',
      'Custom accent color & logo placement',
      'Priority support & SLA',
      'Early access to new features',
    ],
    cta: 'Choose Premium',
  },
];

export default function PricingSection({ onSelectPlan }: Props) {
  return (
    <section id="pricing" className="bg-[#F2F4F6] py-20 px-4 sm:px-6 lg:px-8 scroll-mt-16 overflow-hidden">
      <div className="max-w-7xl mx-auto text-center">

        <motion.div
          className="mb-4"
          initial={{ opacity: 0, y: -20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
        >
          <h2 className="font-display text-3xl sm:text-4xl md:text-5xl font-extrabold text-primary mb-4 tracking-tight">
            Simple, Transparent Pricing
          </h2>
          <p className="font-sans text-base sm:text-lg text-slate-600 max-w-2xl mx-auto">
            One-time setup fee in Ethiopian Birr. No monthly subscription. Cards are printed at <strong>50 ETB per card per year</strong>.
          </p>
        </motion.div>

        {/* Card printing note */}
        <motion.div
          className="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 px-4 py-2.5 rounded-xl mb-12 text-sm text-amber-800"
          initial={{ opacity: 0 }}
          whileInView={{ opacity: 1 }}
          viewport={{ once: true }}
          transition={{ delay: 0.2 }}
        >
          <Package className="w-4 h-4 text-amber-600 flex-shrink-0" />
          <span><strong>Card printing:</strong> 50 ETB per card per year — billed only when you place a card order. No hidden fees.</span>
        </motion.div>

        <motion.div
          className="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch max-w-5xl mx-auto"
          initial="hidden"
          whileInView="visible"
          viewport={{ once: true, margin: '-100px' }}
          variants={{ hidden: {}, visible: { transition: { staggerChildren: 0.1 } } }}
        >
          {plans.map(plan => (
            <motion.div
              key={plan.name}
              variants={{ hidden: { opacity: 0, y: 30 }, visible: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 100, damping: 15 } } }}
              whileHover={{ scale: plan.isPopular ? 1.05 : 1.02, y: -4 }}
              className={`flex flex-col justify-between rounded-2xl border p-8 transition-all duration-300 relative ${plan.isPopular ? 'bg-primary text-white border-secondary border-2 md:scale-105 shadow-2xl z-10 ring-4 ring-secondary/20' : 'bg-white border-slate-200/80 shadow-sm'}`}
            >
              {plan.isPopular && (
                <div className="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-secondary text-white px-4 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest shadow-md flex items-center gap-1">
                  <Sparkles className="w-3 h-3" /> Most Popular
                </div>
              )}

              <div>
                <h3 className={`font-display font-extrabold text-xl mb-1 ${plan.isPopular ? 'text-emerald-300' : 'text-primary'}`}>{plan.name}</h3>
                <p className={`text-xs mb-5 ${plan.isPopular ? 'text-emerald-200/80' : 'text-slate-500'}`}>{plan.students}</p>

                <div className="flex justify-center items-baseline gap-1 my-5">
                  <span className={`text-4xl font-black font-display tracking-tight ${plan.isPopular ? 'text-white' : 'text-primary'}`}>
                    {plan.setupFee.toLocaleString()}
                  </span>
                  <span className={`text-sm font-medium ml-1 ${plan.isPopular ? 'text-emerald-200/80' : 'text-slate-500'}`}>ETB</span>
                </div>
                <p className={`text-[11px] mb-6 ${plan.isPopular ? 'text-emerald-200/70' : 'text-slate-400'}`}>one-time setup fee</p>

                <ul className={`space-y-3 text-left border-t pt-5 ${plan.isPopular ? 'border-white/15' : 'border-slate-100'}`}>
                  {plan.features.map((feat, i) => (
                    <li key={i} className="flex items-start gap-2.5 text-xs font-medium">
                      <CheckCircle className={`w-4 h-4 flex-shrink-0 mt-0.5 ${plan.isPopular ? 'text-emerald-400' : 'text-secondary'}`} />
                      <span className={plan.isPopular ? 'text-white' : 'text-slate-700'}>{feat}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <button
                onClick={() => onSelectPlan(plan.name)}
                className={`mt-8 w-full py-3.5 px-4 rounded-xl font-display font-bold text-sm transition-all active:scale-95 duration-150 ${plan.isPopular ? 'bg-secondary text-white hover:brightness-110 shadow-lg shadow-secondary/20' : 'border border-primary bg-white text-primary hover:bg-primary hover:text-white'}`}
              >
                {plan.cta}
              </button>
            </motion.div>
          ))}
        </motion.div>

        <p className="mt-10 text-xs text-slate-400 max-w-xl mx-auto">
          All prices are in Ethiopian Birr (ETB) and include VAT. Card printing (50 ETB/card/year) is billed separately per order. Contact us for custom quotes for large school networks.
        </p>
      </div>
    </section>
  );
}
