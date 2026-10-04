<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\DocumentStatus;
use App\Models\Activity;
use App\Models\Document;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\NumberSequence;
use App\Services\TotalsCalculator;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a draft from validated form data. Totals are always calculated here,
 * on the server; whatever the browser showed is ignored.
 */
class SaveDocument
{
    public function __construct(
        private NumberSequence $numbers,
        private TotalsCalculator $totals,
    ) {}

    /**
     * @param  array{client_id: int, currency: string, issue_date: string, valid_until?: ?string, due_date?: ?string,
     *     discount_type: string, discount_value: ?string, notes: ?string, terms: ?string,
     *     lines: list<array{item_id?: ?int, name: string, description?: ?string, qty: string, unit?: ?string, unit_price: string, discount?: ?string, tax_rate_id?: ?int}>}  $data
     */
    public function handle(?Document $document, string $type, array $data, User $user): Document
    {
        return DB::transaction(function () use ($document, $type, $data, $user) {
            if (! $document) {
                // Reserved inside this transaction: two people saving at once never share a number.
                $document = Document::create([
                    'type' => $type,
                    'number' => $this->numbers->reserve($type),
                    'status' => DocumentStatus::Draft,
                    'created_by' => $user->id,
                    ...$this->header($type, $data),
                ]);
                $document->update(['root_id' => $document->id]);
                Activity::log($document, ActivityType::Created, [], $user);
            } else {
                $document->update($this->header($type, $data));
                Activity::log($document, ActivityType::Updated, [], $user);
            }

            $this->saveLines($document, $data['lines']);

            return $document;
        });
    }

    /** @return array<string, mixed> */
    private function header(string $type, array $data): array
    {
        return [
            'client_id' => $data['client_id'],
            'currency' => $data['currency'],
            'issue_date' => $data['issue_date'],
            'valid_until' => $type === 'quote' ? ($data['valid_until'] ?? null) : null,
            'due_date' => $type === 'invoice' ? ($data['due_date'] ?? null) : null,
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'] ?: '0',
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? null,
        ];
    }

    /** Replaces the lines and stores the calculated amounts on lines and document. */
    private function saveLines(Document $document, array $lines): void
    {
        $currency = $document->currency;
        $rates = TaxRate::whereIn('id', array_filter(array_column($lines, 'tax_rate_id')))->get()->keyBy('id');

        $prepared = array_map(function (array $line, int $i) use ($currency, $rates) {
            $rate = isset($line['tax_rate_id']) ? $rates->get($line['tax_rate_id']) : null;

            return [
                'item_id' => $line['item_id'] ?? null,
                'position' => $i + 1,
                'name' => $line['name'],
                'description' => $line['description'] ?? null,
                'qty' => $line['qty'],
                'unit' => $line['unit'] ?? null,
                'unit_price_minor' => Money::toMinor($line['unit_price'], $currency),
                'discount_percent' => ($line['discount'] ?? '') === '' ? '0' : $line['discount'],
                'tax_rate_id' => $rate?->id,
                'tax_name' => $rate?->name,
                'tax_rate' => $rate ? (string) $rate->rate : '0',
            ];
        }, $lines, array_keys($lines));

        $result = $this->totals->calculate(
            array_map(fn (array $line) => [
                'qty' => $line['qty'],
                'unit_price_minor' => $line['unit_price_minor'],
                'discount' => $line['discount_percent'],
                'tax_rate' => self::plainDecimal($line['tax_rate']),
            ], $prepared),
            $document->discount_type === 'amount'
                ? ['type' => 'amount', 'value' => Money::toMinor(self::plainDecimal($document->discount_value), $currency)]
                : ['type' => 'percent', 'value' => self::plainDecimal($document->discount_value)],
        );

        $document->lines()->delete();

        foreach ($prepared as $i => $line) {
            $amounts = $result['lines'][$i];
            $document->lines()->create([
                ...$line,
                'gross_minor' => $amounts['gross'],
                'discount_minor' => $amounts['discount'],
                'net_minor' => $amounts['net'],
                'discount_share_minor' => $amounts['discount_share'],
                'tax_minor' => $amounts['tax'],
            ]);
        }

        $document->update([
            'subtotal_minor' => $result['subtotal'],
            'discount_minor' => $result['discount'],
            'tax_minor' => $result['tax'],
            'total_minor' => $result['total'],
        ]);
    }

    /** Database decimals without trailing zeros: "150.000" → "150", "15.500" → "15.5", 15.0 → "15". */
    public static function plainDecimal(string|float|int $value): string
    {
        $text = is_float($value) ? number_format($value, 3, '.', '') : trim((string) $value);

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
