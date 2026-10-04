<?php

namespace Tests\Unit;

use App\Services\ArabicAmountInWords;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArabicAmountInWordsTest extends TestCase
{
    /** @return iterable<string, array{int, string, string}> */
    public static function amounts(): iterable
    {
        // The example from idea-wafiq.md, section 10.
        yield '12,500 SAR' => [1250000, 'SAR', 'فقط اثنا عشر ألفاً وخمسمئة ريال سعودي لا غير'];

        yield '1 SAR' => [100, 'SAR', 'فقط ريال سعودي واحد لا غير'];
        yield '2 SAR' => [200, 'SAR', 'فقط ريالان سعوديان لا غير'];
        yield '3 SAR' => [300, 'SAR', 'فقط ثلاثة ريالات سعودية لا غير'];
        yield '10 SAR' => [1000, 'SAR', 'فقط عشرة ريالات سعودية لا غير'];
        yield '11 SAR' => [1100, 'SAR', 'فقط أحد عشر ريالاً سعودياً لا غير'];
        yield '15 SAR' => [1500, 'SAR', 'فقط خمسة عشر ريالاً سعودياً لا غير'];
        yield '21 SAR' => [2100, 'SAR', 'فقط واحد وعشرون ريالاً سعودياً لا غير'];
        yield '100 SAR' => [10000, 'SAR', 'فقط مئة ريال سعودي لا غير'];
        yield '103 SAR' => [10300, 'SAR', 'فقط مئة وثلاثة ريالات سعودية لا غير'];
        yield '250 SAR' => [25000, 'SAR', 'فقط مئتان وخمسون ريالاً سعودياً لا غير'];
        yield '1,000 SAR' => [100000, 'SAR', 'فقط ألف ريال سعودي لا غير'];
        yield '2,000 SAR' => [200000, 'SAR', 'فقط ألفان ريال سعودي لا غير'];
        yield '3,000 SAR' => [300000, 'SAR', 'فقط ثلاثة آلاف ريال سعودي لا غير'];
        yield '1,500,000 SAR' => [150000000, 'SAR', 'فقط مليون وخمسمئة ألف ريال سعودي لا غير'];

        // Feminine sub-unit: numbers 3–10 take the masculine form, 11–19 the feminine.
        yield '0.03 SAR' => [3, 'SAR', 'فقط ثلاث هللات لا غير'];
        yield '0.12 SAR' => [12, 'SAR', 'فقط اثنتا عشرة هللة لا غير'];
        yield '0.21 SAR' => [21, 'SAR', 'فقط إحدى وعشرون هللة لا غير'];
        yield '1,250.75 SAR' => [125075, 'SAR', 'فقط ألف ومئتان وخمسون ريالاً سعودياً وخمس وسبعون هللة لا غير'];

        // Three-decimal currency with a masculine sub-unit.
        yield '7.250 KWD' => [7250, 'KWD', 'فقط سبعة دنانير كويتية ومئتان وخمسون فلساً لا غير'];

        yield '0 SAR' => [0, 'SAR', 'فقط صفر ريال سعودي لا غير'];
        yield '45 EUR' => [4500, 'EUR', 'فقط خمسة وأربعون يورو لا غير'];
    }

    #[DataProvider('amounts')]
    public function test_amount(int $minor, string $currency, string $words): void
    {
        $this->assertSame($words, (new ArabicAmountInWords)->convert($minor, $currency));
    }

    public function test_every_configured_currency_is_supported(): void
    {
        foreach (array_keys(config('currencies')) as $currency) {
            $this->assertStringStartsWith('فقط ', (new ArabicAmountInWords)->convert(123456, $currency), $currency);
        }
    }
}
