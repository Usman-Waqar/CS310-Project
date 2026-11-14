<?php

namespace App\Support;

class Money
{
    public static function normalize(string|int|float $amount): string
    {
        $sanitized = str_replace(',', '.', trim((string) $amount));

        return bcadd($sanitized, '0', 2);
    }

    public static function add(string|int|float $left, string|int|float $right): string
    {
        return bcadd(self::normalize($left), self::normalize($right), 2);
    }

    public static function sub(string|int|float $left, string|int|float $right): string
    {
        return bcsub(self::normalize($left), self::normalize($right), 2);
    }

    public static function comp(string|int|float $left, string|int|float $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), 2);
    }

    /**
     * Format a decimal amount in Turkish standard (e.g. "₺1.250,00" or "-₺21,50").
     */
    public static function formatTry(string|int|float $amount, string $sign = ''): string
    {
        $normalized = self::normalize($amount);
        $isNegative = str_starts_with($normalized, '-');
        $unsigned = $isNegative ? substr($normalized, 1) : $normalized;

        [$integerPart, $fractionPart] = explode('.', $unsigned);
        $formattedInteger = strrev(implode('.', str_split(strrev($integerPart), 3)));
        $effectiveSign = $sign !== '' ? $sign : ($isNegative ? '-' : '');

        return $effectiveSign.'₺'.$formattedInteger.','.$fractionPart;
    }
}
