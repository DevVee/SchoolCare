<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a record is asked to move to a status its state machine
 * does not allow (e.g. approving a cancelled appointment).
 */
class InvalidStatusTransition extends RuntimeException
{
    public static function for(string $label, string $from, string $to): self
    {
        $from = str_replace('_', ' ', $from);
        $to   = str_replace('_', ' ', $to);

        return new self("This {$label} is {$from} and cannot be changed to {$to}.");
    }
}
