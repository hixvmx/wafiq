<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\CurrentCompany;
use App\Support\Edition;
use App\Support\EnvFile;
use App\Support\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Settings → Email: SMTP, Resend or sendmail. Self-hosted only (in the SaaS the mail is ours).
 * New settings are saved only after a test message to the admin was accepted.
 */
class MailController extends Controller
{
    public function __construct()
    {
        abort_if(Edition::isSaas(), 404);
    }

    public function edit(Request $request, CurrentCompany $current): Response
    {
        $mail = MailSettings::current();
        // Out of the box the sender is "Wafiq"; the company name is what clients expect to see.
        if (blank($mail['from_name']) || $mail['from_name'] === config('app.name')) {
            $mail['from_name'] = $current->get()->name;
        }

        return Inertia::render('Settings/Mail', [
            'mail' => $mail,
            'testEmail' => $request->user()->email,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(MailSettings::rules($request, keepSecrets: true));
        $env = MailSettings::env($data, keepSecrets: true);
        MailSettings::apply($env);

        $this->sendTest($request);

        try {
            EnvFile::set($env);
        } catch (RuntimeException) {
            return back()->with('error', __('ui.mail_settings.env_not_writable'));
        }

        // A cached config would keep using the old settings.
        if (app()->configurationIsCached()) {
            Artisan::call('config:clear');
        }

        return back()->with('success', __('ui.mail_settings.saved', ['email' => $request->user()->email]));
    }

    /** Sends a test message with the saved settings. */
    public function test(Request $request): RedirectResponse
    {
        $this->sendTest($request);

        return back()->with('success', __('ui.mail_settings.test_sent', ['email' => $request->user()->email]));
    }

    private function sendTest(Request $request): void
    {
        $error = MailSettings::sendTest($request->user()->email, __('ui.mail_settings.test_subject'), __('ui.mail_settings.test_body'));

        if ($error !== null) {
            throw ValidationException::withMessages(['mailer' => __('ui.mail_settings.failed', ['error' => $error])]);
        }
    }
}
