<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Company name, legal details, contacts and bank details (printed on documents). */
class CompanyProfileController extends Controller
{
    public function edit(CurrentCompany $current): Response
    {
        $company = $current->get();

        return Inertia::render('Settings/Company', [
            'profile' => [
                ...$company->only('name', 'legal_name', 'vat_number', 'cr_number', 'address', 'phone', 'email'),
                'bank_details' => $company->preferences()->get('bank_details'),
            ],
        ]);
    }

    public function update(Request $request, CurrentCompany $current): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:50'],
            'cr_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'bank_details' => ['nullable', 'string', 'max:2000'],
        ]);

        $company = $current->get();
        $company->update(collect($data)->except('bank_details')->all());
        $company->preferences()->put(['bank_details' => $data['bank_details'] ?? '']);

        return back()->with('success', __('ui.settings.saved'));
    }
}
