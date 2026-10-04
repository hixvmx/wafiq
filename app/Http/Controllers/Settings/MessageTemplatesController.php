<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use App\Support\CompanySettings;
use App\Support\MessageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** The WhatsApp message and email that go with a sent quotation / invoice. */
class MessageTemplatesController extends Controller
{
    public function edit(CurrentCompany $current): Response
    {
        return Inertia::render('Settings/Messages', [
            'templates' => $current->get()->preferences()->get('templates'),
            'variables' => MessageTemplate::VARIABLES,
            'defaults' => trans('defaults.templates'),
        ]);
    }

    public function update(Request $request, CurrentCompany $current): RedirectResponse
    {
        $rules = [];
        foreach (CompanySettings::DOCUMENT_TYPES as $type) {
            $rules += [
                "{$type}.whatsapp" => ['required', 'string', 'max:2000'],
                "{$type}.email_subject" => ['required', 'string', 'max:255'],
                "{$type}.email_body" => ['required', 'string', 'max:5000'],
            ];
        }
        $data = $request->validate($rules);

        foreach (CompanySettings::DOCUMENT_TYPES as $type) {
            $current->get()->preferences()->put(["templates.{$type}" => $data[$type]]);
        }

        return back()->with('success', __('ui.settings.saved'));
    }
}
