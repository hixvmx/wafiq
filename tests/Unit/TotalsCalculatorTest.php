<?php

namespace Tests\Unit;

use App\Services\TotalsCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Same cases as tests/js/totals.test.js (tests/fixtures/totals.json). */
class TotalsCalculatorTest extends TestCase
{
    /** @return iterable<string, array{array, array}> */
    public static function cases(): iterable
    {
        foreach (json_decode(file_get_contents(__DIR__.'/../fixtures/totals.json'), true) as $case) {
            yield $case['name'] => [$case['input'], $case['expected']];
        }
    }

    #[DataProvider('cases')]
    public function test_fixture(array $input, array $expected): void
    {
        $result = (new TotalsCalculator)->calculate($input['lines'], $input['discount']);

        $this->assertSame(self::strings($expected), self::strings($result));
    }

    /** Compare as strings: the fixture keeps huge values as strings for JavaScript's sake. */
    private static function strings(array $values): array
    {
        array_walk_recursive($values, function (&$value) {
            $value = (string) $value;
        });

        return $values;
    }
}
