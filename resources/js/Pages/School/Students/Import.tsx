import React, { useRef, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import SchoolLayout from '@/Layouts/SchoolLayout';
import type { PageProps } from '@/types';
import { Upload, Download, CheckCircle, XCircle, AlertTriangle } from 'lucide-react';

function normHex(c: string | null | undefined) {
    const h = (c || '#6366f1').trim();
    return /^#[0-9a-fA-F]{6}$/.test(h) ? h : '#6366f1';
}
function alpha(hexColor: string, a: number) {
    return `${normHex(hexColor)}${Math.round(a * 255).toString(16).padStart(2, '0')}`;
}

function Card({ children, className = '' }: { children: React.ReactNode; className?: string }) {
    return (
        <div
            className={`bg-white rounded-3xl border border-slate-200/60 ${className}`}
            style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04), 0 12px 32px rgba(15,23,42,0.05)' }}
        >
            {children}
        </div>
    );
}

interface SchoolClass { id: number; grade: string; section: string; }

interface PreviewRow {
    row: number;
    data: Record<string, string>;
    errors: string[];
    valid: boolean;
}

interface Props {
    classes: SchoolClass[];
    preview?: PreviewRow[];
    validCount?: number;
    invalidCount?: number;
    tmpKey?: string;
}

export default function Import({ classes, preview, validCount, invalidCount, tmpKey }: Props) {
    const { props } = usePage<PageProps>();
    const accent = normHex(props.accent);
    const flash = (props as any).flash as { error?: string; success?: string } | undefined;

    const fileRef = useRef<HTMLInputElement>(null);
    const [fileName, setFileName] = useState('');
    const [uploading, setUploading] = useState(false);
    const [confirming, setConfirming] = useState(false);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        setFileName(e.target.files?.[0]?.name ?? '');
    }

    function handleUpload(e: React.FormEvent) {
        e.preventDefault();
        const file = fileRef.current?.files?.[0];
        if (!file) return;
        setUploading(true);
        const formData = new FormData();
        formData.append('csv_file', file);
        formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '');
        router.post('/school/students/import', formData as any, {
            forceFormData: true,
            onFinish: () => setUploading(false),
        });
    }

    function handleConfirm() {
        if (!tmpKey) return;
        setConfirming(true);
        router.post('/school/students/import/confirm', { tmp_key: tmpKey }, {
            onFinish: () => setConfirming(false),
        });
    }

    const ringStyle = { ['--tw-ring-color' as string]: alpha(accent, 0.35) };

    return (
        <SchoolLayout>
            <Head title="Import Students" />

            <div className="mb-6">
                <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Bulk Import Students</h1>
                <p className="text-sm text-slate-400 mt-1">Upload a CSV file to import multiple students at once</p>
            </div>

            {flash?.error && (
                <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2">
                    <XCircle className="w-4 h-4 flex-shrink-0" />
                    {flash.error}
                </div>
            )}

            {/* Upload form — always visible so user can re-upload */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <Card className="p-6">
                    <h3 className="font-bold text-slate-800 mb-4 flex items-center gap-2">
                        <Upload className="w-4 h-4" style={{ color: accent }} />
                        Upload CSV
                    </h3>

                    <a
                        href="/school/students/import/template"
                        className="flex items-center gap-2 text-sm font-semibold mb-5 pb-5 border-b border-slate-100"
                        style={{ color: accent }}
                    >
                        <Download className="w-4 h-4" />
                        Download CSV Template
                    </a>

                    <form onSubmit={handleUpload} className="space-y-4">
                        <div>
                            <label className="block text-xs font-bold text-slate-500 mb-1.5">CSV File</label>
                            <label
                                className="flex flex-col items-center justify-center w-full h-28 border-2 border-dashed rounded-2xl cursor-pointer transition hover:border-slate-400"
                                style={{ borderColor: fileName ? accent : undefined }}
                            >
                                <input
                                    ref={fileRef}
                                    type="file"
                                    accept=".csv,text/csv"
                                    className="hidden"
                                    onChange={handleFileChange}
                                    required
                                />
                                {fileName ? (
                                    <div className="text-center px-3">
                                        <CheckCircle className="w-5 h-5 mx-auto mb-1" style={{ color: accent }} />
                                        <p className="text-xs font-bold text-slate-700 truncate max-w-full">{fileName}</p>
                                    </div>
                                ) : (
                                    <div className="text-center">
                                        <Upload className="w-5 h-5 mx-auto mb-1 text-slate-300" />
                                        <p className="text-xs text-slate-400">Click to select file</p>
                                        <p className="text-[10px] text-slate-300 mt-0.5">.csv up to 2 MB</p>
                                    </div>
                                )}
                            </label>
                        </div>

                        <button
                            type="submit"
                            disabled={uploading || !fileName}
                            className="w-full px-4 py-2.5 rounded-2xl text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                            style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                        >
                            {uploading ? 'Validating…' : 'Preview Import'}
                        </button>
                    </form>

                    <div className="mt-5 pt-5 border-t border-slate-100 space-y-1.5">
                        <p className="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Column Reference</p>
                        <p className="text-xs text-slate-500"><span className="font-semibold text-slate-700">Required:</span> first_name, last_name, gender</p>
                        <p className="text-xs text-slate-500"><span className="font-semibold text-slate-700">Optional:</span> first_name_am, last_name_am, date_of_birth, grade, section, blood_type, status, medical_notes, guardian_name, guardian_phone, guardian_relationship</p>
                        <p className="text-[11px] text-slate-400 mt-1">grade + section must match an active class (e.g. <code className="bg-slate-100 px-1 rounded">Grade 5</code> / <code className="bg-slate-100 px-1 rounded">A</code>)</p>
                    </div>
                </Card>

                {/* Active classes reference */}
                <Card className="p-6 lg:col-span-2">
                    <h3 className="font-bold text-slate-800 mb-4">Active Classes</h3>
                    {classes.length === 0 ? (
                        <p className="text-sm text-slate-400">No active classes found. Create classes first.</p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {classes.map(c => (
                                <span
                                    key={c.id}
                                    className="text-xs px-2.5 py-1 rounded-full font-semibold"
                                    style={{ background: alpha(accent, 0.10), color: accent }}
                                >
                                    {c.grade} – {c.section}
                                </span>
                            ))}
                        </div>
                    )}
                    <p className="text-xs text-slate-400 mt-4">Use the exact grade and section text above in your CSV.</p>
                </Card>
            </div>

            {/* Preview results */}
            {preview && preview.length > 0 && (
                <>
                    {/* Summary */}
                    <div className="flex items-center gap-4 mb-4 flex-wrap">
                        <div className="flex items-center gap-1.5 px-3.5 py-2 rounded-2xl text-sm font-bold bg-white border border-slate-200/60" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04)' }}>
                            <CheckCircle className="w-4 h-4 text-green-500" />
                            <span className="text-green-600">{validCount} valid</span>
                        </div>
                        {(invalidCount ?? 0) > 0 && (
                            <div className="flex items-center gap-1.5 px-3.5 py-2 rounded-2xl text-sm font-bold bg-white border border-slate-200/60" style={{ boxShadow: '0 1px 3px rgba(15,23,42,0.04)' }}>
                                <XCircle className="w-4 h-4 text-red-500" />
                                <span className="text-red-600">{invalidCount} with errors</span>
                            </div>
                        )}
                        <span className="text-sm text-slate-400">{preview.length} total rows</span>
                    </div>

                    {(invalidCount ?? 0) > 0 && (
                        <div className="mb-4 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4 flex-shrink-0" />
                            Only valid rows will be imported. Fix errors and re-upload to include skipped rows.
                        </div>
                    )}

                    {/* Preview table */}
                    <Card className="overflow-hidden mb-6">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm whitespace-nowrap">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left">
                                        <th className="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Row</th>
                                        <th className="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">First Name</th>
                                        <th className="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Last Name</th>
                                        <th className="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Gender</th>
                                        <th className="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Class</th>
                                        <th className="px-4 py-3.5 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status / Errors</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {preview.map(row => (
                                        <tr key={row.row} className={row.valid ? 'hover:bg-slate-50/70' : 'bg-red-50/60'}>
                                            <td className="px-4 py-2.5 text-slate-400 font-mono text-xs">{row.row}</td>
                                            <td className="px-4 py-2.5 font-semibold text-slate-800">{row.data.first_name || <span className="text-red-400 font-normal italic">missing</span>}</td>
                                            <td className="px-4 py-2.5 text-slate-600">{row.data.last_name || <span className="text-red-400 italic">missing</span>}</td>
                                            <td className="px-4 py-2.5 text-slate-500 capitalize">{row.data.gender || '—'}</td>
                                            <td className="px-4 py-2.5 text-slate-500">
                                                {row.data.grade && row.data.section ? `${row.data.grade} – ${row.data.section}` : '—'}
                                            </td>
                                            <td className="px-4 py-2.5">
                                                {row.valid ? (
                                                    <span className="text-xs px-2 py-0.5 rounded-full bg-green-50 text-green-600 font-bold">Valid</span>
                                                ) : (
                                                    <ul className="list-none space-y-0.5">
                                                        {row.errors.map((err, i) => (
                                                            <li key={i} className="text-xs text-red-600 flex items-start gap-1">
                                                                <XCircle className="w-3 h-3 mt-0.5 flex-shrink-0" />
                                                                {err}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {/* Confirm import */}
                    {(validCount ?? 0) > 0 && tmpKey && (
                        <div className="flex items-center gap-4">
                            <button
                                onClick={handleConfirm}
                                disabled={confirming}
                                className="px-6 py-2.5 rounded-2xl text-sm font-bold text-white transition hover:-translate-y-0.5 disabled:opacity-60"
                                style={{ background: accent, boxShadow: `0 8px 20px ${alpha(accent, 0.4)}` }}
                            >
                                {confirming ? 'Importing…' : `Import ${validCount} Student${validCount !== 1 ? 's' : ''}`}
                            </button>
                            <Link href="/school/students/import" className="text-sm font-semibold text-slate-500 hover:text-slate-700">
                                Cancel
                            </Link>
                        </div>
                    )}
                </>
            )}

            <div className="mt-6">
                <Link href="/school/students" className="text-sm font-semibold text-slate-500 hover:text-slate-700">← Back to Students</Link>
            </div>
        </SchoolLayout>
    );
}
