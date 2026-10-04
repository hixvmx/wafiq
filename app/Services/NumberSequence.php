<?php

namespace App\Services;

use App\Models\NumberSequence as Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Document numbers per type: QT-2026-0042, INV-0107…
 * Formats come from the company settings (numbering.{type}); counters from number_sequences.
 */
class NumberSequence
{
    public function __construct(private CurrentCompany $current) {}

    /** The number the next document of this type will get (nothing is reserved). */
    public function peek(string $type): string
    {
        return $this->format($type, $this->counter($type)?->next_number ?? 1);
    }

    /** The counter value the next document will use. */
    public function nextNumber(string $type): int
    {
        return $this->counter($type)?->next_number ?? 1;
    }

    /**
     * Takes the next number for good. Call it inside the transaction that saves the document:
     * the row lock means two people saving at once never get the same number.
     */
    public function reserve(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $counter = $this->lockedCounter($type);
            $number = $counter->next_number;
            $counter->update(['next_number' => $number + 1]);

            return $this->format($type, $number);
        });
    }

    /** "Start the next quote at 120" (e.g. when moving from another system). */
    public function setNextNumber(string $type, int $next): void
    {
        DB::transaction(fn () => $this->lockedCounter($type)->update(['next_number' => $next]));
    }

    /** Builds a number from the format settings, e.g. ["QT", year, padding 4] + 42 → "QT-2026-0042". */
    public function format(string $type, int $number, ?array $format = null): string
    {
        $format ??= $this->current->get()->preferences()->get("numbering.{$type}");

        return collect([
            $format['prefix'] ?: null,
            $format['include_year'] ? now()->year : null,
            str_pad((string) $number, (int) $format['padding'], '0', STR_PAD_LEFT),
        ])->filter()->implode('-');
    }

    private function counter(string $type): ?Sequence
    {
        return Sequence::where('type', $type)->where('year', $this->year($type))->first();
    }

    private function lockedCounter(string $type): Sequence
    {
        $year = $this->year($type);

        return Sequence::where('type', $type)->where('year', $year)->lockForUpdate()->first()
            ?? Sequence::create(['type' => $type, 'year' => $year, 'next_number' => 1]);
    }

    /** Numbers restart every 1 January when yearly reset is on; otherwise one counter forever. */
    private function year(string $type): int
    {
        return $this->current->get()->preferences()->get("numbering.{$type}.yearly_reset") ? now()->year : 0;
    }
}
