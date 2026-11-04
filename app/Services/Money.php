<?php

namespace App\Services;

final class Money
{
    public static function toCents(int|float|string|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $normalized = str_replace(',', '.', (string) $amount);
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = substr(str_pad($fraction, 3, '0'), 0, 3);
        $cents = ((int) $whole * 100) + (int) substr($fraction, 0, 2);

        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $negative, intdiv($absolute, 100), $absolute % 100);
    }

    public static function quantityToMillis(int|float|string $quantity): int
    {
        $normalized = str_replace(',', '.', (string) $quantity);
        [$whole, $fraction] = array_pad(explode('.', $normalized, 2), 2, '');
        $fraction = substr(str_pad($fraction, 3, '0'), 0, 3);

        return ((int) $whole * 1000) + (int) $fraction;
    }

    public static function lineTotal(int $unitPriceCents, int $quantityMillis): int
    {
        return (int) round(($unitPriceCents * $quantityMillis) / 1000);
    }

    public static function tax(int $taxableCents, int|float|string|null $rate): int
    {
        $basisPoints = (int) round(((float) ($rate ?? 0)) * 100);

        return (int) round(($taxableCents * $basisPoints) / 10000);
    }
}
