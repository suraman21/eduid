import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ArrowLeft, Check, Download, Loader2, AlertTriangle } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import TemplateCard, { type CardTemplateData, type TemplateValues } from '@/Components/TemplateCard';

interface Student {
    id: number;
    full_name: string;
    full_name_am: string | null;
    uid: string;
    class_name: string | null;
    date_of_birth: string | null;
    blood_type: string | null;
    has_photo: boolean;
    photo_url: string | null;
    qr_svg: string;
}

interface SheetTemplate {
    id: number;
    key: string;
    name: string;
    sheet_w_mm: number;
    sheet_h_mm: number;
    card_w_mm: number;
    card_h_mm: number;
    columns: number;
    rows: number;
    slot_count: number;
    positions: Record<number, { x: number; y: number }>;
}

interface SheetSlot { slot_number: number; status: 'empty' | 'printed'; student_id: number | null; }
interface OpenSheet { id: number; sheet_template_id: number; status: string; next_empty_slot: number | null; slots: SheetSlot[]; }
interface Printer { id: number; name: string; is_default: boolean; flip_instruction: string; }

interface Props {
    order: { id: number; order_code: string };
    school: { id: number; name: string; code: string; academic_year: string | null };
    template: (CardTemplateData & { cardWmm: number; cardHmm: number }) | null;
    students: Student[];
    sheetTemplates: SheetTemplate[];
    openSheets: OpenSheet[];
    printers: Printer[];
}

type Side = 'front' | 'back' | 'both';

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const STEPS = ['Students', 'Side', 'Sheet', 'Slots', 'Preview'];

export default function Wizard({ order, school, template, students, sheetTemplates, openSheets, printers = [] }: Props) {
    // Card orientation drives the default sheet: a portrait card must print on a
    // portrait sheet so it stays vertical (never squished into a landscape slot).
    const cardIsPortrait = template ? template.cardHmm > template.cardWmm : null;
    const sheetIsPortrait = (t: SheetTemplate) => t.card_h_mm > t.card_w_mm;
    const defaultSheet =
        (cardIsPortrait !== null ? sheetTemplates.find(t => sheetIsPortrait(t) === cardIsPortrait) : undefined)
        ?? sheetTemplates[0];

    const [step, setStep] = useState(1);
    const [selectedIds, setSelectedIds] = useState<number[]>(students.map(s => s.id));
    const [side, setSide] = useState<Side>('both');
    const [sheetTemplateId, setSheetTemplateId] = useState<number | null>(defaultSheet?.id ?? null);
    const [targetSheet, setTargetSheet] = useState<number | 'new'>('new');
    const [generating, setGenerating] = useState(false);
    const [result, setResult] = useState<{ front?: string; back?: string } | null>(null);
    const [flipInstruction, setFlipInstruction] = useState<string | null>(null);
    const [printerId, setPrinterId] = useState<number | null>(printers.find(p => p.is_default)?.id ?? printers[0]?.id ?? null);
    const [error, setError] = useState<string | null>(null);

    const sheetTemplate = useMemo(
        () => sheetTemplates.find(t => t.id === sheetTemplateId) ?? null,
        [sheetTemplates, sheetTemplateId],
    );

    const selectedStudents = useMemo(
        () => students.filter(s => selectedIds.includes(s.id)),
        [students, selectedIds],
    );

    // Sheets compatible with the chosen template that still have room.
    const resumableSheets = useMemo(
        () => openSheets.filter(s => s.sheet_template_id === sheetTemplateId && s.next_empty_slot !== null),
        [openSheets, sheetTemplateId],
    );

    const startSlot = useMemo(() => {
        if (targetSheet === 'new') return 1;
        return resumableSheets.find(s => s.id === targetSheet)?.next_empty_slot ?? 1;
    }, [targetSheet, resumableSheets]);

    const printedSlots = useMemo(() => {
        if (targetSheet === 'new') return new Set<number>();
        const sheet = resumableSheets.find(s => s.id === targetSheet);
        return new Set((sheet?.slots ?? []).filter(s => s.status === 'printed').map(s => s.slot_number));
    }, [targetSheet, resumableSheets]);

    const capacity = sheetTemplate ? sheetTemplate.slot_count - (startSlot - 1) : 0;
    const overflow = selectedStudents.length > capacity;

    // slot → student assignment (contiguous from startSlot)
    const assignments = useMemo(
        () => selectedStudents.slice(0, capacity).map((s, i) => ({ slot: startSlot + i, student: s })),
        [selectedStudents, startSlot, capacity],
    );

    function toggleStudent(id: number) {
        setSelectedIds(prev => prev.includes(id) ? prev.filter(x => x !== id) : [...prev, id]);
    }

    function toValues(s: Student): TemplateValues {
        return {
            full_name: s.full_name, full_name_am: s.full_name_am, uid: s.uid,
            class_name: s.class_name, date_of_birth: s.date_of_birth, blood_type: s.blood_type,
            academic_year: school.academic_year ?? undefined, school_name: school.name,
            photo_url: s.photo_url, qr_svg: s.qr_svg,
        };
    }

    async function generate() {
        if (!sheetTemplate) return;
        setGenerating(true); setError(null); setResult(null); setFlipInstruction(null);
        try {
            const res = await fetch(`/admin/card-orders/${order.id}/sheet-print/generate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({
                    sheet_template_id: sheetTemplate.id,
                    print_sheet_id: targetSheet === 'new' ? null : targetSheet,
                    printer_profile_id: printerId,
                    side,
                    assignments: assignments.map(a => ({ slot: a.slot, student_id: a.student.id })),
                }),
            });
            const data = await res.json();
            if (!res.ok) { setError(data.message ?? 'Generation failed.'); return; }
            setResult(data.urls ?? {});
            setFlipInstruction(data.flip_instruction ?? null);
        } catch (e) {
            setError('Network error while generating the sheet.');
        } finally {
            setGenerating(false);
        }
    }

    const canNext =
        (step === 1 && selectedStudents.length > 0) ||
        (step === 2) ||
        (step === 3 && !!sheetTemplate) ||
        (step === 4 && !overflow && assignments.length > 0) ||
        step === 5;

    return (
        <AdminLayout>
            <Head title={`Print Sheet — ${order.order_code}`} />

            <div className="mb-4">
                <Link href={`/admin/card-orders/${order.id}`} className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-purple-400 transition">
                    <ArrowLeft className="w-4 h-4" /> Back to Order
                </Link>
            </div>

            <h1 className="text-xl font-bold text-slate-100">Professional Sheet Printing</h1>
            <p className="text-sm text-slate-500 mb-6">{school.name} · {order.order_code}</p>

            {/* Stepper */}
            <div className="flex items-center gap-2 mb-8 flex-wrap">
                {STEPS.map((label, i) => {
                    const n = i + 1;
                    const done = step > n;
                    const active = step === n;
                    return (
                        <div key={label} className="flex items-center gap-2">
                            <div className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm ${active ? 'bg-purple-600 text-white' : done ? 'bg-emerald-600/20 text-emerald-300' : 'bg-white/5 text-slate-500'}`}>
                                <span className="w-5 h-5 rounded-full bg-black/20 flex items-center justify-center text-xs">
                                    {done ? <Check className="w-3 h-3" /> : n}
                                </span>
                                {label}
                            </div>
                            {n < STEPS.length && <div className="w-4 h-px bg-white/10" />}
                        </div>
                    );
                })}
            </div>

            <div className="max-w-4xl">
                {/* Step 1 — Students */}
                {step === 1 && (
                    <Panel title="Select students" subtitle={`${selectedStudents.length} of ${students.length} selected`}>
                        <div className="flex gap-2 mb-3">
                            <button onClick={() => setSelectedIds(students.map(s => s.id))} className="text-xs text-purple-400 hover:text-purple-300">Select all</button>
                            <span className="text-slate-600">·</span>
                            <button onClick={() => setSelectedIds([])} className="text-xs text-slate-500 hover:text-slate-300">Clear</button>
                        </div>
                        <div className="grid sm:grid-cols-2 gap-1.5 max-h-96 overflow-y-auto">
                            {students.map(s => (
                                <label key={s.id} className={`flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer border ${selectedIds.includes(s.id) ? 'bg-purple-600/10 border-purple-500/40' : 'bg-white/[0.02] border-white/10'}`}>
                                    <input type="checkbox" checked={selectedIds.includes(s.id)} onChange={() => toggleStudent(s.id)} className="accent-purple-500" />
                                    <span className="flex-1 text-sm text-slate-200">{s.full_name}</span>
                                    <span className="text-xs text-slate-500 font-mono">{s.uid}</span>
                                    {!s.has_photo && <span className="text-[10px] text-amber-400">no photo</span>}
                                </label>
                            ))}
                        </div>
                    </Panel>
                )}

                {/* Step 2 — Side */}
                {step === 2 && (
                    <Panel title="Which side(s) to print?" subtitle="Front and back are produced as separate PDFs in the same slot order.">
                        <div className="grid sm:grid-cols-3 gap-3">
                            {(['front', 'back', 'both'] as Side[]).map(opt => (
                                <button key={opt} onClick={() => setSide(opt)} className={`px-4 py-6 rounded-xl border text-center capitalize ${side === opt ? 'bg-purple-600/15 border-purple-500 text-white' : 'bg-white/[0.02] border-white/10 text-slate-400'}`}>
                                    <p className="font-semibold">{opt === 'both' ? 'Front & Back' : opt}</p>
                                    <p className="text-xs text-slate-500 mt-1">{opt === 'both' ? '2 PDFs' : '1 PDF'}</p>
                                </button>
                            ))}
                        </div>
                    </Panel>
                )}

                {/* Step 3 — Sheet template */}
                {step === 3 && (
                    <Panel title="Choose sheet template" subtitle={cardIsPortrait !== null ? `This card is ${cardIsPortrait ? 'portrait' : 'landscape'} — pick a matching sheet so it isn't squished.` : undefined}>
                        <div className="grid sm:grid-cols-2 gap-3">
                            {sheetTemplates.map(t => {
                                const matches = cardIsPortrait === null || sheetIsPortrait(t) === cardIsPortrait;
                                return (
                                    <button
                                        key={t.id}
                                        onClick={() => { setSheetTemplateId(t.id); setTargetSheet('new'); }}
                                        className={`px-4 py-4 rounded-xl border text-left transition ${sheetTemplateId === t.id ? 'bg-purple-600/15 border-purple-500' : 'bg-white/[0.02] border-white/10 hover:border-white/30'}`}
                                    >
                                        <p className="font-semibold text-slate-100 flex items-center gap-2">
                                            {t.name}
                                            <span className="text-[10px] px-1.5 py-0.5 rounded bg-slate-600/30 text-slate-300">{sheetIsPortrait(t) ? 'Portrait slots' : 'Landscape slots'}</span>
                                            {!matches && <span className="text-[10px] px-1.5 py-0.5 rounded bg-blue-600/20 text-blue-300">card rotated 90° to fit</span>}
                                        </p>
                                        <p className="text-xs text-slate-500 mt-1">{t.columns}×{t.rows} · {t.slot_count} cards · {t.sheet_w_mm}×{t.sheet_h_mm}mm</p>
                                    </button>
                                );
                            })}
                        </div>
                        {cardIsPortrait !== null && sheetTemplate && sheetIsPortrait(sheetTemplate) !== cardIsPortrait && (
                            <div className="mt-3 flex items-start gap-2 text-sm text-blue-300 bg-blue-500/10 border border-blue-500/30 rounded-lg p-3">
                                <AlertTriangle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                                <span>The card is {cardIsPortrait ? 'portrait' : 'landscape'} and this sheet has {sheetIsPortrait(sheetTemplate) ? 'portrait' : 'landscape'} slots. Each card will be <strong>rotated 90° to fit</strong> — no distortion. Pick the matching-orientation sheet if you’d rather not rotate.</span>
                            </div>
                        )}
                    </Panel>
                )}

                {/* Step 4 — Slots */}
                {step === 4 && sheetTemplate && (
                    <Panel title="Choose slots" subtitle={`Filling sequentially from slot ${startSlot}. The cutter requires contiguous slots.`}>
                        <div className="mb-4">
                            <label className="text-xs text-slate-500 uppercase tracking-wide">Target sheet</label>
                            <div className="flex flex-wrap gap-2 mt-1.5">
                                <button onClick={() => setTargetSheet('new')} className={`px-3 py-1.5 rounded-lg text-sm border ${targetSheet === 'new' ? 'bg-purple-600 text-white border-purple-500' : 'bg-white/5 text-slate-400 border-white/10'}`}>
                                    New sheet
                                </button>
                                {resumableSheets.map(s => (
                                    <button key={s.id} onClick={() => setTargetSheet(s.id)} className={`px-3 py-1.5 rounded-lg text-sm border ${targetSheet === s.id ? 'bg-purple-600 text-white border-purple-500' : 'bg-white/5 text-slate-400 border-white/10'}`}>
                                        Sheet #{s.id} (resume @ {s.next_empty_slot})
                                    </button>
                                ))}
                            </div>
                        </div>

                        <SlotGrid template={sheetTemplate} printed={printedSlots} assignments={assignments} />

                        {overflow && (
                            <div className="mt-4 flex items-start gap-2 text-sm text-amber-300 bg-amber-500/10 border border-amber-500/30 rounded-lg p-3">
                                <AlertTriangle className="w-4 h-4 mt-0.5 flex-shrink-0" />
                                <span>{selectedStudents.length} cards selected but only {capacity} slot(s) free on this sheet. Reduce the selection or start a new sheet.</span>
                            </div>
                        )}
                    </Panel>
                )}

                {/* Step 5 — Preview + generate */}
                {step === 5 && sheetTemplate && (
                    <Panel title="Preview & generate" subtitle="This preview matches the generated PDF exactly (same mm coordinates).">
                        {/* Printer profile — calibration offsets are baked into the PDF */}
                        <div className="mb-4 flex flex-wrap items-center gap-3">
                            <label className="text-xs text-slate-500 uppercase tracking-wide">Printer</label>
                            <select
                                value={printerId ?? ''}
                                onChange={e => setPrinterId(e.target.value ? Number(e.target.value) : null)}
                                className="bg-white/5 border border-white/10 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-purple-500"
                            >
                                <option value="">No calibration (raw)</option>
                                {printers.map(p => (
                                    <option key={p.id} value={p.id}>{p.name}{p.is_default ? ' (default)' : ''}</option>
                                ))}
                            </select>
                            <a href="/admin/printers" className="text-xs text-purple-400 hover:text-purple-300">Manage printers & calibration →</a>
                        </div>

                        <A4Preview template={sheetTemplate} cardTemplate={template} assignments={assignments} toValues={toValues} side={side} />

                        {error && (
                            <div className="mt-4 flex items-start gap-2 text-sm text-rose-300 bg-rose-500/10 border border-rose-500/30 rounded-lg p-3">
                                <AlertTriangle className="w-4 h-4 mt-0.5 flex-shrink-0" /> {error}
                            </div>
                        )}

                        {result ? (
                            <div className="mt-5 space-y-2">
                                <p className="text-sm text-emerald-300">Sheet generated and slots recorded.</p>
                                {result.front && <a href={result.front} className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm px-4 py-2 rounded-lg mr-2"><Download className="w-4 h-4" /> Front PDF</a>}
                                {result.back && <a href={result.back} className="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm px-4 py-2 rounded-lg"><Download className="w-4 h-4" /> Back PDF</a>}
                                {flipInstruction && (
                                    <div className="mt-3 text-sm text-blue-300 bg-blue-500/10 border border-blue-500/30 rounded-lg p-3">
                                        <strong>Duplex:</strong> print the FRONT first, then reinsert the sheet — {flipInstruction} Then print the BACK.
                                    </div>
                                )}
                                <p className="text-[11px] text-slate-500 pt-2">Print at 100% / "Actual size" — never "Fit to page" — or front/back registration will drift.</p>
                            </div>
                        ) : (
                            <button onClick={generate} disabled={generating || overflow} className="mt-5 inline-flex items-center gap-2 bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-500 hover:to-blue-500 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2.5 rounded-xl">
                                {generating ? <Loader2 className="w-4 h-4 animate-spin" /> : null}
                                {generating ? 'Generating…' : `Generate ${side === 'both' ? 'Front & Back' : side} PDF`}
                            </button>
                        )}
                    </Panel>
                )}

                {/* Nav */}
                <div className="flex items-center justify-between mt-6">
                    <button onClick={() => setStep(s => Math.max(1, s - 1))} disabled={step === 1} className="px-4 py-2 rounded-lg text-sm text-slate-400 hover:text-slate-200 disabled:opacity-30">
                        Back
                    </button>
                    {step < 5 && (
                        <button onClick={() => setStep(s => s + 1)} disabled={!canNext} className="px-5 py-2 rounded-lg text-sm font-medium bg-purple-600 hover:bg-purple-500 disabled:opacity-40 text-white">
                            Next
                        </button>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}

function Panel({ title, subtitle, children }: { title: string; subtitle?: string; children: React.ReactNode }) {
    return (
        <div className="bg-white/[0.025] border border-white/10 rounded-xl p-6">
            <h2 className="text-slate-100 font-semibold">{title}</h2>
            {subtitle && <p className="text-xs text-slate-500 mt-0.5 mb-4">{subtitle}</p>}
            {!subtitle && <div className="mb-4" />}
            {children}
        </div>
    );
}

/** Simple slot grid (not to scale) for fast selection feedback. */
function SlotGrid({ template, printed, assignments }: {
    template: SheetTemplate;
    printed: Set<number>;
    assignments: { slot: number; student: Student }[];
}) {
    const assignedMap = new Map(assignments.map(a => [a.slot, a.student]));
    const slots = Array.from({ length: template.slot_count }, (_, i) => i + 1);
    return (
        <div className="grid gap-2" style={{ gridTemplateColumns: `repeat(${template.columns}, minmax(0,1fr))` }}>
            {slots.map(n => {
                const isPrinted = printed.has(n);
                const assigned = assignedMap.get(n);
                const tone = assigned ? 'bg-purple-600/20 border-purple-500 text-purple-100'
                    : isPrinted ? 'bg-slate-700/40 border-slate-600 text-slate-400'
                    : 'bg-white/[0.02] border-white/10 text-slate-500';
                return (
                    <div key={n} className={`rounded-lg border px-3 py-3 text-center ${tone}`}>
                        <p className="text-xs font-mono">Slot {n}</p>
                        <p className="text-xs mt-1 truncate">
                            {assigned ? assigned.full_name : isPrinted ? 'Printed' : 'Empty'}
                        </p>
                    </div>
                );
            })}
        </div>
    );
}

/** Accurate A4 preview — same mm geometry as the PDF. */
function A4Preview({ template, cardTemplate, assignments, toValues, side }: {
    template: SheetTemplate;
    cardTemplate: (CardTemplateData & { cardWmm: number; cardHmm: number }) | null;
    assignments: { slot: number; student: Student }[];
    toValues: (s: Student) => TemplateValues;
    side: Side;
}) {
    const DISPLAY_W = 360; // px for the sheet width
    const f = DISPLAY_W / template.sheet_w_mm; // px per mm
    const sheetH = template.sheet_h_mm * f;
    const cardSide = side === 'back' ? 'back' : 'front';

    return (
        <div className="relative mx-auto bg-white rounded-md shadow-xl" style={{ width: DISPLAY_W, height: sheetH }}>
            {assignments.map(a => {
                const pos = template.positions[a.slot];
                if (!pos) return null;
                return (
                    <div key={a.slot} className="absolute" style={{ left: pos.x * f, top: pos.y * f }}>
                        {cardTemplate ? (
                            <TemplateCard template={cardTemplate} values={toValues(a.student)} side={cardSide} displayWidth={template.card_w_mm * f} preview={false} />
                        ) : (
                            <div className="border border-slate-300 bg-slate-50 flex flex-col items-center justify-center text-center" style={{ width: template.card_w_mm * f, height: template.card_h_mm * f }}>
                                <span className="text-[8px] text-slate-700 font-semibold px-1 truncate">{a.student.full_name}</span>
                                <span className="text-[7px] text-slate-400 font-mono">{a.student.uid}</span>
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
