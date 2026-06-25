<?php

namespace App\Http\Controllers\School;

use App\Models\AuditLog;
use App\Models\CardOrder;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * School-facing card ordering. A school admin selects students, the system
 * mints a confirmation code (ORD-{CODE}-{YEAR}-{SEQ}) and records a PENDING
 * order. The school shares that code with Arkeon out-of-band; the Super Admin
 * processes it from the admin panel. The school never sees a print file.
 */
class CardOrderController extends BaseSchoolController
{
    public function index()
    {
        $orders = CardOrder::with('orderedBy')
            ->orderByDesc('created_at')
            ->paginate(20);

        return Inertia::render('School/CardOrders/Index', [
            'orders' => $orders->through(fn ($o) => [
                'id'            => $o->id,
                'order_code'    => $o->order_code,
                'order_type'    => $o->order_type,
                'student_count' => $o->student_count,
                'amount_etb'    => $o->amount_etb,
                'status'        => $o->status,
                'created_at'    => $o->created_at->format('M d, Y'),
            ]),
        ]);
    }

    public function create(Request $request)
    {
        $this->requireAdmin();

        $classId = $request->get('class_id');

        $roster = Student::with('schoolClass')
            ->where('status', 'active')
            ->when($classId, fn ($q) => $q->where('class_id', $classId))
            ->orderBy('first_name')
            ->get();

        // Students already sitting in an open order — surfaced as a warning so
        // the admin doesn't double-order cards for the same student.
        $alreadyOrdered = $this->studentsInOpenOrders();

        return Inertia::render('School/CardOrders/Create', [
            'roster' => $roster->map(fn ($s) => [
                'id'         => $s->id,
                'full_name'  => $s->full_name,
                'uid'        => $s->uid,
                'initials'   => strtoupper(substr($s->first_name, 0, 1) . substr($s->last_name, 0, 1)),
                'class_name' => $s->schoolClass?->display_name,
                'has_photo'  => $s->hasPhoto(),
                'in_open'    => in_array($s->id, $alreadyOrdered, true),
            ]),
            'classes'     => $this->activeClasses()->map(fn ($c) => ['id' => $c->id, 'name' => $c->display_name]),
            'class_id'    => $classId ? (int) $classId : null,
            'order_types' => array_map(fn ($t) => [
                'value' => $t,
                'label' => ucfirst(str_replace('_', ' ', $t)),
                'price' => CardOrder::PRICING[$t],
            ], CardOrder::SCHOOL_ORDER_TYPES),
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAdmin();
        $this->requireWritableSubscription();

        $validated = $request->validate([
            'order_type'   => ['required', Rule::in(CardOrder::SCHOOL_ORDER_TYPES)],
            'student_ids'  => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'integer',
                Rule::exists('students', 'id')->where(fn ($q) => $q
                    ->where('school_id', $this->schoolId())
                    ->where('status', 'active')),
            ],
        ]);

        // Deduplicate and re-validate count server-side (never trust the form).
        $studentIds = array_values(array_unique(array_map('intval', $validated['student_ids'])));
        $count      = count($studentIds);
        $unit       = CardOrder::unitPrice($validated['order_type']);

        $order = $this->createOrderAtomically([
            'ordered_by'    => auth()->id(),
            'student_ids'   => $studentIds,
            'student_count' => $count,
            'order_type'    => $validated['order_type'],
            'status'        => 'pending',
            'amount_etb'    => $unit * $count,
        ]);

        AuditLog::log('card_order.created', 'card_orders', $order->id, null, [
            'order_code'    => $order->order_code,
            'student_count' => $count,
            'order_type'    => $order->order_type,
            'amount_etb'    => $order->amount_etb,
        ]);

        return redirect()
            ->route('school.card-orders.show', $order)
            ->with('success', "Order {$order->order_code} created. Share this code with Arkeon to confirm.");
    }

    public function show(CardOrder $cardOrder)
    {
        $cardOrder->load('orderedBy');
        $students = $cardOrder->getStudents()->load('schoolClass');

        $steps = ['pending', 'confirmed', 'printing', 'shipped', 'delivered'];
        $stepIdx = array_search($cardOrder->status, $steps, true);

        return Inertia::render('School/CardOrders/Show', [
            'order' => [
                'id'            => $cardOrder->id,
                'order_code'    => $cardOrder->order_code,
                'order_type'    => $cardOrder->order_type,
                'student_count' => $cardOrder->student_count,
                'amount_etb'    => $cardOrder->amount_etb,
                'status'        => $cardOrder->status,
                'is_pending'    => $cardOrder->isPending(),
                'is_cancelled'  => $cardOrder->isCancelled(),
                'can_cancel'    => $cardOrder->canBeCancelledBy(auth()->user()),
                'cancel_reason' => $cardOrder->cancel_reason,
                'tracking_note' => $cardOrder->tracking_note,
                'ordered_by'    => $cardOrder->orderedBy?->name,
                'created_at'    => $cardOrder->created_at->format('M d, Y'),
                'step_index'    => $stepIdx !== false ? $stepIdx : -1,
            ],
            'students' => $students->map(fn ($s) => [
                'id'        => $s->id,
                'full_name' => $s->full_name,
                'uid'       => $s->uid,
            ]),
        ]);
    }

    public function cancel(Request $request, CardOrder $cardOrder)
    {
        $this->requireAdmin();

        // A school may only cancel its own still-pending order.
        if (! $cardOrder->canBeCancelledBy(auth()->user())) {
            return back()->with('error', 'This order can no longer be cancelled. Please contact Arkeon.');
        }

        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $cardOrder->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => $validated['cancel_reason'] ?? 'Cancelled by school.',
        ]);

        AuditLog::log('card_order.cancelled', 'card_orders', $cardOrder->id, null, [
            'order_code' => $cardOrder->order_code,
        ]);

        return redirect()
            ->route('school.card-orders.index')
            ->with('success', "Order {$cardOrder->order_code} cancelled.");
    }

    // ── Internals ───────────────────────────────────────────────

    /**
     * Create the order with a unique confirmation code. The code embeds the
     * school code + Gregorian year; the trailing sequence is allocated by
     * counting existing orders, and the unique index is the real arbiter —
     * we retry on collision rather than trusting the count under concurrency.
     */
    private function createOrderAtomically(array $attributes): CardOrder
    {
        $school = $this->currentSchool();
        $year   = now()->year;
        $prefix = "ORD-{$school->code}-{$year}-";

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $prefix) {
                    $seq = CardOrder::withoutGlobalScope('school')
                        ->where('order_code', 'like', $prefix . '%')
                        ->count() + 1;

                    $attributes['order_code'] = $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

                    return CardOrder::create($attributes);
                });
            } catch (QueryException $e) {
                if ($e->getCode() === '23000'
                    && str_contains($e->getMessage(), 'order_code')
                    && $attempt < 4) {
                    continue;
                }
                throw $e;
            }
        }

        // Exhausted retries — collision storm; fall back to a random suffix.
        $attributes['order_code'] = $prefix . strtoupper(\Illuminate\Support\Str::random(5));

        return CardOrder::create($attributes);
    }

    /**
     * IDs of students currently sitting in a non-terminal order.
     */
    private function studentsInOpenOrders(): array
    {
        $ids = [];

        CardOrder::whereIn('status', CardOrder::OPEN_STATUSES)
            ->pluck('student_ids')
            ->each(function ($arr) use (&$ids) {
                foreach ((array) $arr as $id) {
                    $ids[$id] = true;
                }
            });

        return array_keys($ids);
    }

    private function activeClasses()
    {
        return SchoolClass::where('is_active', true)
            ->orderBy('level_order')
            ->orderBy('grade')
            ->get();
    }
}
