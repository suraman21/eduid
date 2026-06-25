import { useState } from 'react';
import { CheckCircle, XCircle, Sparkles } from 'lucide-react';
import { motion } from 'framer-motion';

interface Props {
  onSelectPlan: (planName: string) => void;
}

export default function PricingSection({ onSelectPlan }: Props) {
  const [billingCycle, setBillingCycle] = useState<'monthly' | 'annual'>('monthly');

  const plans = [
    {
      name: 'Starter',
      price: billingCycle === 'monthly' ? 29 : 23,
      features: [
        { name: 'Up to 100 students', included: true },
        { name: 'Digital QR IDs', included: true },
        { name: 'Basic attendance logs', included: true },
        { name: 'SMS Notifications', included: false },
      ],
      cta: 'Choose Starter',
      isPopular: false,
    },
    {
      name: 'Standard',
      price: billingCycle === 'monthly' ? 79 : 63,
      features: [
        { name: 'Up to 500 students', included: true },
        { name: 'Smart PVC ID Design', included: true },
        { name: 'SMS Alerts for Parents', included: true },
        { name: 'Admin & Staff Dashboards', included: true },
      ],
      cta: 'Get Standard',
      isPopular: true,
    },
    {
      name: 'Premium',
      price: billingCycle === 'monthly' ? 149 : 119,
      features: [
        { name: 'Unlimited students', included: true },
        { name: 'Biometric Integration', included: true },
        { name: 'API Access & Webhooks', included: true },
        { name: 'Priority 24/7 Support', included: true },
      ],
      cta: 'Choose Premium',
      isPopular: false,
    },
  ];

  return (
    <section id="pricing" className="bg-[#F2F4F6] py-20 px-4 sm:px-6 lg:px-8 scroll-mt-16 overflow-hidden">
      <div className="max-w-7xl mx-auto text-center">

        <motion.div
          className="mb-10"
          initial={{ opacity: 0, y: -20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.6 }}
        >
          <h2 className="font-display text-3xl sm:text-4xl md:text-5xl font-extrabold text-primary mb-4 tracking-tight">
            Scalable Plans for Every Institution
          </h2>
          <p className="font-sans text-base sm:text-lg text-slate-600 max-w-2xl mx-auto">
            From single academies to statewide school districts, choose the plan that fits your scale.
          </p>
        </motion.div>

        <motion.div
          className="inline-flex items-center gap-3 bg-white border border-slate-200/80 p-1.5 rounded-xl mb-16 shadow-sm"
          initial={{ opacity: 0, scale: 0.95 }}
          whileInView={{ opacity: 1, scale: 1 }}
          viewport={{ once: true }}
          transition={{ duration: 0.5, type: 'spring' }}
        >
          <button
            onClick={() => setBillingCycle('monthly')}
            className={`px-4 py-2.5 rounded-lg text-xs font-bold transition-all ${billingCycle === 'monthly' ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:text-primary'}`}
          >
            Monthly Billing
          </button>
          <button
            onClick={() => setBillingCycle('annual')}
            className={`px-4 py-2.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 ${billingCycle === 'annual' ? 'bg-primary text-white shadow-sm' : 'text-slate-500 hover:text-primary'}`}
          >
            <span>Annual Saving</span>
            <span className="bg-secondary/20 text-secondary text-[9px] font-black uppercase px-1.5 py-0.5 rounded">-20%</span>
          </button>
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
              whileHover={{ scale: plan.isPopular ? 1.07 : 1.03, y: -4 }}
              className={`flex flex-col justify-between rounded-2xl border p-8 transition-all duration-300 relative ${plan.isPopular ? 'bg-primary text-white border-secondary border-2 md:scale-105 shadow-2xl z-10 ring-4 ring-secondary/20' : 'bg-white border-slate-200/80 shadow-sm'}`}
            >
              {plan.isPopular && (
                <div className="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-secondary text-white px-4 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest shadow-md flex items-center gap-1">
                  <Sparkles className="w-3 h-3 animate-spin" /> Recommended
                </div>
              )}

              <div>
                <h3 className={`font-display font-extrabold text-xl mb-2 ${plan.isPopular ? 'text-emerald-300' : 'text-primary'}`}>{plan.name}</h3>
                <div className="flex justify-center items-baseline gap-1 my-6">
                  <span className={`text-5xl font-black font-display tracking-tight ${plan.isPopular ? 'text-white' : 'text-primary'}`}>${plan.price}</span>
                  <span className={`text-sm font-medium ${plan.isPopular ? 'text-emerald-200/80' : 'text-slate-500'}`}>/mo</span>
                </div>
                <ul className={`space-y-4 mb-8 text-left border-t pt-6 ${plan.isPopular ? 'border-white/15' : 'border-slate-100'}`}>
                  {plan.features.map((feat, i) => (
                    <li key={i} className="flex items-center gap-3 text-xs sm:text-sm font-medium">
                      {feat.included
                        ? <CheckCircle className={`w-4 h-4 flex-shrink-0 ${plan.isPopular ? 'text-emerald-400' : 'text-secondary'}`} />
                        : <XCircle className={`w-4 h-4 flex-shrink-0 ${plan.isPopular ? 'text-white/20' : 'text-slate-300'}`} />}
                      <span className={`${feat.included ? (plan.isPopular ? 'text-white' : 'text-slate-700') : (plan.isPopular ? 'text-white/40 line-through' : 'text-slate-400 line-through')}`}>{feat.name}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <button
                onClick={() => onSelectPlan(plan.name)}
                className={`w-full py-3.5 px-4 rounded-xl font-display font-bold text-sm transition-all shadow-sm active:scale-95 duration-150 ${plan.isPopular ? 'bg-secondary text-white hover:brightness-110 shadow-lg shadow-secondary/20' : 'border border-primary bg-white text-primary hover:bg-primary hover:text-white'}`}
              >
                {plan.cta}
              </button>
            </motion.div>
          ))}
        </motion.div>

      </div>
    </section>
  );
}
