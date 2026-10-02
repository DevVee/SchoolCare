<?php

namespace App\Services\Coco;

/**
 * An action the assistant asked for cannot be prepared or done, with a short
 * reason in plain words. The reason goes back to the model (when proposing)
 * or to the card (when confirming), so it must never contain secrets.
 */
class CocoRefusal extends \RuntimeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
