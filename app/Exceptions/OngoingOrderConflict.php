<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown from inside the card-order transaction when one or more selected
 * students already sit in a non-terminal order. Raised under a lock on the
 * school row so the conflict check and the insert are atomic — two concurrent
 * submits for the same student can never both create an order.
 */
class OngoingOrderConflict extends RuntimeException
{
    /** @param int[] $studentIds the conflicting student ids */
    public function __construct(public array $studentIds)
    {
        parent::__construct('One or more students already have an ongoing card order.');
    }
}
