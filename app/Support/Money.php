<?php

namespace App\Support;

/**
 * Money arithmetic in whole paisa (1/100 taka) to avoid floating point drift.
 * Amounts come in as decimal strings / numbers and are stored as "123.45".
 */
final class Money
{
    public static function toPaisa(float|int|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        // Go through a fixed 2-decimal string so 0.1 + 0.2 style artefacts never leak in.
        return (int) round((float) number_format((float) $amount, 2, '.', '') * 100);
    }

    public static function fromPaisa(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';
        $paisa = abs($paisa);

        return $sign . intdiv($paisa, 100) . '.' . str_pad((string) ($paisa % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Percentage of an amount, rounded half-up to the paisa. */
    public static function percentOf(int $paisa, float|int|string $percent): int
    {
        return (int) round($paisa * (float) $percent / 100);
    }
}
