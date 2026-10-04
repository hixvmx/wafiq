<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Tax rates and the currencies documents can use. */
class TaxController extends Controller
{
    public function index(CurrentCompany $current): Response
    {
        $company = $current->get();

        return Inertia::render('Settings/Taxes', [
            'taxRates' => TaxRate::orderByDesc('is_default')->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']),
            'presets' => collect(config('wafiq.tax_presets'))
                ->map(fn ($rate, $code) => ['code' => $code, 'name' => __("ui.settings.taxes.presets.{$code}"), 'rate' => $rate])
                ->values(),
            'currency' => $company->currency,
            'currencies' => $company->preferences()->get('currencies'),
            'allCurrencies' => collect(config('currencies'))->keys()->map(fn ($code) => ['code' => $code, 'name' => __("currencies.{$code}")]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tax = TaxRate::create($this->validated($request));

        if ($tax->is_default || TaxRate::count() === 1) {
            $tax->makeDefault();
        }

        return back()->with('success', __('ui.settings.saved'));
    }

    public function update(Request $request, TaxRate $taxRate): RedirectResponse
    {
        $taxRate->update($this->validated($request));

        if ($taxRate->is_default) {
            $taxRate->makeDefault();
        }

        return back()->with('success', __('ui.settings.saved'));
    }

    /** Lines keep a copy of the name and rate, so deleting a rate never changes a document. */
    public function destroy(TaxRate $taxRate): RedirectResponse
    {
        $taxRate->delete();

        return back()->with('success', __('ui.settings.deleted'));
    }

    public function updateCurrencies(Request $request, CurrentCompany $current): RedirectResponse
    {
        $codes = array_keys(config('currencies'));
        $data = $request->validate([
            'currency' => ['required', Rule::in($codes)],
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['distinct', Rule::in($codes)],
        ]);

        $company = $current->get();
        $company->update(['currency' => $data['currency']]);
        // The default currency is always available.
        $company->preferences()->put(['currencies' => array_values(array_unique([$data['currency'], ...$data['currencies']]))]);

        return back()->with('success', __('ui.settings.saved'));
    }

    /** @return array{name: string, rate: float, is_default: bool} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_default' => ['boolean'],
        ]);

        return [...$data, 'is_default' => (bool) ($data['is_default'] ?? false)];
    }
}
