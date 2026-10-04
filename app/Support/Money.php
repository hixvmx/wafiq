<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Amounts are integers in minor units (halalas, fils…), never floats.
 * Conversion works on the decimal string, so "0.1 + 0.2" problems can't happen.
 * The number of decimals per currency is in config/currencies.php.
 */
final class Money
{
    public static function decimals(string $currency): int
    {
        return config("currencies.{$currency}") ?? throw new InvalidArgumentException("Unknown currency {$currency}");
    }

    /** "1250.5" SAR → 125050. Accepts Arabic-Indic digits and thousands separators. */
    public static function toMinor(string|int|float $amount, string $currency): int
    {
        $decimals = self::decimals($currency);
        $text = str_replace([',', '٬', ' '], '', Digits::latin(trim((string) $amount)));
        $text = str_replace('٫', '.', $text);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $text, $m)) {
            throw new InvalidArgumentException("Not an amount: {$amount}");
        }

        $fraction = $m[3] ?? '';
        if (strlen($fraction) > $decimals) {
            throw new InvalidArgumentException("Too many decimals for {$currency}: {$amount}");
        }

        $minor = (int) ($m[2].str_pad($fraction, $decimals, '0'));

        return $m[1] === '-' ? -$minor : $minor;
    }

    /** 125050 SAR → "1250.50" (plain decimal string, for forms and JSON). */
    public static function fromMinor(int $minor, string $currency): string
    {
        $decimals = self::decimals($currency);
        $sign = $minor < 0 ? '-' : '';
        $digits = str_pad((string) abs($minor), $decimals + 1, '0', STR_PAD_LEFT);

        return $decimals === 0
            ? $sign.$digits
            : $sign.substr($digits, 0, -$decimals).'.'.substr($digits, -$decimals);
    }

    /** Validation regex for an amount typed in a form, for a given currency. */
    public static function pattern(string $currency): string
    {
        $decimals = self::decimals($currency);

        return $decimals === 0 ? '/^\d{1,12}$/' : '/^\d{1,12}(\.\d{1,'.$decimals.'})?$/';
    }
}
