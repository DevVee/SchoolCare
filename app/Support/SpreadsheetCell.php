<?php

namespace App\Support;

/**
 * Formula (CSV / spreadsheet) injection protection for exported cells.
 *
 * A text cell that starts with = + - @ (or a tab / carriage return) could be
 * run as a formula by Excel or Sheets. Such values are prefixed with a single
 * quote so they are shown as plain text. Numbers are left untouched.
 */
class SpreadsheetCell
{
    public static function safe(mixed $value): string|int|float|null
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $value = (string) $value;

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /** @param array<int,mixed> $row */
    public static function row(array $row): array
    {
        return array_map([self::class, 'safe'], $row);
    }
}
