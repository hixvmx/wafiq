<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** What a new quotation / invoice starts with: validity or due days, terms and notes. */
class DocumentDefaultsController extends Controller
{
    public function edit(CurrentCompany $current): Response
    {
        return Inertia::render('Settings/Documents', [
            'documents' => $current->get()->preferences()->get('documents'),
        ]);
    }

    public function update(Request $request, CurrentCompany $current): RedirectResponse
    {
        $data = $request->validate([
            'quote.validity_days' => ['required', 'integer', 'between:1,365'],
            'quote.terms' => ['nullable', 'string', 'max:5000'],
            'quote.notes' => ['nullable', 'string', 'max:5000'],
            'invoice.due_days' => ['required', 'integer', 'between:0,365'],
            'invoice.terms' => ['nullable', 'string', 'max:5000'],
            'invoice.notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $current->get()->preferences()->put([
            'documents.quote' => [
                'validity_days' => (int) $data['quote']['validity_days'],
                'terms' => $data['quote']['terms'] ?? '',
                'notes' => $data['quote']['notes'] ?? '',
            ],
            'documents.invoice' => [
                'due_days' => (int) $data['invoice']['due_days'],
                'terms' => $data['invoice']['terms'] ?? '',
                'notes' => $data['invoice']['notes'] ?? '',
            ],
        ]);

        return back()->with('success', __('ui.settings.saved'));
    }
}
