import React from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, UserPlus } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

interface Option { value: string | number; label: string }

interface Props {
    application: null;
    reference: string;
    options: { programmes: Option[]; classes: Option[]; sources: Option[] };
}

export default function Form({ reference, options }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const schoolYear = (props as unknown as { school?: { academic_year?: string } }).school?.academic_year ?? '';

    const { data, setData, post, processing, errors } = useForm({
        first_name: '', last_name: '', first_name_am: '', last_name_am: '',
        gender: '', date_of_birth: '',
        phone: '', email: '', address_line: '',
        guardian_name: '', guardian_phone: '', guardian_email: '', guardian_relationship: '',
        previous_school: '', previous_grade: '',
        programme_id: '', class_id: '', academic_year: schoolYear, intended_level: '',
        status: 'applied', source: 'walk_in',
        notes: '', follow_up_on: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/school/admissions');
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 transition bg-white';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };
    const labelCls = 'block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-1.5';

    function Err({ name }: { name: keyof typeof errors }) {
        return errors[name] ? <p className="text-red-500 text-xs mt-1">{errors[name]}</p> : null;
    }

    function Section({ title, hint, children }: { title: string; hint?: string; children: React.ReactNode }) {
        return (
            <div>
                <h3 className="text-sm font-bold text-slate-800">{title}</h3>
                {hint && <p className="text-[11px] text-slate-400">{hint}</p>}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-3">{children}</div>
            </div>
        );
    }

    return (
        <SchoolLayout>
            <Head title="New application" />

            <div className="flex items-center justify-between mb-6">
                <Link href="/school/admissions"
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800">
                    <ArrowLeft className="w-4 h-4" /> Back to Admissions
                </Link>
                <div className="text-right">
                    <h1 className="text-lg font-extrabold text-slate-900">New application</h1>
                    <p className="text-[11px] text-slate-400 font-mono">{reference}</p>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div className="bg-white rounded-3xl border border-slate-200 p-6 space-y-7">
                    <Section title="Applicant" hint="The person who wants to join">
                        <div>
                            <label className={labelCls}>First name *</label>
                            <input value={data.first_name} onChange={e => setData('first_name', e.target.value)}
                                className={inputCls} style={inputStyle} required />
                            <Err name="first_name" />
                        </div>
                        <div>
                            <label className={labelCls}>Last name *</label>
                            <input value={data.last_name} onChange={e => setData('last_name', e.target.value)}
                                className={inputCls} style={inputStyle} required />
                            <Err name="last_name" />
                        </div>
                        <div>
                            <label className={labelCls}>Gender</label>
                            <select value={data.gender} onChange={e => setData('gender', e.target.value)}
                                className={inputCls} style={inputStyle}>
                                <option value="">—</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div>
                            <label className={labelCls}>First name (Amharic)</label>
                            <input value={data.first_name_am} onChange={e => setData('first_name_am', e.target.value)}
                                className={inputCls} style={inputStyle} dir="auto" />
                        </div>
                        <div>
                            <label className={labelCls}>Last name (Amharic)</label>
                            <input value={data.last_name_am} onChange={e => setData('last_name_am', e.target.value)}
                                className={inputCls} style={inputStyle} dir="auto" />
                        </div>
                        <div>
                            <label className={labelCls}>Date of birth</label>
                            <input type="date" value={data.date_of_birth}
                                onChange={e => setData('date_of_birth', e.target.value)}
                                className={inputCls} style={inputStyle} />
                            <Err name="date_of_birth" />
                        </div>
                    </Section>

                    <hr className="border-slate-100" />

                    <Section title="Guardian / enquirer" hint="Who to call back">
                        <div>
                            <label className={labelCls}>Name</label>
                            <input value={data.guardian_name} onChange={e => setData('guardian_name', e.target.value)}
                                className={inputCls} style={inputStyle} />
                        </div>
                        <div>
                            <label className={labelCls}>Phone</label>
                            <input value={data.guardian_phone} onChange={e => setData('guardian_phone', e.target.value)}
                                className={inputCls} style={inputStyle} placeholder="+251…" />
                            <Err name="guardian_phone" />
                        </div>
                        <div>
                            <label className={labelCls}>Relationship</label>
                            <input value={data.guardian_relationship}
                                onChange={e => setData('guardian_relationship', e.target.value)}
                                className={inputCls} style={inputStyle} placeholder="Mother, father…" />
                        </div>
                        <div>
                            <label className={labelCls}>Email</label>
                            <input type="email" value={data.guardian_email}
                                onChange={e => setData('guardian_email', e.target.value)}
                                className={inputCls} style={inputStyle} />
                            <Err name="guardian_email" />
                        </div>
                        <div className="sm:col-span-2">
                            <label className={labelCls}>Address</label>
                            <input value={data.address_line} onChange={e => setData('address_line', e.target.value)}
                                className={inputCls} style={inputStyle} placeholder="Sub-city, woreda, house no." />
                        </div>
                    </Section>

                    <hr className="border-slate-100" />

                    <Section title="Applying for" hint="Leave the class blank if it is not decided yet">
                        <div>
                            <label className={labelCls}>Programme</label>
                            <select value={data.programme_id} onChange={e => setData('programme_id', e.target.value)}
                                className={inputCls} style={inputStyle}>
                                <option value="">—</option>
                                {options.programmes.map(p => (
                                    <option key={p.value} value={p.value}>{p.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className={labelCls}>Class</label>
                            <select value={data.class_id} onChange={e => setData('class_id', e.target.value)}
                                className={inputCls} style={inputStyle}>
                                <option value="">—</option>
                                {options.classes.map(c => (
                                    <option key={c.value} value={c.value}>{c.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className={labelCls}>Level requested</label>
                            <input value={data.intended_level} onChange={e => setData('intended_level', e.target.value)}
                                className={inputCls} style={inputStyle} placeholder="Grade 7, Level 2…" />
                        </div>
                        <div>
                            <label className={labelCls}>Academic year</label>
                            <input value={data.academic_year} onChange={e => setData('academic_year', e.target.value)}
                                className={inputCls} style={inputStyle} placeholder="2026/2027" />
                        </div>
                        <div>
                            <label className={labelCls}>Previous school</label>
                            <input value={data.previous_school} onChange={e => setData('previous_school', e.target.value)}
                                className={inputCls} style={inputStyle} />
                        </div>
                        <div>
                            <label className={labelCls}>Previous grade</label>
                            <input value={data.previous_grade} onChange={e => setData('previous_grade', e.target.value)}
                                className={inputCls} style={inputStyle} />
                        </div>
                    </Section>

                    <hr className="border-slate-100" />

                    <Section title="Pipeline" hint="Where this application stands right now">
                        <div>
                            <label className={labelCls}>Status</label>
                            <select value={data.status} onChange={e => setData('status', e.target.value)}
                                className={inputCls} style={inputStyle}>
                                <option value="enquiry">Enquiry</option>
                                <option value="applied">Applied</option>
                                <option value="screening">Screening</option>
                                <option value="offered">Offered</option>
                                <option value="accepted">Accepted</option>
                            </select>
                        </div>
                        <div>
                            <label className={labelCls}>How they found us</label>
                            <select value={data.source} onChange={e => setData('source', e.target.value)}
                                className={inputCls} style={inputStyle}>
                                {options.sources.map(s => (
                                    <option key={s.value} value={s.value}>{s.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className={labelCls}>Follow up on</label>
                            <input type="date" value={data.follow_up_on}
                                onChange={e => setData('follow_up_on', e.target.value)}
                                className={inputCls} style={inputStyle} />
                        </div>
                        <div className="sm:col-span-2 lg:col-span-3">
                            <label className={labelCls}>Notes</label>
                            <textarea value={data.notes} onChange={e => setData('notes', e.target.value)}
                                rows={3} className={inputCls} style={inputStyle}
                                placeholder="Anything worth remembering about this enquiry…" />
                        </div>
                    </Section>
                </div>

                <div className="flex items-center justify-end gap-3">
                    <Link href="/school/admissions"
                        className="px-5 py-2.5 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-slate-100">
                        Cancel
                    </Link>
                    <button type="submit" disabled={processing}
                        className="flex items-center gap-2 px-6 py-2.5 rounded-2xl text-sm font-bold text-white disabled:opacity-50"
                        style={{ background: accent, boxShadow: `0 4px 14px ${alpha(accent, 0.35)}` }}>
                        <UserPlus className="w-4 h-4" />
                        {processing ? 'Saving…' : 'Record application'}
                    </button>
                </div>
            </form>
        </SchoolLayout>
    );
}
