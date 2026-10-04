<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\TaxRate;
use App\Services\CurrentCompany;
use App\Support\Digits;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Products & services catalog. */
class ItemController extends Controller
{
    public function index(Request $request, CurrentCompany $current): Response
    {
        $type = in_array($request->query('type'), Item::TYPES, true) ? $request->query('type') : null;

        $items = Item::search($request->query('q'))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Item $item) => $item->toFormArray());

        return Inertia::render('Items/Index', [
            'items' => $items,
            'filters' => ['q' => (string) $request->query('q', ''), 'type' => $type],
            'taxRates' => TaxRate::orderByDesc('is_default')->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']),
            'currencies' => $current->get()->preferences()->get('currencies'),
            'defaultCurrency' => $current->get()->currency,
            'units' => trans('ui.items.unit_suggestions'),
        ]);
    }

    /** Quick search for the document line editor. */
    public function search(Request $request): JsonResponse
    {
        $items = Item::search($request->query('q'))->orderBy('name')->limit(10)->get();

        return response()->json(['data' => $items->map(fn (Item $item) => $item->toFormArray())]);
    }

    public function store(Request $request, CurrentCompany $current): RedirectResponse
    {
        Item::create($this->validated($request, $current));

        return back()->with('success', __('ui.items.created'));
    }

    public function update(Request $request, Item $item, CurrentCompany $current): RedirectResponse
    {
        $item->update($this->validated($request, $current, $item));

        return back()->with('success', __('ui.items.updated'));
    }

    public function destroy(Item $item): RedirectResponse
    {
        $item->delete();

        return back()->with('success', __('ui.items.deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, CurrentCompany $current, ?Item $item = null): array
    {
        $company = $current->get();
        $currency = $request->input('currency');
        // "١٬٢٥٠٫٥" or "1,250.5" → "1250.5"
        $request->merge(['price' => str_replace([',', '٬', ' '], '', str_replace('٫', '.', Digits::latin(trim((string) $request->input('price')))))]);

        $data = $request->validate([
            'type' => ['required', Rule::in(Item::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit' => ['nullable', 'string', 'max:30'],
            'currency' => ['required', Rule::in(array_filter([...$company->preferences()->get('currencies'), $item?->currency]))], // an item may keep a since-disabled currency
            'price' => ['required', 'string', ...(is_string($currency) && config("currencies.{$currency}") !== null ? ['regex:'.Money::pattern($currency)] : [])],
            'tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $company->id)],
        ]);

        return [
            ...collect($data)->except('price')->all(),
            'price_minor' => Money::toMinor($data['price'], $data['currency']),
        ];
    }
}
