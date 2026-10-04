<?php

namespace App\Support;

use App\Actions\SaveDocument;
use App\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Services\ArabicAmountInWords;

/**
 * Shapes a document for the screen: the team's document page now, the client's page and the
 * PDF later. Money goes out as plain decimal strings ("1250.50") plus the currency's decimals.
 */
final class DocumentPresenter
{
    /** @return array<string, mixed> */
    public static function full(Document $document): array
    {
        $currency = $document->currency;
        $money = fn (int $minor) => Money::fromMinor($minor, $currency);
        $lines = $document->lines;

        return [
            ...self::summary($document),
            'decimals' => Money::decimals($currency),
            'client' => $document->client_snapshot ?? self::client($document->client),
            'company' => $document->company_snapshot ?? self::company($document->company),
            'lines' => $lines->map(fn (DocumentLine $line) => [
                'id' => $line->id,
                'name' => $line->name,
                'description' => $line->description,
                'qty' => SaveDocument::plainDecimal($line->qty),
                'unit' => $line->unit,
                'unit_price' => $money($line->unit_price_minor),
                'discount_percent' => SaveDocument::plainDecimal($line->discount_percent),
                'tax_name' => $line->tax_name,
                'tax_rate' => SaveDocument::plainDecimal($line->tax_rate),
                'net' => $money($line->net_minor),
            ])->all(),
            'subtotal' => $money($document->subtotal_minor),
            'discount' => $money($document->discount_minor),
            'discount_label' => $document->discount_type === 'percent' && $document->discount_minor > 0
                ? SaveDocument::plainDecimal($document->discount_value).'%'
                : null,
            'taxable' => $money($document->subtotal_minor - $document->discount_minor),
            'tax' => $money($document->tax_minor),
            // One row per rate, e.g. "VAT 15%: 1,350.00".
            'tax_breakdown' => $lines->filter(fn ($line) => $line->tax_minor > 0)
                ->groupBy(fn ($line) => $line->tax_name.'|'.$line->tax_rate)
                ->map(fn ($group) => [
                    'name' => $group->first()->tax_name,
                    'rate' => SaveDocument::plainDecimal($group->first()->tax_rate),
                    'amount' => $money($group->sum('tax_minor')),
                ])->values()->all(),
            'total' => $money($document->total_minor),
            'amount_in_words' => (new ArabicAmountInWords)->convert($document->total_minor, $currency),
            'notes' => $document->notes,
            'terms' => $document->terms,
        ];
    }

    /** What lists need. @return array<string, mixed> */
    public static function summary(Document $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->type,
            'number' => $document->displayNumber(),
            'revision' => $document->revision,
            'is_latest' => $document->is_latest,
            'status' => $document->status->value,
            'client_name' => $document->client_snapshot['name'] ?? $document->client?->name,
            'currency' => $document->currency,
            'total' => Money::fromMinor($document->total_minor, $document->currency),
            'issue_date' => $document->issue_date?->toDateString(),
            'valid_until' => $document->valid_until?->toDateString(),
            'due_date' => $document->due_date?->toDateString(),
            'sent_at' => $document->sent_at?->toIso8601String(),
            'first_viewed_at' => $document->first_viewed_at?->toIso8601String(),
            'last_viewed_at' => $document->last_viewed_at?->toIso8601String(),
            'views_count' => $document->views_count,
            'created_by' => $document->creator?->name,
            // "Sent 3+ days ago and still not opened" (section 3: follow-up warning).
            // Only while still "Sent": once the client answered (or it expired) there's nothing to chase.
            'not_viewed_warning' => $document->status === DocumentStatus::Sent
                && $document->sent_at !== null && $document->first_viewed_at === null && $document->sent_at->lte(now()->subDays(3)),
        ];
    }

    /** The values the editor form starts from. @return array<string, mixed> */
    public static function form(Document $document): array
    {
        $currency = $document->currency;

        return [
            'client' => $document->client?->toFormArray(),
            'currency' => $currency,
            'issue_date' => $document->issue_date->toDateString(),
            'valid_until' => $document->valid_until?->toDateString(),
            'due_date' => $document->due_date?->toDateString(),
            'discount_type' => $document->discount_type,
            'discount_value' => SaveDocument::plainDecimal($document->discount_value),
            'notes' => $document->notes ?? '',
            'terms' => $document->terms ?? '',
            'lines' => $document->lines->map(fn (DocumentLine $line) => [
                'item_id' => $line->item_id,
                'name' => $line->name,
                'description' => $line->description ?? '',
                'qty' => SaveDocument::plainDecimal($line->qty),
                'unit' => $line->unit ?? '',
                'unit_price' => Money::fromMinor($line->unit_price_minor, $currency),
                'discount' => SaveDocument::plainDecimal($line->discount_percent),
                'tax_rate_id' => $line->tax_rate_id,
            ])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function client(?Client $client): ?array
    {
        return $client?->only('name', 'contact_name', 'email', 'phone', 'vat_number', 'cr_number', 'address');
    }

    /** @return array<string, mixed> */
    public static function company(Company $company): array
    {
        $settings = $company->preferences();

        return [
            ...$company->only('name', 'legal_name', 'vat_number', 'cr_number', 'address', 'phone', 'email'),
            'logo_url' => $company->imageUrl('logo'),
            'stamp_url' => $company->imageUrl('stamp'),
            'signature_url' => $company->imageUrl('signature'),
            'brand_color' => $settings->get('brand_color'),
            'bank_details' => $settings->get('bank_details'),
        ];
    }
}
