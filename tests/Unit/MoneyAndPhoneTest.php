<?php

namespace Tests\Unit;

use App\Support\Money;
use App\Support\Phone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MoneyAndPhoneTest extends TestCase
{
    /** @return iterable<array{string, string, int}> */
    public static function amounts(): iterable
    {
        yield ['1250.5', 'SAR', 125050];
        yield ['1,250.50', 'SAR', 125050];
        yield ['١٢٥٠٫٥', 'SAR', 125050];
        yield ['0.1', 'SAR', 10];
        yield ['12.345', 'KWD', 12345];
        yield ['7', 'KWD', 7000];
        yield ['-3.5', 'AED', -350];
    }

    #[DataProvider('amounts')]
    public function test_to_minor(string $amount, string $currency, int $minor): void
    {
        $this->assertSame($minor, Money::toMinor($amount, $currency));
    }

    public function test_from_minor(): void
    {
        $this->assertSame('1250.50', Money::fromMinor(125050, 'SAR'));
        $this->assertSame('0.05', Money::fromMinor(5, 'SAR'));
        $this->assertSame('12.345', Money::fromMinor(12345, 'KWD'));
        $this->assertSame('-3.50', Money::fromMinor(-350, 'AED'));
    }

    public function test_too_many_decimals_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::toMinor('1.005', 'SAR');
    }

    /** @return iterable<array{?string, string, ?string}> */
    public static function phones(): iterable
    {
        yield 'saudi local' => ['966', '050 123 4567', '+966501234567'];
        yield 'arabic digits' => ['966', '٠٥٠١٢٣٤٥٦٧', '+966501234567'];
        yield 'own country code wins' => ['966', '+971 50 123 4567', '+971501234567'];
        yield '00 prefix' => ['966', '0020 100 123 4567', '+201001234567'];
        yield 'egypt local' => ['20', '01001234567', '+201001234567'];
        yield 'too short' => ['966', '123', null];
        yield 'letters only' => ['966', 'abc', null];
        yield 'empty' => ['966', '', null];
    }

    #[DataProvider('phones')]
    public function test_phone_normalize(?string $code, string $number, ?string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($code, $number));
    }

    public function test_phone_split_prefers_the_longest_code(): void
    {
        $this->assertSame(['971', '501234567'], Phone::split('+971501234567'));
        $this->assertSame(['20', '1001234567'], Phone::split('+201001234567'));
        $this->assertSame([null, ''], Phone::split(null));
    }
}
