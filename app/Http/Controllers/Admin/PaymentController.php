<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\School;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::with(['school', 'recordedBy', 'plan'])->latest('payment_date');

        if ($schoolId = $request->get('school_id')) {
            $query->where('school_id', $schoolId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $payments = $query->paginate(25);
        $schools  = School::orderBy('name')->get(['id', 'name']);

        $totalConfirmed = Payment::where('status', 'confirmed')->sum('amount_etb');
        $pendingCount   = Payment::where('status', 'pending')->count();

        return view('admin.payments.index', compact('payments', 'schools', 'totalConfirmed', 'pendingCount'));
    }

    public function create()
    {
        return view('admin.payments.create', [
            'schools' => School::orderBy('name')->get(['id', 'name', 'code']),
            'plans'   => SubscriptionPlan::where('is_active', true)->orderBy('tier')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'school_id'        => 'required|exists:schools,id',
            'plan_id'          => 'nullable|exists:subscription_plans,id',
            'amount_etb'       => 'required|numeric|min:0',
            'payment_method'   => 'required|in:bank_transfer,cash,chapa,other',
            'bank_name'        => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:200',
            'payment_date'     => 'required|date',
            'period_months'    => 'required|integer|min:1|max:24',
            'notes'            => 'nullable|string|max:1000',
            'status'           => 'nullable|in:pending,confirmed',
        ]);

        $validated['recorded_by'] = auth()->id();
        $validated['status']      = $validated['status'] ?? 'pending';

        if ($validated['status'] === 'confirmed') {
            $validated['confirmed_at'] = now();
            $validated['confirmed_by'] = auth()->id();
        }

        $payment = Payment::create($validated);

        if ($payment->status === 'confirmed') {
            $this->extendSubscription($payment->school, $payment->period_months);
        }

        AuditLog::log('payment.recorded', 'payments', $payment->id, null, [
            'school'  => $payment->school->name,
            'amount'  => $payment->amount_etb,
            'status'  => $payment->status,
        ]);

        return redirect()->route('admin.payments.show', $payment)
            ->with('success', 'Payment recorded.');
    }

    public function show(Payment $payment)
    {
        $payment->load(['school', 'recordedBy', 'confirmedBy', 'plan']);

        return view('admin.payments.show', compact('payment'));
    }

    public function confirm(Payment $payment)
    {
        if ($payment->status !== 'pending') {
            return back()->with('error', 'This payment is already confirmed or not pending.');
        }

        $payment->update([
            'status'       => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_by' => auth()->id(),
        ]);

        $this->extendSubscription($payment->school, $payment->period_months);

        AuditLog::log('payment.confirmed', 'payments', $payment->id, null, [
            'school' => $payment->school->name,
        ]);

        return back()->with('success', 'Payment confirmed. Subscription extended.');
    }

    private function extendSubscription(School $school, int $months): void
    {
        $base = $school->subscription_expires_at && $school->subscription_expires_at->isFuture()
            ? $school->subscription_expires_at
            : now();

        $school->update([
            'subscription_status'     => 'active',
            'subscription_expires_at' => $base->addMonths($months),
        ]);
    }
}
