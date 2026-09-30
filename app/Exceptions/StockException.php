<?php

namespace App\Exceptions;

/**
 * A stock problem tied to one form field (e.g. "medicines.2.quantity"),
 * so the controller can show the message next to the right input.
 */
class StockException extends \RuntimeException
{
    public function __construct(public readonly string $field, string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
