<?php

namespace App\Services;

use App\Support\Money;
use InvalidArgumentException;

/**
 * التفقيط: 1250000 halalas (SAR) → «فقط اثنا عشر ألفاً وخمسمئة ريال سعودي لا غير».
 *
 * Grammar used (standard invoice Arabic):
 * - the counted noun's form follows the last two digits: 1 → "ريال سعودي واحد", 2 → dual,
 *   3–10 → plural, 11–99 → accusative singular, 0 / 1 / 2 after hundreds → singular.
 * - numbers 3–10 take the opposite gender of the noun (ثلاثة ريالات / ثلاث هللات).
 * - thousand / million / billion are masculine and follow the same rules.
 * Supports amounts below one trillion.
 */
class ArabicAmountInWords
{
    /**
     * Forms per noun: [singular, accusative (11–99), dual, plural, feminine?]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool}>
     */
    private const NOUNS = [
        'riyal' => ['ريال', 'ريالاً', 'ريالان', 'ريالات', false],
        'dirham' => ['درهم', 'درهماً', 'درهمان', 'دراهم', false],
        'dinar' => ['دينار', 'ديناراً', 'ديناران', 'دنانير', false],
        'pound' => ['جنيه', 'جنيهاً', 'جنيهان', 'جنيهات', false],
        'dollar' => ['دولار', 'دولاراً', 'دولاران', 'دولارات', false],
        'euro' => ['يورو', 'يورو', 'يورو', 'يورو', false],
        'halala' => ['هللة', 'هللة', 'هللتان', 'هللات', true],
        'fils' => ['فلس', 'فلساً', 'فلسان', 'فلوس', false],
        'baisa' => ['بيسة', 'بيسة', 'بيستان', 'بيسات', true],
        'piastre' => ['قرش', 'قرشاً', 'قرشان', 'قروش', false],
        'centime' => ['سنتيم', 'سنتيماً', 'سنتيمان', 'سنتيمات', false],
        'millime' => ['مليم', 'مليماً', 'مليمان', 'مليمات', false],
        'cent' => ['سنت', 'سنتاً', 'سنتان', 'سنتات', false],
    ];

    /** Nationality adjective forms: [singular, accusative, dual, plural (agrees with a non-human plural: feminine singular)] */
    private const ADJECTIVES = [
        'SAR' => ['سعودي', 'سعودياً', 'سعوديان', 'سعودية'],
        'AED' => ['إماراتي', 'إماراتياً', 'إماراتيان', 'إماراتية'],
        'KWD' => ['كويتي', 'كويتياً', 'كويتيان', 'كويتية'],
        'QAR' => ['قطري', 'قطرياً', 'قطريان', 'قطرية'],
        'BHD' => ['بحريني', 'بحرينياً', 'بحرينيان', 'بحرينية'],
        'OMR' => ['عماني', 'عمانياً', 'عمانيان', 'عمانية'],
        'EGP' => ['مصري', 'مصرياً', 'مصريان', 'مصرية'],
        'JOD' => ['أردني', 'أردنياً', 'أردنيان', 'أردنية'],
        'IQD' => ['عراقي', 'عراقياً', 'عراقيان', 'عراقية'],
        'LYD' => ['ليبي', 'ليبياً', 'ليبيان', 'ليبية'],
        'MAD' => ['مغربي', 'مغربياً', 'مغربيان', 'مغربية'],
        'DZD' => ['جزائري', 'جزائرياً', 'جزائريان', 'جزائرية'],
        'TND' => ['تونسي', 'تونسياً', 'تونسيان', 'تونسية'],
        'USD' => ['أمريكي', 'أمريكياً', 'أمريكيان', 'أمريكية'],
    ];

    /** Currency => [main noun, sub-unit noun] */
    private const CURRENCIES = [
        'SAR' => ['riyal', 'halala'], 'AED' => ['dirham', 'fils'], 'KWD' => ['dinar', 'fils'],
        'QAR' => ['riyal', 'dirham'], 'BHD' => ['dinar', 'fils'], 'OMR' => ['riyal', 'baisa'],
        'EGP' => ['pound', 'piastre'], 'JOD' => ['dinar', 'fils'], 'IQD' => ['dinar', 'fils'],
        'LYD' => ['dinar', 'dirham'], 'MAD' => ['dirham', 'centime'], 'DZD' => ['dinar', 'centime'],
        'TND' => ['dinar', 'millime'], 'USD' => ['dollar', 'cent'], 'EUR' => ['euro', 'cent'],
    ];

    private const ONES_MASC = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة', 'عشرة'];

    private const ONES_FEM = ['', 'واحدة', 'اثنتان', 'ثلاث', 'أربع', 'خمس', 'ست', 'سبع', 'ثماني', 'تسع', 'عشر'];

    private const TENS = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];

    private const HUNDREDS = ['', 'مئة', 'مئتان', 'ثلاثمئة', 'أربعمئة', 'خمسمئة', 'ستمئة', 'سبعمئة', 'ثمانمئة', 'تسعمئة'];

    /** [singular, accusative, dual, plural] for thousand, million, billion (all masculine). */
    private const SCALES = [
        1_000_000_000 => ['مليار', 'ملياراً', 'ملياران', 'مليارات'],
        1_000_000 => ['مليون', 'مليوناً', 'مليونان', 'ملايين'],
        1_000 => ['ألف', 'ألفاً', 'ألفان', 'آلاف'],
    ];

    /** «فقط … لا غير» for an amount in minor units. */
    public function convert(int $minor, string $currency): string
    {
        [$mainKey, $subKey] = self::CURRENCIES[$currency] ?? throw new InvalidArgumentException("No Arabic words for {$currency}");

        if ($minor < 0 || $minor >= 1_000_000_000_000 * 10 ** Money::decimals($currency)) {
            throw new InvalidArgumentException("Amount out of range: {$minor}");
        }

        $factor = 10 ** Money::decimals($currency);
        $main = intdiv($minor, $factor);
        $sub = $minor % $factor;

        $parts = [];
        if ($main > 0 || $sub === 0) {
            $parts[] = $main === 0
                ? 'صفر '.$this->noun(self::NOUNS[$mainKey], self::ADJECTIVES[$currency] ?? null, 0)
                : $this->counted($main, self::NOUNS[$mainKey], self::ADJECTIVES[$currency] ?? null);
        }
        if ($sub > 0) {
            $parts[] = $this->counted($sub, self::NOUNS[$subKey], null);
        }

        return 'فقط '.implode(' و', $parts).' لا غير';
    }

    /** "ثلاثة ريالات سعودية", "ريال سعودي واحد", "ريالان سعوديان", "اثنا عشر ريالاً سعودياً"… */
    private function counted(int $n, array $noun, ?array $adjective): string
    {
        $feminine = $noun[4];

        if ($n === 1) {
            return trim($this->noun($noun, $adjective, 1).' '.($feminine ? 'واحدة' : 'واحد'));
        }
        if ($n === 2) {
            return $this->noun($noun, $adjective, 2);
        }

        return $this->number($n, $feminine).' '.$this->noun($noun, $adjective, $n);
    }

    /** The noun (and adjective) in the form a count of $n requires. */
    private function noun(array $noun, ?array $adjective, int $n): string
    {
        $form = self::form($n);

        return trim($noun[$form].' '.($adjective[$form] ?? ''));
    }

    /** 0 singular · 1 accusative · 2 dual · 3 plural */
    private static function form(int $n): int
    {
        $lastTwo = $n % 100;

        return match (true) {
            $n === 2 => 2,
            $lastTwo >= 3 && $lastTwo <= 10 => 3,
            $lastTwo >= 11 => 1,
            default => 0, // 0, 1, or 1–2 after hundreds/thousands
        };
    }

    /** Number words below one trillion; $feminine is the gender of the counted noun. */
    private function number(int $n, bool $feminine): string
    {
        $parts = [];

        foreach (self::SCALES as $size => $forms) {
            $count = intdiv($n, $size);
            $n %= $size;

            if ($count === 0) {
                continue;
            }

            $parts[] = match (true) {
                $count === 1 => $forms[0],
                $count === 2 => $forms[2],
                default => $this->belowThousand($count, false).' '.$forms[self::form($count)],
            };
        }

        if ($n > 0) {
            $parts[] = $this->belowThousand($n, $feminine);
        }

        return implode(' و', $parts);
    }

    private function belowThousand(int $n, bool $feminine): string
    {
        $parts = [];

        if ($n >= 100) {
            $parts[] = self::HUNDREDS[intdiv($n, 100)];
            $n %= 100;
        }

        if ($n > 0) {
            $parts[] = $this->belowHundred($n, $feminine);
        }

        return implode(' و', $parts);
    }

    private function belowHundred(int $n, bool $feminine): string
    {
        // ONES_MASC goes with masculine nouns: 1–2 agree (واحد، اثنان), 3–10 take the
        // opposite form (ثلاثة ريالات). ONES_FEM likewise for feminine nouns (ثلاث هللات).
        $ones = fn (int $d) => ($feminine ? self::ONES_FEM : self::ONES_MASC)[$d];

        if ($n <= 10) {
            return $ones($n);
        }

        if ($n < 20) {
            $unit = $n - 10;

            return match ($unit) {
                1 => $feminine ? 'إحدى عشرة' : 'أحد عشر',
                2 => $feminine ? 'اثنتا عشرة' : 'اثنا عشر',
                default => $ones($unit).' '.($feminine ? 'عشرة' : 'عشر'),
            };
        }

        $tens = self::TENS[intdiv($n, 10)];
        $unit = $n % 10;

        if ($unit === 0) {
            return $tens;
        }

        $unitWord = $unit === 1 ? ($feminine ? 'إحدى' : 'واحد') : $ones($unit);

        return $unitWord.' و'.$tens;
    }
}
