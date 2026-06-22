<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown from inside the staff-creation transaction when a school has hit its
 * plan's teacher cap. Raising it inside the transaction keeps the limit check
 * and the insert atomic (under a row lock on the school).
 */
class StaffLimitReached extends RuntimeException
{
}
