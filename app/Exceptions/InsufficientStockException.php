<?php

namespace App\Exceptions;

class InsufficientStockException extends ShopException
{
    /**
     * @param  array<int, array{name: string, requested: int, available: int}>  $shortages  keyed by product id
     */
    public function __construct(public readonly array $shortages)
    {
        $parts = array_map(
            fn ($s) => $s['available'] > 0
                ? "{$s['name']} (only {$s['available']} left)"
                : "{$s['name']} (out of stock)",
            $shortages
        );

        parent::__construct('Not enough stock for ' . implode(', ', $parts) . '.');
    }
}
