<?php

namespace App\Support;

/**
 * Phone numbers are stored in international format (+9665xxxxxxxx), which is what
 * WhatsApp click-to-chat (wa.me) needs. The form sends a country code + local number.
 */
final class Phone
{
    /** Country calling codes offered in the form (Arab countries first, then common others). */
    public const CODES = [
        'SA' => '966', 'AE' => '971', 'KW' => '965', 'QA' => '974', 'BH' => '973', 'OM' => '968',
        'EG' => '20', 'JO' => '962', 'IQ' => '964', 'LB' => '961', 'PS' => '970', 'SY' => '963',
        'YE' => '967', 'SD' => '249', 'LY' => '218', 'TN' => '216', 'DZ' => '213', 'MA' => '212',
        'MR' => '222', 'TR' => '90', 'GB' => '44', 'FR' => '33', 'DE' => '49', 'US' => '1',
    ];

    /**
     * "966" + "050 123 4567" → "+966501234567". A number typed with its own
     * country code (+971…, 00971…) keeps it. Returns null when it can't be a phone number.
     */
    public static function normalize(?string $code, ?string $number): ?string
    {
        $number = Digits::latin(trim((string) $number));

        if ($number === '') {
            return null;
        }

        $international = str_starts_with($number, '+') || str_starts_with($number, '00');
        $digits = preg_replace('/\D/', '', $number);

        if ($international) {
            $digits = str_starts_with($number, '00') ? substr($digits, 2) : $digits;
        } else {
            $code = preg_replace('/\D/', '', (string) $code);
            if ($code === '') {
                return null;
            }
            $digits = $code.ltrim($digits, '0'); // local numbers drop their trunk 0
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+'.$digits : null;
    }

    /** "+966501234567" → ['966', '501234567'] for the edit form. */
    public static function split(?string $phone): array
    {
        if (! $phone) {
            return [null, ''];
        }

        $digits = ltrim($phone, '+');
        // Longest codes first, so "+9715…" isn't read as Russia/"+7".
        $codes = array_unique(self::CODES);
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (str_starts_with($digits, $code)) {
                return [$code, substr($digits, strlen($code))];
            }
        }

        return [null, $phone];
    }
}
