<?php

namespace App\Support;

/** Arabic keyboards often type ٠١٢٣ or ۰۱۲۳; numbers are stored with 0123. */
final class Digits
{
    public static function latin(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    /** A number typed in a form: "١٬٢٥٠٫٥" or "1,250.5 " → "1250.5". */
    public static function decimal(mixed $value): string
    {
        $text = self::latin(trim((string) $value));

        return str_replace([',', '٬', ' '], '', str_replace('٫', '.', $text));
    }
}
