<?php

if (! function_exists('setting')) {
    /** Store setting with its default applied, e.g. setting('delivery_fee'). */
    function setting(string $key): mixed
    {
        return \App\Models\Setting::value($key);
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount in Bangladeshi Taka, e.g. ৳1,249 or ৳99.50.
     * Pass $symbol = 'Tk ' for PDFs, whose fonts have no ৳ glyph.
     */
    function money(float|int|string|null $amount, string $symbol = '৳'): string
    {
        $amount = (float) $amount;
        $decimals = fmod(round($amount, 2), 1.0) === 0.0 ? 0 : 2;

        return $symbol . number_format($amount, $decimals);
    }
}
