<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use App\Services\NumberSequence;
use App\Support\CompanySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Number format per document type (QT-2026-0001…) and where the counter continues. */
class NumberingController extends Controller
{
    public function edit(CurrentCompany $current, NumberSequence $numbers): Response
    {
        $settings = $current->get()->preferences();

        return Inertia::render('Settings/Numbering', [
            'numbering' => collect(CompanySettings::DOCUMENT_TYPES)->mapWithKeys(fn (string $type) => [$type => [
                ...$settings->get("numbering.{$type}"),
                'next_number' => $numbers->nextNumber($type),
            ]]),
            'year' => now()->year,
        ]);
    }

    public function update(Request $request, CurrentCompany $current, NumberSequence $numbers): RedirectResponse
    {
        $rules = [];
        foreach (CompanySettings::DOCUMENT_TYPES as $type) {
            $rules += [
                "{$type}.prefix" => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN]*$/u'],
                "{$type}.include_year" => ['required', 'boolean'],
                "{$type}.padding" => ['required', 'integer', 'between:1,8'],
                "{$type}.yearly_reset" => ['required', 'boolean'],
                "{$type}.next_number" => ['required', 'integer', 'between:1,99999999'],
            ];
        }
        $data = $request->validate($rules);

        foreach (CompanySettings::DOCUMENT_TYPES as $type) {
            $current->get()->preferences()->put(["numbering.{$type}" => [
                'prefix' => (string) ($data[$type]['prefix'] ?? ''),
                'include_year' => (bool) $data[$type]['include_year'],
                'padding' => (int) $data[$type]['padding'],
                'yearly_reset' => (bool) $data[$type]['yearly_reset'],
            ]]);

            // After the format is saved, so the counter of the right year is updated.
            if ((int) $data[$type]['next_number'] !== $numbers->nextNumber($type)) {
                $numbers->setNextNumber($type, (int) $data[$type]['next_number']);
            }
        }

        return back()->with('success', __('ui.settings.saved'));
    }
}
