import { useState } from 'react';
import { CheckCircle, Sparkles, Package } from 'lucide-react';
import { motion } from 'framer-motion';

/** One plan tier, assembled server-side from the Super Admin's live packages. */
export interface PublicPlan {
  name: string;
  tier: string;
  price_monthly_etb: number;
  price_annual_etb: number | null;
  max_students: number;
  max_teachers: number;
  max_school_admins: number;
  description: string | null;
  is_popular: boolean;
  features: string[];
}

interface Props {
  onSelectPlan: (planName: string) => void;
  plans?: PublicPlan[];
}

const fmt = (n: number) => Math.round(n).toLocaleString();

export default function PricingSection({ onSelectPlan, plans = [] }: Props) {
  const [annual, setAnnual] = useState(false);

  // Whether any plan actually offers an annual price — hides the toggle if not.
  const hasAnnual = plans.some(p => p.price_annual_etb !== null && p.price_annual_etb > 0);

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
            Choose the plan that fits your school. Cards are printed at <strong>50 ETB per card per year</strong>, billed only when you order.
          </p>
        </motion.div>

        {/* Card printing note */}
        <motion.div
          className="inline-flex items-center gap-2 bg-amber-50 border border-amber-200 px-4 py-2.5 rounded-xl mb-8 text-sm text-amber-800"
          initial={{ opacity: 0 }}
          whileInView={{ opacity: 1 }}
          viewport={{ once: true }}
          transition={{ delay: 0.2 }}
        >
          <Package className="w-4 h-4 text-amber-600 flex-shrink-0" />
          <span><strong>Card printing:</strong> 50 ETB per card per year — billed only when you place a card order. No hidden fees.</span>
        </motion.div>

        {/* Monthly / annual toggle (only if annual pricing exists) */}
        {hasAnnual && (
          <div className="flex items-center justify-center gap-3 mb-12">
            <span className={`text-sm font-semibold ${!annual ? 'text-primary' : 'text-slate-400'}`}>Monthly</span>
            <button
              type="button"
              role="switch"
              aria-checked={annual}
              onClick={() => setAnnual(a => !a)}
              className={`relative w-12 h-6 rounded-full transition-colors ${annual ? 'bg-secondary' : 'bg-slate-300'}`}
            >
              <span className={`absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform ${annual ? 'translate-x-6' : ''}`} />
            </button>
            <span className={`text-sm font-semibold ${annual ? 'text-primary' : 'text-slate-400'}`}>
              Annual <span className="text-emerald-600 font-bold">(save more)</span>
            </span>
          </div>
        )}

        {plans.length === 0 ? (
          <p className="text-slate-500 py-10">Pricing is being updated. Please check back shortly or contact us for a quote.</p>
        ) : (
          <motion.div
            className="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch max-w-5xl mx-auto"
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: '-100px' }}
            variants={{ hidden: {}, visible: { transition: { staggerChildren: 0.1 } } }}
          >
            {plans.map(plan => {
              const showAnnual = annual && plan.price_annual_etb !== null && plan.price_annual_etb > 0;
              const price = showAnnual ? (plan.price_annual_etb as number) : plan.price_monthly_etb;
              const period = showAnnual ? '/year' : '/month';
              const seats = plan.max_school_admins > 1
                ? `${plan.max_school_admins} admin accounts + unlimited teachers`
                : '1 admin account + unlimited teachers';

              return (
                <motion.div
                  key={plan.tier}
                  variants={{ hidden: { opacity: 0, y: 30 }, visible: { opacity: 1, y: 0, transition: { type: 'spring', stiffness: 100, damping: 15 } } }}
                  whileHover={{ scale: plan.is_popular ? 1.05 : 1.02, y: -4 }}
                  className={`flex flex-col justify-between rounded-2xl border p-8 transition-all duration-300 relative ${plan.is_popular ? 'bg-primary text-white border-secondary border-2 md:scale-105 shadow-2xl z-10 ring-4 ring-secondary/20' : 'bg-white border-slate-200/80 shadow-sm'}`}
                >
                  {plan.is_popular && (
                    <div className="absolute top-0 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-secondary text-white px-4 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-widest shadow-md flex items-center gap-1">
                      <Sparkles className="w-3 h-3" /> Most Popular
                    </div>
                  )}

                  <div>
                    <h3 className={`font-display font-extrabold text-xl mb-1 ${plan.is_popular ? 'text-emerald-300' : 'text-primary'}`}>{plan.name}</h3>
                    <p className={`text-xs mb-5 ${plan.is_popular ? 'text-emerald-200/80' : 'text-slate-500'}`}>
                      {plan.description || `Up to ${fmt(plan.max_students)} students`}
                    </p>

                    <div className="flex justify-center items-baseline gap-1 my-5">
                      <span className={`text-4xl font-black font-display tracking-tight ${plan.is_popular ? 'text-white' : 'text-primary'}`}>
                        {fmt(price)}
                      </span>
                      <span className={`text-sm font-medium ml-1 ${plan.is_popular ? 'text-emerald-200/80' : 'text-slate-500'}`}>ETB</span>
                    </div>
                    <p className={`text-[11px] mb-6 ${plan.is_popular ? 'text-emerald-200/70' : 'text-slate-400'}`}>{period}</p>

                    <ul className={`space-y-3 text-left border-t pt-5 ${plan.is_popular ? 'border-white/15' : 'border-slate-100'}`}>
                      {/* Seat limits — always shown, straight from the plan record. */}
                      <li className="flex items-start gap-2.5 text-xs font-medium">
                        <CheckCircle className={`w-4 h-4 flex-shrink-0 mt-0.5 ${plan.is_popular ? 'text-emerald-400' : 'text-secondary'}`} />
                        <span className={plan.is_popular ? 'text-white' : 'text-slate-700'}>Up to {fmt(plan.max_students)} students</span>
                      </li>
                      <li className="flex items-start gap-2.5 text-xs font-medium">
                        <CheckCircle className={`w-4 h-4 flex-shrink-0 mt-0.5 ${plan.is_popular ? 'text-emerald-400' : 'text-secondary'}`} />
                        <span className={plan.is_popular ? 'text-white' : 'text-slate-700'}>{seats}</span>
                      </li>
                      {/* Included modules — live from the plan×feature matrix. */}
                      {plan.features.map((feat, i) => (
                        <li key={i} className="flex items-start gap-2.5 text-xs font-medium">
                          <CheckCircle className={`w-4 h-4 flex-shrink-0 mt-0.5 ${plan.is_popular ? 'text-emerald-400' : 'text-secondary'}`} />
                          <span className={plan.is_popular ? 'text-white' : 'text-slate-700'}>{feat}</span>
                        </li>
                      ))}
                    </ul>
                  </div>

                  <button
                    onClick={() => onSelectPlan(plan.name)}
                    className={`mt-8 w-full py-3.5 px-4 rounded-xl font-display font-bold text-sm transition-all active:scale-95 duration-150 ${plan.is_popular ? 'bg-secondary text-white hover:brightness-110 shadow-lg shadow-secondary/20' : 'border border-primary bg-white text-primary hover:bg-primary hover:text-white'}`}
                  >
                    Choose {plan.name}
                  </button>
                </motion.div>
              );
            })}
          </motion.div>
        )}

        <p className="mt-10 text-xs text-slate-400 max-w-xl mx-auto">
          All prices are in Ethiopian Birr (ETB) and include VAT. Card printing (50 ETB/card/year) is billed separately per order. Contact us for custom quotes for large school networks.
        </p>
      </div>
    </section>
  );
}
