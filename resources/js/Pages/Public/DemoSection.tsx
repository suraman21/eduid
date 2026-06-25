import React, { useState } from 'react';
import { Mail, Phone, Check, Send } from 'lucide-react';
import { router, usePage } from '@inertiajs/react';

interface Props {
  flash?: { success?: string; error?: string };
}

export default function DemoSection({ flash }: Props) {
  const [formData, setFormData] = useState({ fullName: '', schoolName: '', workEmail: '', phoneNumber: '', message: '' });
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitSuccess, setSubmitSuccess] = useState(!!flash?.success);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    router.post('/contact', {
      name: formData.fullName,
      school_name: formData.schoolName,
      email: formData.workEmail,
      phone: formData.phoneNumber,
      message: formData.message,
    }, {
      onSuccess: () => {
        setIsSubmitting(false);
        setSubmitSuccess(true);
        setFormData({ fullName: '', schoolName: '', workEmail: '', phoneNumber: '', message: '' });
        setTimeout(() => setSubmitSuccess(false), 5000);
      },
      onError: () => setIsSubmitting(false),
      preserveScroll: true,
    });
  };

  const inputCls = 'w-full text-xs px-3.5 py-3 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-secondary/20 focus:border-secondary transition-all';
  const labelCls = 'block text-[11px] font-bold text-slate-500 uppercase';

  return (
    <section id="contact" className="py-20 px-4 sm:px-6 lg:px-8 bg-white scroll-mt-16">
      <div className="max-w-4xl mx-auto">
        <div className="bg-white rounded-3xl shadow-xl overflow-hidden grid grid-cols-1 md:grid-cols-5 border border-slate-200">

          <div className="md:col-span-2 bg-primary p-8 sm:p-12 text-white flex flex-col justify-between relative overflow-hidden">
            <div className="absolute top-0 right-0 w-32 h-32 bg-secondary/15 rounded-full blur-2xl translate-x-1/3 -translate-y-1/3" />
            <div className="z-10">
              <h2 className="font-display text-2xl sm:text-3xl font-extrabold mb-4 tracking-tight">Request a Demo</h2>
              <p className="font-sans text-slate-300 text-xs sm:text-sm leading-relaxed">
                See how EduID can transform your school's daily operations. Let us build a free sample card sheet for your brand.
              </p>
            </div>
            <div className="space-y-6 z-10 mt-12 md:mt-0">
              <div className="flex items-center gap-4 hover:translate-x-1 transition-transform duration-200">
                <div className="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center text-emerald-400">
                  <Mail className="w-5 h-5" />
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold text-slate-400">Email Address</p>
                  <p className="text-xs sm:text-sm font-semibold">hello@eduid.ai</p>
                </div>
              </div>
              <div className="flex items-center gap-4 hover:translate-x-1 transition-transform duration-200">
                <div className="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center text-emerald-400">
                  <Phone className="w-5 h-5" />
                </div>
                <div>
                  <p className="text-[10px] uppercase font-bold text-slate-400">Contact Line</p>
                  <p className="text-xs sm:text-sm font-semibold">+251 (911) 000-000</p>
                </div>
              </div>
            </div>
            <p className="text-[9px] text-slate-400 font-mono mt-8 z-10">* GDPR & FERPA compliant data security standards guaranteed.</p>
          </div>

          <div className="md:col-span-3 p-8 sm:p-12 relative">
            {submitSuccess && (
              <div className="absolute inset-0 bg-white/95 backdrop-blur-sm z-20 flex flex-col items-center justify-center p-8 animate-fadeIn text-center">
                <div className="w-16 h-16 rounded-full bg-secondary/10 text-secondary flex items-center justify-center mb-6 animate-bounce">
                  <Check className="w-8 h-8" />
                </div>
                <h3 className="font-display font-extrabold text-xl text-primary mb-2">Demo Request Submitted!</h3>
                <p className="text-sm text-slate-500 max-w-xs leading-relaxed mb-6">
                  Thank you for your interest. One of our consultants will reach out with customized ID templates within 24 hours.
                </p>
                <button onClick={() => setSubmitSuccess(false)} className="px-6 py-2.5 bg-primary hover:bg-slate-800 text-white font-semibold rounded-lg text-xs">
                  Back to Form
                </button>
              </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-5">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className={labelCls}>Full Name</label>
                  <input type="text" required value={formData.fullName} onChange={e => setFormData({ ...formData, fullName: e.target.value })} className={inputCls} placeholder="Sarah Jenkins" />
                </div>
                <div className="space-y-1">
                  <label className={labelCls}>School Name</label>
                  <input type="text" required value={formData.schoolName} onChange={e => setFormData({ ...formData, schoolName: e.target.value })} className={inputCls} placeholder="Oakridge Academy" />
                </div>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-1">
                  <label className={labelCls}>Work Email</label>
                  <input type="email" value={formData.workEmail} onChange={e => setFormData({ ...formData, workEmail: e.target.value })} className={inputCls} placeholder="admin@school.edu.et" />
                </div>
                <div className="space-y-1">
                  <label className={labelCls}>Phone Number</label>
                  <input type="tel" required value={formData.phoneNumber} onChange={e => setFormData({ ...formData, phoneNumber: e.target.value })} className={inputCls} placeholder="+251 911 000 000" />
                </div>
              </div>
              <div className="space-y-1">
                <label className={labelCls}>Message</label>
                <textarea value={formData.message} onChange={e => setFormData({ ...formData, message: e.target.value })} className={inputCls} placeholder="How many students do you have?" rows={4} />
              </div>
              <button
                type="submit"
                disabled={isSubmitting}
                className="w-full bg-secondary hover:brightness-110 text-white py-3.5 rounded-lg font-display font-extrabold text-sm transition-all shadow-md active:scale-[0.99] flex items-center justify-center gap-2"
              >
                {isSubmitting ? (
                  <>
                    <div className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                    <span>Sending Request...</span>
                  </>
                ) : (
                  <>
                    <Send className="w-4 h-4" />
                    <span>Submit Request</span>
                  </>
                )}
              </button>
            </form>
          </div>

        </div>
      </div>
    </section>
  );
}
