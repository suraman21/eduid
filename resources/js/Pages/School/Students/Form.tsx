import React, { useState, useRef } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Upload, User } from 'lucide-react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';

/* ── colour helpers ──────────────────────────────────────────── */
function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

/* ── Card primitive ─────────────────────────────────────────── */
function Card({ children, className = '', style }: { children: React.ReactNode; className?: string; style?: React.CSSProperties }) {
    return (
        <div
            className={`bg-white rounded-3xl border border-slate-200/60 ${className}`}
            style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)', ...style }}
        >
            {children}
        </div>
    );
}

/* ── Types ───────────────────────────────────────────────────── */
interface StudentEdit {
    id: number;
    first_name: string;
    last_name: string;
    first_name_am: string | null;
    last_name_am: string | null;
    gender: string;
    date_of_birth: string | null;
    blood_type: string | null;
    status: string;
    class_id: number | null;
    medical_notes: string | null;
    enrollment_date: string | null;
    join_date: string | null;
    end_date: string | null;
    photo_url: string | null;
}

interface ClassOption {
    id: number;
    name: string;
}

interface Props {
    student: StudentEdit | null;
    classes: ClassOption[];
}

const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const STATUSES = ['active', 'promoted', 'repeating', 'graduated', 'transferred', 'withdrawn'];

export default function Form({ student, classes }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const isEdit = student !== null;

    const [photoPreview, setPhotoPreview] = useState<string | null>(student?.photo_url ?? null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { data, setData, post, transform, processing, errors } = useForm({
        first_name:      student?.first_name ?? '',
        last_name:       student?.last_name ?? '',
        first_name_am:   student?.first_name_am ?? '',
        last_name_am:    student?.last_name_am ?? '',
        gender:          student?.gender ?? '',
        date_of_birth:   student?.date_of_birth ?? '',
        blood_type:      student?.blood_type ?? '',
        status:          student?.status ?? 'active',
        class_id:        String(student?.class_id ?? ''),
        medical_notes:   student?.medical_notes ?? '',
        enrollment_date: student?.enrollment_date ?? '',
        join_date:       student?.join_date ?? '',
        end_date:        student?.end_date ?? '',
        photo:           null as File | null,
    });

    function handlePhotoChange(e: React.ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setData('photo', file);
        setPhotoPreview(URL.createObjectURL(file));
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        if (isEdit) {
            // Multipart PUT isn't supported by browsers; Inertia/Laravel use
            // POST + `_method: PUT` spoofing so the file upload still goes through.
            transform(d => ({ ...d, _method: 'PUT' }));
            post(`/school/students/${student!.id}`, { forceFormData: true });
        } else {
            transform(d => d);
            post('/school/students', { forceFormData: true });
        }
    }

    const inputCls = 'w-full px-3.5 py-2.5 rounded-2xl border border-slate-200 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 transition bg-white';
    const inputStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };
    const labelCls = 'block text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5';

    function SectionTitle({ children }: { children: React.ReactNode }) {
        return (
            <p className="text-[10px] font-bold uppercase tracking-widest mb-4" style={{ color: accent }}>
                {children}
            </p>
        );
    }

    function FieldError({ name }: { name: keyof typeof errors }) {
        return errors[name] ? <p className="text-red-500 text-xs mt-1">{errors[name]}</p> : null;
    }

    return (
        <SchoolLayout>
            <Head title={isEdit ? `Edit ${student.first_name}` : 'New Student'} />

            {/* Top bar */}
            <div className="flex items-center justify-between mb-6">
                <Link
                    href={isEdit ? `/school/students/${student.id}` : '/school/students'}
                    className="flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-slate-800 transition-colors"
                >
                    <ArrowLeft className="w-4 h-4" /> {isEdit ? 'Back to Profile' : 'Back to Students'}
                </Link>
                <h1 className="text-lg font-extrabold text-slate-900">
                    {isEdit ? 'Edit Student' : 'New Student'}
                </h1>
            </div>

            <form onSubmit={submit}>
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">

                    {/* ── Left col: Photo ── */}
                    <div className="space-y-5">
                        <Card className="p-6">
                            <SectionTitle>Photo</SectionTitle>
                            <div className="flex flex-col items-center gap-4">
                                {/* Preview */}
                                <div
                                    className="w-32 h-32 rounded-2xl overflow-hidden flex items-center justify-center cursor-pointer transition-all"
                                    style={{ background: alpha(accent, 0.08), border: `2px dashed ${alpha(accent, 0.3)}` }}
                                    onClick={() => fileInputRef.current?.click()}
                                >
                                    {photoPreview ? (
                                        <img src={photoPreview} alt="Preview" className="w-full h-full object-cover" />
                                    ) : (
                                        <div className="flex flex-col items-center gap-2 text-center px-2">
                                            <User className="w-10 h-10" style={{ color: alpha(accent, 0.5) }} />
                                            <span className="text-xs font-medium text-slate-400">Click to upload</span>
                                        </div>
                                    )}
                                </div>
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept="image/*"
                                    className="hidden"
                                    onChange={handlePhotoChange}
                                />
                                <button
                                    type="button"
                                    onClick={() => fileInputRef.current?.click()}
                                    className="flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-2xl transition-all"
                                    style={{ color: accent, background: alpha(accent, 0.08) }}
                                >
                                    <Upload className="w-4 h-4" />
                                    {photoPreview ? 'Change Photo' : 'Upload Photo'}
                                </button>
                                <p className="text-[11px] text-slate-400 text-center">JPEG or PNG, max 2 MB</p>
                            </div>
                            <FieldError name="photo" />
                        </Card>
                    </div>

                    {/* ── Right 2 cols ── */}
                    <div className="lg:col-span-2 space-y-5">

                        {/* Name (English) */}
                        <Card className="p-6">
                            <SectionTitle>Name (English)</SectionTitle>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className={labelCls}>First Name *</label>
                                    <input
                                        value={data.first_name}
                                        onChange={e => setData('first_name', e.target.value)}
                                        placeholder="First name"
                                        className={inputCls}
                                        style={inputStyle}
                                        required
                                    />
                                    <FieldError name="first_name" />
                                </div>
                                <div>
                                    <label className={labelCls}>Last Name *</label>
                                    <input
                                        value={data.last_name}
                                        onChange={e => setData('last_name', e.target.value)}
                                        placeholder="Last name"
                                        className={inputCls}
                                        style={inputStyle}
                                        required
                                    />
                                    <FieldError name="last_name" />
                                </div>
                            </div>
                        </Card>

                        {/* Name (Amharic) */}
                        <Card className="p-6">
                            <SectionTitle>Name (አማርኛ)</SectionTitle>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className={labelCls}>First Name (Am)</label>
                                    <input
                                        value={data.first_name_am}
                                        onChange={e => setData('first_name_am', e.target.value)}
                                        placeholder="የመጀመሪያ ስም"
                                        className={inputCls}
                                        style={inputStyle}
                                        dir="auto"
                                    />
                                    <FieldError name="first_name_am" />
                                </div>
                                <div>
                                    <label className={labelCls}>Last Name (Am)</label>
                                    <input
                                        value={data.last_name_am}
                                        onChange={e => setData('last_name_am', e.target.value)}
                                        placeholder="የአባት ስም"
                                        className={inputCls}
                                        style={inputStyle}
                                        dir="auto"
                                    />
                                    <FieldError name="last_name_am" />
                                </div>
                            </div>
                        </Card>

                        {/* Personal */}
                        <Card className="p-6">
                            <SectionTitle>Personal Details</SectionTitle>
                            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div>
                                    <label className={labelCls}>Gender *</label>
                                    <select
                                        value={data.gender}
                                        onChange={e => setData('gender', e.target.value)}
                                        className={inputCls}
                                        style={inputStyle}
                                        required
                                    >
                                        <option value="">Select…</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                    <FieldError name="gender" />
                                </div>
                                <div>
                                    <label className={labelCls}>Date of Birth</label>
                                    <input
                                        type="date"
                                        value={data.date_of_birth}
                                        onChange={e => setData('date_of_birth', e.target.value)}
                                        className={inputCls}
                                        style={inputStyle}
                                    />
                                    <FieldError name="date_of_birth" />
                                </div>
                                <div>
                                    <label className={labelCls}>Blood Type</label>
                                    <select
                                        value={data.blood_type}
                                        onChange={e => setData('blood_type', e.target.value)}
                                        className={inputCls}
                                        style={inputStyle}
                                    >
                                        <option value="">Unknown</option>
                                        {BLOOD_TYPES.map(bt => <option key={bt} value={bt}>{bt}</option>)}
                                    </select>
                                    <FieldError name="blood_type" />
                                </div>
                            </div>
                        </Card>

                        {/* Academic */}
                        <Card className="p-6">
                            <SectionTitle>Academic</SectionTitle>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className={labelCls}>Class</label>
                                    <select
                                        value={data.class_id}
                                        onChange={e => setData('class_id', e.target.value)}
                                        className={inputCls}
                                        style={inputStyle}
                                    >
                                        <option value="">Unassigned</option>
                                        {classes.map(c => <option key={c.id} value={String(c.id)}>{c.name}</option>)}
                                    </select>
                                    <FieldError name="class_id" />
                                </div>
                                <div>
                                    <label className={labelCls}>Status</label>
                                    <select
                                        value={data.status}
                                        onChange={e => setData('status', e.target.value)}
                                        className={inputCls}
                                        style={inputStyle}
                                    >
                                        {STATUSES.map(s => (
                                            <option key={s} value={s}>{s.charAt(0).toUpperCase() + s.slice(1)}</option>
                                        ))}
                                    </select>
                                    <FieldError name="status" />
                                </div>
                                <div>
                                    <label className={labelCls}>Enrollment Date</label>
                                    <input
                                        type="date"
                                        value={data.enrollment_date}
                                        onChange={e => setData('enrollment_date', e.target.value)}
                                        className={inputCls}
                                        style={inputStyle}
                                    />
                                    <FieldError name="enrollment_date" />
                                </div>
                                <div>
                                    <label className={labelCls}>Join Date</label>
                                    <input
                                        type="date"
                                        value={data.join_date}
                                        onChange={e => setData('join_date', e.target.value)}
                                        className={inputCls}
                                        style={inputStyle}
                                    />
                                    <FieldError name="join_date" />
                                </div>
                                <div>
                                    <label className={labelCls}>End Date</label>
                                    <input
                                        type="date"
                                        value={data.end_date}
                                        onChange={e => setData('end_date', e.target.value)}
                                        className={inputCls}
                                        style={inputStyle}
                                    />
                                    <FieldError name="end_date" />
                                </div>
                            </div>
                        </Card>

                        {/* Medical Notes */}
                        <Card className="p-6">
                            <SectionTitle>Medical Notes</SectionTitle>
                            <textarea
                                value={data.medical_notes}
                                onChange={e => setData('medical_notes', e.target.value)}
                                placeholder="Allergies, conditions, or special requirements…"
                                rows={4}
                                className={`${inputCls} resize-none`}
                                style={inputStyle}
                            />
                            <FieldError name="medical_notes" />
                        </Card>

                        {/* Submit */}
                        <div className="flex items-center justify-end gap-3 pb-4">
                            <Link
                                href={isEdit ? `/school/students/${student.id}` : '/school/students'}
                                className="px-5 py-2.5 rounded-2xl text-sm font-semibold text-slate-600 hover:bg-slate-100 transition-all"
                            >
                                Cancel
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="px-6 py-2.5 rounded-2xl text-sm font-bold text-white transition-all disabled:opacity-50"
                                style={{
                                    background: accent,
                                    boxShadow: `0 4px 14px ${alpha(accent, 0.35)}`,
                                }}
                            >
                                {processing ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Student'}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </SchoolLayout>
    );
}
