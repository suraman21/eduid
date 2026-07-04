import { useState } from 'react';
import { ChevronDown } from 'lucide-react';

interface FAQItem { q: string; a: string; }

interface Props { cardAnnualPrice?: number; }

export default function FAQSection({ cardAnnualPrice = 50 }: Props) {
  const [openIndex, setOpenIndex] = useState<number | null>(0);
  const cardPrice = Math.round(cardAnnualPrice).toLocaleString();

  const faqs: FAQItem[] = [
    {
      q: 'How long does it take to get cards after we place an order?',
      a: 'Arkeon prints and ships cards within 3 business days of receiving a completed order. The order must include all student photos — the photo session must be done before ordering. You can track your order status (Pending → In Print → Shipped → Delivered) from the dashboard using your order code.',
    },
    {
      q: 'Do we need any special hardware or printers?',
      a: 'No. Schools do not print cards. Arkeon handles all printing on professional PVC printers. For attendance, teachers use their own smartphones with the EduID Flutter app (Android). No scanners, terminals, or other hardware purchases are needed.',
    },
    {
      q: 'Can teachers take attendance without internet?',
      a: 'Yes. The EduID teacher app is offline-first. Scans are saved locally on the device and automatically synced to the server when the phone reconnects. This is important for schools in areas with unreliable connectivity.',
    },
    {
      q: 'What happens if a student loses their card?',
      a: 'The school admin can place a replacement card order for that student at any time. A new card is printed with a new QR token; the old QR token is automatically invalidated so the lost card cannot be misused.',
    },
    {
      q: 'Can we customize the ID card design?',
      a: 'Yes — your school name, logo, accent color, and school code are printed on every card. Premium plan schools can also request a custom card template layout. Standard and Starter schools get the default EduID template with their branding applied.',
    },
    {
      q: 'How is student data protected?',
      a: 'Data is stored on our servers with standard web security practices: bcrypt for passwords, HTTPS for all connections, and strict tenant isolation so one school cannot access another school\'s data. Student data is used only for ID card printing and attendance — it is not shared or sold.',
    },
    {
      q: 'How do we pay? Do you accept Telebirr?',
      a: 'Yes, we accept Telebirr and bank transfers for both your plan subscription and card printing orders. Payment details are confirmed during onboarding.',
    },
    {
      q: 'What is the difference between the plan subscription and the card printing fee?',
      a: `Your plan subscription (billed monthly or annually, depending on the tier you choose) keeps your school account active and unlocks the platform features for that tier. The card printing fee (${cardPrice} ETB per card per year) is separate and is paid only when you place a card order. For example, an annual card order for 200 students costs ${(200 * cardAnnualPrice).toLocaleString()} ETB.`,
    },
  ];

  return (
    <section id="faq" className="py-20 px-4 sm:px-6 lg:px-8 bg-[#F2F4F6] scroll-mt-16">
      <div className="max-w-3xl mx-auto">
        <div className="text-center mb-16">
          <h2 className="font-display text-3xl sm:text-4xl font-extrabold text-primary mb-4 tracking-tight">Frequently Asked Questions</h2>
          <p className="font-sans text-sm sm:text-base text-slate-600">Real answers about how EduID works for Ethiopian schools.</p>
        </div>
        <div className="space-y-3">
          {faqs.map((faq, index) => {
            const isOpen = openIndex === index;
            return (
              <div key={index} className="bg-white rounded-xl border border-slate-200 overflow-hidden transition-all duration-200 shadow-sm hover:border-slate-300">
                <button
                  onClick={() => setOpenIndex(isOpen ? null : index)}
                  className="w-full px-6 py-5 text-left flex justify-between items-center hover:bg-slate-50/50 transition-colors"
                >
                  <span className="font-display font-extrabold text-sm sm:text-base text-primary">{faq.q}</span>
                  <ChevronDown className={`w-5 h-5 text-slate-400 flex-shrink-0 ml-3 transition-transform duration-300 ${isOpen ? 'rotate-180 text-secondary' : ''}`} />
                </button>
                <div className={`transition-all duration-300 ${isOpen ? 'max-h-60 border-t border-slate-100 opacity-100' : 'max-h-0 opacity-0'} overflow-hidden`}>
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
