<?php

namespace App\Services;

use App\Exceptions\SheetSlotException;
use App\Models\PrintSheet;
use App\Models\School;
use App\Models\SheetTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Manages the lifecycle and slot occupancy of physical PVC sheets.
 *
 * The cutting machine cannot handle randomly-used slots, so the software
 * enforces SEQUENTIAL filling: occupied slots must always form a contiguous
 * prefix (1..k), and new cards may only fill the next contiguous block starting
 * at slot k+1. This invariant lets a partial sheet be reopened and topped up
 * without wasting PVC.
 */
class SheetTrackingService
{
    /**
     * Open a fresh sheet for a template, pre-creating one empty slot per card
     * position so the wizard can render the grid immediately.
     */
    public function openSheet(SheetTemplate $template, ?School $school = null, ?User $user = null): PrintSheet
    {
        return DB::transaction(function () use ($template, $school, $user) {
            $sheet = PrintSheet::create([
                'sheet_template_id' => $template->id,
                'school_id'         => $school?->id,
                'created_by'        => $user?->id,
                'status'            => 'open',
            ]);

            $rows = [];
            $now = now();
            for ($slot = 1; $slot <= $template->slotCount(); $slot++) {
                $rows[] = [
                    'print_sheet_id' => $sheet->id,
                    'slot_number'    => $slot,
                    'status'         => 'empty',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];
            }
            $sheet->slots()->insert($rows);

            return $sheet->load('slots');
        });
    }

    /**
     * Validate that the requested slot numbers are exactly the next contiguous
     * block of empty slots (sequential-fill rule). Throws otherwise.
     *
     * @param  int[]  $slots
     */
    public function validateSequential(PrintSheet $sheet, array $slots): void
    {
        $slots = array_values(array_unique(array_map('intval', $slots)));
        sort($slots);

        if (empty($slots)) {
            throw new SheetSlotException('No slots were selected.');
        }

        $start = $sheet->nextEmptySlot();
        if ($start === null) {
            throw new SheetSlotException('This sheet is already full.');
        }

        $expected = range($start, $start + count($slots) - 1);

        if ($slots !== $expected) {
            throw new SheetSlotException(
                "Slots must be filled sequentially starting at slot {$start}. ".
                'Random or non-contiguous slots are not allowed.'
            );
        }
    }

    /**
     * Assign students to slots and mark them occupied. Runs under a row lock on
     * the sheet so two concurrent operators cannot both claim the same slots
     * (TOCTOU-safe, matching the capacity-write invariant).
     *
     * @param  array<int, array{slot: int, student_id: int}>  $items
     * @param  string  $side  'front' | 'back' | 'both'
     */
    public function assign(PrintSheet $sheet, array $items, string $side = 'both', ?int $cardOrderId = null): PrintSheet
    {
        return DB::transaction(function () use ($sheet, $items, $side, $cardOrderId) {
            // Lock the sheet row, then re-read slot state inside the lock.
            $locked = PrintSheet::whereKey($sheet->id)->lockForUpdate()->firstOrFail();

            $slots = array_column($items, 'slot');
            $this->validateSequential($locked, $slots);

            $byStudent = [];
            foreach ($items as $it) {
                $byStudent[(int) $it['slot']] = (int) $it['student_id'];
            }

            $now = now();
            foreach ($slots as $slotNumber) {
                $locked->slots()
                    ->where('slot_number', $slotNumber)
                    ->update([
                        'status'        => 'printed',
                        'student_id'    => $byStudent[$slotNumber],
                        'card_order_id' => $cardOrderId,
                        'printed_front' => in_array($side, ['front', 'both'], true),
                        'printed_back'  => in_array($side, ['back', 'both'], true),
                        'printed_at'    => $now,
                        'updated_at'    => $now,
                    ]);
            }

            // Auto-complete the sheet once every slot is filled.
            if ($locked->fresh()->isFull()) {
                $locked->update(['status' => 'completed']);
            }

            return $locked->load('slots');
        });
    }

    /**
     * Record that a given side (front/back) has been printed for already-occupied
     * slots — used by the duplex flow where front and back are separate passes
     * over the same physical sheet. Does not touch occupancy or the sequential
     * rule.
     *
     * @param  int[]  $slots
     */
    public function recordSidePrinted(PrintSheet $sheet, array $slots, string $side): void
    {
        $column = $side === 'back' ? 'printed_back' : 'printed_front';

        $sheet->slots()
            ->whereIn('slot_number', array_map('intval', $slots))
            ->where('status', 'printed')
            ->update([$column => true, 'printed_at' => now()]);
    }
}
