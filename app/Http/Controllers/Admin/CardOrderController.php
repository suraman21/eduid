<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CardOrder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Super-admin processing of card orders. The admin enters the confirmation
 * code a school shared, reviews it, confirms it, then walks the order through
 * the fulfilment lifecycle (confirmed → printing → shipped → delivered).
 *
 * CardOrder uses the BelongsToSchool scope, but a super_admin has no school_id
 * so the scope is a no-op for this role — these queries see every school.
 */
class CardOrderController extends Controller
{
    /** Forward-only fulfilment transitions an admin may apply. */
    private const TRANSITIONS = [
        'confirmed' => 'printing',
        'printing'  => 'shipped',
        'shipped'   => 'delivered',
    ];

    public function index(Request $request)
    {
        $query = CardOrder::with(['school', 'orderedBy']);

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($code = $request->get('code')) {
            $query->where('order_code', 'like', "%{$code}%");
        }

        $orders = $query->orderByDesc('created_at')->paginate(20);

        return view('admin.card_orders.index', compact('orders'));
    }

    /**
     * Enter-a-code screen. If a code is supplied, resolve and show it.
     */
    public function process(Request $request)
    {
        $order = null;
        $code  = trim((string) $request->get('code', ''));

        if ($code !== '') {
            $order = CardOrder::with(['school', 'orderedBy'])
                ->where('order_code', $code)
                ->first();
        }

        $students = $order ? $order->getStudents()->load('schoolClass') : collect();

        return view('admin.card_orders.process', [
            'code'     => $code,
            'order'    => $order,
            'students' => $students,
        ]);
    }

    public function show(CardOrder $cardOrder)
    {
        $cardOrder->load(['school', 'orderedBy']);
        $students = $cardOrder->getStudents()->load('schoolClass');

        return view('admin.card_orders.show', [
            'order'    => $cardOrder,
            'students' => $students,
        ]);
    }

    /**
     * Confirm a pending order. Note: high-resolution print-file generation
     * (TCPDF, server-side only) is a separate deliverable; this records the
     * confirmation and advances the lifecycle.
     */
    public function confirm(CardOrder $cardOrder)
    {
        if (! $cardOrder->isPending()) {
            return back()->with('error', 'Only pending orders can be confirmed.');
        }

        $cardOrder->update([
            'status'       => 'confirmed',
            'confirmed_at' => now(),
        ]);

        AuditLog::createRecord(
            $cardOrder->school_id, auth()->id(),
            'card_order.confirmed', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code]
        );

        return back()->with('success', "Order {$cardOrder->order_code} confirmed.");
    }

    /**
     * Advance the order one step along the fulfilment lifecycle.
     */
    public function updateStatus(Request $request, CardOrder $cardOrder)
    {
        $validated = $request->validate([
            'status'        => ['required', Rule::in(array_values(self::TRANSITIONS))],
            'tracking_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $expected = self::TRANSITIONS[$cardOrder->status] ?? null;

        if ($validated['status'] !== $expected) {
            return back()->with('error', "Cannot move a {$cardOrder->status} order to {$validated['status']}.");
        }

        $timestampColumn = [
            'printing'  => 'printed_at',
            'shipped'   => 'shipped_at',
            'delivered' => 'delivered_at',
        ][$validated['status']];

        $cardOrder->update([
            'status'          => $validated['status'],
            $timestampColumn  => now(),
            'tracking_note'   => $validated['tracking_note'] ?? $cardOrder->tracking_note,
        ]);

        AuditLog::createRecord(
            $cardOrder->school_id, auth()->id(),
            'card_order.status', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code, 'status' => $validated['status']]
        );

        return back()->with('success', "Order marked {$validated['status']}.");
    }

    public function cancel(Request $request, CardOrder $cardOrder)
    {
        if (! $cardOrder->canBeCancelledBy(auth()->user())) {
            return back()->with('error', 'This order can no longer be cancelled.');
        }

        $validated = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $cardOrder->update([
            'status'        => 'cancelled',
            'cancelled_at'  => now(),
            'cancel_reason' => $validated['cancel_reason'] ?? 'Cancelled by Arkeon.',
        ]);

        AuditLog::createRecord(
            $cardOrder->school_id, auth()->id(),
            'card_order.cancelled', 'card_orders', $cardOrder->id, null,
            ['order_code' => $cardOrder->order_code]
        );

        return back()->with('success', "Order {$cardOrder->order_code} cancelled.");
    }
}
