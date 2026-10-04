<?php

namespace App\Support;

use App\Models\Company;

/**
 * Typed access to companies.settings (JSON), with defaults for anything never saved.
 *
 *   $company->preferences()->get('numbering.quote.prefix')   → "QT"
 *   $company->preferences()->put(['brand_color' => '#0d9488'])
 */
final class CompanySettings
{
    public const DOCUMENT_TYPES = ['quote', 'invoice'];

    public function __construct(private Company $company) {}

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $numbering = fn (string $prefix) => ['prefix' => $prefix, 'include_year' => true, 'padding' => 4, 'yearly_reset' => true];

        return [
            'brand_color' => '#0d9488',
            'bank_details' => '',
            'currencies' => ['SAR'],
            'numbering' => [
                'quote' => $numbering('QT'),
                'invoice' => $numbering('INV'),
            ],
            'documents' => [
                'quote' => ['validity_days' => 15, ...trans('defaults.documents.quote')],
                'invoice' => ['due_days' => 30, ...trans('defaults.documents.invoice')],
            ],
            'templates' => trans('defaults.templates'),
        ];
    }

    public function get(string $key): mixed
    {
        return data_get($this->all(), $key);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return self::merge(self::defaults(), $this->company->getAttribute('settings') ?? []);
    }

    /**
     * Save values by dotted key. Lists (e.g. "currencies") are replaced, never merged.
     *
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        $settings = $this->company->getAttribute('settings') ?? [];

        foreach ($values as $key => $value) {
            data_set($settings, $key, $value);
        }

        $this->company->setAttribute('settings', $settings)->save();
    }

    /** Recursive merge for keyed arrays; lists and plain values from $over win as a whole. */
    private static function merge(array $base, array $over): array
    {
        foreach ($over as $key => $value) {
            $base[$key] = is_array($value) && ! array_is_list($value) && is_array($base[$key] ?? null)
                ? self::merge($base[$key], $value)
                : $value;
        }

        return $base;
    }
}
