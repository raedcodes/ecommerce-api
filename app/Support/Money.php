<?php

namespace App\Support;

/**
 * Money is stored as integer cents; this converts at the API boundary only.
 */
final class Money
{
    /**
     * Format cents as a decimal string, e.g. 14999 => "149.99".
     */
    public static function format(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    /**
     * Convert a validated decimal amount (at most 2 decimal places) to cents.
     */
    public static function toCents(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
