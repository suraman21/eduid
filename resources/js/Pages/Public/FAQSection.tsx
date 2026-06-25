import { useState } from 'react';
import { ChevronDown } from 'lucide-react';

interface FAQItem { q: string; a: string; }

export default function FAQSection() {
  const [openIndex, setOpenIndex] = useState<number | null>(0);

  const faqs: FAQItem[] = [
    { q: 'How secure is the student data?', a: 'We use industry-standard AES-256 encryption for all data at rest and TLS 1.3 for data in transit. Our systems are GDPR and FERPA compliant, ensuring student privacy is always the top priority.' },
    { q: 'Do we need to purchase special hardware?', a: 'No. Our system is designed to work with standard smartphones and tablets. Staff can use the EduID App on their existing devices to scan QR codes, eliminating the need for expensive proprietary hardware.' },
    { q: 'How long does the onboarding process take?', a: 'Most schools are up and running within 48 hours. Once you upload your student database, digital IDs are generated instantly, and you can begin tracking attendance immediately.' },
    { q: 'Can we customize the ID card design?', a: 'Absolutely. Our Standard and Premium plans include a custom design tool where you can add your school logo, brand colors, and specific data fields to the PVC cards.' },
    { q: 'Does it work offline?', a: 'Yes. The EduID App can cache attendance data locally if the internet connection is lost and will automatically sync with the cloud once connectivity is restored.' },
  ];

  return (
    <section id="faq" className="py-20 px-4 sm:px-6 lg:px-8 bg-[#F2F4F6] scroll-mt-16">
      <div className="max-w-3xl mx-auto">
        <div className="text-center mb-16">
          <h2 className="font-display text-3xl sm:text-4xl font-extrabold text-primary mb-4 tracking-tight">Frequently Asked Questions</h2>
          <p className="font-sans text-sm sm:text-base text-slate-600">Everything you need to know about implementing EduID at your institution.</p>
        </div>
        <div className="space-y-4">
          {faqs.map((faq, index) => {
            const isOpen = openIndex === index;
            return (
              <div key={index} className="bg-white rounded-xl border border-slate-200 overflow-hidden transition-all duration-200 shadow-sm hover:border-slate-300">
                <button
                  onClick={() => setOpenIndex(isOpen ? null : index)}
                  className="w-full px-6 py-5 text-left flex justify-between items-center hover:bg-slate-50/50 transition-colors"
                >
                  <span className="font-display font-extrabold text-sm sm:text-base text-primary">{faq.q}</span>
                  <ChevronDown className={`w-5 h-5 text-slate-400 transition-transform duration-300 ${isOpen ? 'rotate-180 text-secondary' : ''}`} />
                </button>
                <div className={`transition-all duration-300 ${isOpen ? 'max-h-40 border-t border-slate-100 opacity-100' : 'max-h-0 opacity-0'} overflow-hidden`}>
                  <div className="p-6 text-xs sm:text-sm text-slate-500 font-medium leading-relaxed bg-slate-50/30">{faq.a}</div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
