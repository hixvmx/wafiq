<?php

namespace App\Services;

use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Document totals, in integer minor units. The browser mirrors this exact algorithm in
 * resources/js/lib/totals.js; tests/fixtures/totals.json is checked against both.
 *
 * Per line:  gross = qty × unit price · line discount = gross × % · net = gross − line discount
 * Document:  subtotal = Σ net · document discount (percent or fixed, capped at the subtotal)
 *            is spread over the lines in proportion to their net (largest remainder, so it adds up exactly)
 * Tax:       per line, on (net − its share of the document discount)
 * Total:     subtotal − document discount + Σ tax
 *
 * Rounding: half up, at each step above. All maths is exact (no floats, no overflow).
 */
class TotalsCalculator
{
    /** Decimals accepted for quantities, discount % and tax %. */
    public const SCALE = 3;

    /**
     * @param  list<array{qty: string, unit_price_minor: int, discount: string, tax_rate: string}>  $lines
     * @param  array{type: 'percent'|'amount', value: string|int}  $discount  percent as a decimal string, or an amount in minor units
     * @return array{lines: list<array<string, int>>, subtotal: int, discount: int, taxable: int, tax: int, total: int}
     */
    public function calculate(array $lines, array $discount): array
    {
        $computed = [];
        $subtotal = BigInteger::zero();

        foreach ($lines as $line) {
            $gross = $this->mulDiv(self::scaled($line['qty']), BigInteger::of($line['unit_price_minor']), 1000);
            $lineDiscount = $this->mulDiv($gross, self::scaled($line['discount']), 100000);
            $net = $gross->minus($lineDiscount);

            $computed[] = ['gross' => $gross, 'discount' => $lineDiscount, 'net' => $net, 'rate' => self::scaled($line['tax_rate'])];
            $subtotal = $subtotal->plus($net);
        }

        $documentDiscount = $discount['type'] === 'amount'
            ? BigInteger::min(BigInteger::of($discount['value']), $subtotal)
            : $this->mulDiv($subtotal, self::scaled((string) $discount['value']), 100000);

        $shares = $this->spread($documentDiscount, array_column($computed, 'net'), $subtotal);

        $result = ['lines' => []];
        $tax = BigInteger::zero();

        foreach ($computed as $i => $line) {
            $taxable = $line['net']->minus($shares[$i]);
            $lineTax = $this->mulDiv($taxable, $line['rate'], 100000);
            $tax = $tax->plus($lineTax);

            $result['lines'][] = [
                'gross' => $line['gross']->toInt(),
                'discount' => $line['discount']->toInt(),
                'net' => $line['net']->toInt(),
                'discount_share' => $shares[$i]->toInt(),
                'taxable' => $taxable->toInt(),
                'tax' => $lineTax->toInt(),
            ];
        }

        $taxableTotal = $subtotal->minus($documentDiscount);

        return [
            ...$result,
            'subtotal' => $subtotal->toInt(),
            'discount' => $documentDiscount->toInt(),
            'taxable' => $taxableTotal->toInt(),
            'tax' => $tax->toInt(),
            'total' => $taxableTotal->plus($tax)->toInt(),
        ];
    }

    /** "2.5" → 2500 (three decimals). Rejects anything that isn't a plain non-negative decimal. */
    public static function scaled(string $decimal): BigInteger
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', trim($decimal), $m)) {
            throw new InvalidArgumentException("Invalid decimal: {$decimal}");
        }

        return BigInteger::of($m[1].str_pad($m[2] ?? '', self::SCALE, '0'));
    }

    /** round(a × b ÷ divisor), half up. */
    private function mulDiv(BigInteger $a, BigInteger $b, int $divisor): BigInteger
    {
        return $a->multipliedBy($b)->dividedBy($divisor, RoundingMode::HalfUp);
    }

    /**
     * Splits $amount over $weights proportionally; the parts always add up to $amount.
     *
     * @param  list<BigInteger>  $weights
     * @return list<BigInteger>
     */
    private function spread(BigInteger $amount, array $weights, BigInteger $total): array
    {
        if ($amount->isZero() || $total->isZero()) {
            return array_fill(0, count($weights), BigInteger::zero());
        }

        $shares = [];
        $remainders = [];
        $given = BigInteger::zero();

        foreach ($weights as $i => $weight) {
            [$share, $remainder] = $amount->multipliedBy($weight)->quotientAndRemainder($total);
            $shares[$i] = $share;
            $remainders[$i] = $remainder;
            $given = $given->plus($share);
        }

        // Hand out what's left, one unit each, to the largest remainders (earlier lines win ties).
        $order = array_keys($weights);
        usort($order, fn (int $a, int $b) => $remainders[$b]->compareTo($remainders[$a]) ?: $a <=> $b);

        $left = $amount->minus($given)->toInt();
        foreach (array_slice($order, 0, $left) as $i) {
            $shares[$i] = $shares[$i]->plus(1);
        }

        return $shares;
    }
}
