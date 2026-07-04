<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Student fee management: a catalogue of fee structures, per-student invoices
 * generated from them (or ad-hoc), and a payment ledger. Balances are always
 * derived from the ledger, never stored.
 *
 * Admin-only writes + 'feature:fees' gate applied at the route level.
 */
class FeeController extends BaseSchoolController
{
    public function index(Request $request)
    {
        $status = $request->get('status');
        $classId = (int) $request->get('class_id');

        $invoices = StudentInvoice::with(['student:id,first_name,last_name,class_id', 'student.schoolClass:id,grade,section', 'payments:id,student_invoice_id,amount_etb'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($classId, fn ($q) => $q->whereHas('student', fn ($s) => $s->where('class_id', $classId)))
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(function (StudentInvoice $inv) {
                $paid = (float) $inv->payments->sum('amount_etb');
                return [
                    'id'          => $inv->id,
                    'title'       => $inv->title,
                    'student'     => $inv->student?->full_name,
                    'class'       => $inv->student?->schoolClass?->display_name,
                    'amount'      => (float) $inv->amount_etb,
                    'paid'        => $paid,
                    'balance'     => max(0, (float) $inv->amount_etb - $paid),
                    'status'      => $inv->status,
                    'due_date'    => $inv->due_date?->toDateString(),
                ];
            });

        // Money summary across ALL non-void invoices (not just the filtered list).
        $live = StudentInvoice::where('status', '!=', 'void');
        $totalInvoiced = (float) (clone $live)->sum('amount_etb');
        $totalCollected = (float) FeePayment::whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))->sum('amount_etb');

        return Inertia::render('School/Fees/Index', [
            'invoices' => $invoices->values(),
            'summary'  => [
                'invoiced'    => $totalInvoiced,
                'collected'   => $totalCollected,
                'outstanding' => max(0, $totalInvoiced - $totalCollected),
            ],
            'structures' => FeeStructure::with('targetClass:id,grade,section')->orderBy('name')->get()
                ->map(fn ($f) => [
                    'id'          => $f->id,
                    'name'        => $f->name,
                    'amount'      => (float) $f->amount_etb,
                    'frequency'   => $f->frequency,
                    'applies_to'  => $f->applies_to,
                    'target_class'=> $f->targetClass?->display_name,
                    'target_class_id' => $f->target_class_id,
                    'is_active'   => $f->is_active,
                ])->values(),
            'classes'  => SchoolClass::where('is_active', true)->orderBy('grade')->orderBy('section')->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name])->values(),
            'students' => Student::where('status', 'active')->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name])->values(),
            'filters'  => ['status' => $status, 'class_id' => $classId ?: null],
        ]);
    }

    // ── Fee structures (the catalogue) ───────────────────────────

    public function storeStructure(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $this->validateStructure($request);
        $validated['school_id'] = $this->schoolId();

        FeeStructure::create($validated);
        AuditLog::log('fees.structure_created', 'fee_structures', null, null, $validated);

        return back()->with('success', 'Fee type added.');
    }

    public function updateStructure(Request $request, FeeStructure $structure)
    {
        $this->requireAdmin();

        $structure->update($this->validateStructure($request));

        return back()->with('success', 'Fee type updated.');
    }

    public function destroyStructure(FeeStructure $structure)
    {
        $this->requireAdmin();

        // Detach from invoices rather than cascade-deleting billing history.
        $structure->delete();

        return back()->with('success', 'Fee type removed. Existing invoices are kept.');
    }

    private function validateStructure(Request $request): array
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:120',
            'amount_etb'      => 'required|numeric|min:0',
            'frequency'       => ['required', Rule::in(['one_time', 'monthly', 'term', 'annual'])],
            'applies_to'      => ['required', Rule::in(['all', 'class'])],
            'target_class_id' => ['nullable', 'required_if:applies_to,class', Rule::exists('classes', 'id')->where('school_id', $this->schoolId())],
            'academic_year'   => 'nullable|string|max:20',
            'is_active'       => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active', true);
        if ($validated['applies_to'] === 'all') {
            $validated['target_class_id'] = null;
        }

        return $validated;
    }

    /**
     * Bulk-generate one invoice per matching active student from a structure.
     * Idempotent: a student who already has an invoice from this structure for
     * the same academic year is skipped, so re-running never double-bills.
     */
    public function generateInvoices(Request $request, FeeStructure $structure)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'due_date' => 'nullable|date',
        ]);

        $students = Student::where('status', 'active')
            ->when($structure->applies_to === 'class' && $structure->target_class_id,
                fn ($q) => $q->where('class_id', $structure->target_class_id))
            ->get(['id']);

        $created = 0;
        DB::transaction(function () use ($students, $structure, $validated, &$created) {
            foreach ($students as $student) {
                $exists = StudentInvoice::where('student_id', $student->id)
                    ->where('fee_structure_id', $structure->id)
                    ->where('academic_year', $structure->academic_year)
                    ->exists();
                if ($exists) {
                    continue;
                }

                StudentInvoice::create([
                    'school_id'        => $this->schoolId(),
                    'student_id'       => $student->id,
                    'fee_structure_id' => $structure->id,
                    'title'            => $structure->name,
                    'amount_etb'       => $structure->amount_etb,
                    'due_date'         => $validated['due_date'] ?? null,
                    'academic_year'    => $structure->academic_year,
                    'status'           => 'unpaid',
                    'created_by'       => auth()->id(),
                ]);
                $created++;
            }
        });

        AuditLog::log('fees.invoices_generated', 'fee_structures', $structure->id, null, ['created' => $created]);

        return back()->with('success', $created > 0
            ? "{$created} invoice(s) generated."
            : 'No new invoices — every matching student already has one.');
    }

    // ── Invoices ─────────────────────────────────────────────────

    public function storeInvoice(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'student_id'    => ['required', Rule::exists('students', 'id')->where('school_id', $this->schoolId())],
            'title'         => 'required|string|max:150',
            'amount_etb'    => 'required|numeric|min:0.01',
            'due_date'      => 'nullable|date',
            'academic_year' => 'nullable|string|max:20',
            'note'          => 'nullable|string|max:255',
        ]);

        StudentInvoice::create($validated + [
            'school_id'  => $this->schoolId(),
            'status'     => 'unpaid',
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Invoice added.');
    }

    public function waiveInvoice(StudentInvoice $invoice)
    {
        $this->requireAdmin();

        $invoice->update(['status' => 'waived']);
        AuditLog::log('fees.invoice_waived', 'student_invoices', $invoice->id, null, null);

        return back()->with('success', 'Invoice waived.');
    }

    public function destroyInvoice(StudentInvoice $invoice)
    {
        $this->requireAdmin();

        $invoice->delete(); // payments cascade via FK
        return back()->with('success', 'Invoice deleted.');
    }

    // ── Payments ─────────────────────────────────────────────────

    public function recordPayment(Request $request, StudentInvoice $invoice)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        if (in_array($invoice->status, ['waived', 'void'], true)) {
            return back()->with('error', 'This invoice is not collectible.');
        }

        $validated = $request->validate([
            'amount_etb'   => 'required|numeric|min:0.01',
            'method'       => ['required', Rule::in(['cash', 'bank_transfer', 'telebirr', 'other'])],
            'reference'    => 'nullable|string|max:120',
            'payment_date' => 'required|date',
            'note'         => 'nullable|string|max:255',
        ]);

        // Lock the invoice so two cashiers can't jointly overpay it (TOCTOU).
        DB::transaction(function () use ($invoice, $validated) {
            $locked = StudentInvoice::whereKey($invoice->id)->lockForUpdate()->first();
            $balance = $locked->balance();

            if ($validated['amount_etb'] > $balance + 0.001) {
                abort(422, "Payment exceeds the outstanding balance of {$balance} ETB.");
            }

            FeePayment::create($validated + [
                'school_id'          => $this->schoolId(),
                'student_invoice_id' => $locked->id,
                'recorded_by'        => auth()->id(),
            ]);

            $locked->syncStatus();
        });

        AuditLog::log('fees.payment_recorded', 'student_invoices', $invoice->id, null, ['amount' => $validated['amount_etb']]);

        return back()->with('success', 'Payment recorded.');
    }

    public function invoicePayments(StudentInvoice $invoice)
    {
        return response()->json(
            $invoice->payments()->orderByDesc('payment_date')->get()
                ->map(fn ($p) => [
                    'id'           => $p->id,
                    'amount'       => (float) $p->amount_etb,
                    'method'       => $p->method,
                    'reference'    => $p->reference,
                    'payment_date' => $p->payment_date?->toDateString(),
                    'note'         => $p->note,
                ])
        );
    }
}
