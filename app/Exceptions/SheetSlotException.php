<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a slot assignment violates the sequential-fill rule or targets a
 * sheet that cannot accept the request (full, or non-contiguous selection).
 */
class SheetSlotException extends RuntimeException {}
