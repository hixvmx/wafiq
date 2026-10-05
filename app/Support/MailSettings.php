<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * How the app sends email: SMTP, the Resend API (resend.com) or the server's sendmail.
 * Shared by the installer and Settings → Email. Settings live in .env; they are tried on a
 * test message first and only written when that message was accepted.
 */
final class MailSettings
{
    public const MAILERS = ['smtp', 'resend', 'sendmail'];

    /** .env key => config key, to use new settings in this request before they are saved. */
    private const CONFIG = [
        'MAIL_MAILER' => 'mail.default',
        'MAIL_FROM_ADDRESS' => 'mail.from.address',
        'MAIL_FROM_NAME' => 'mail.from.name',
        'MAIL_HOST' => 'mail.mailers.smtp.host',
        'MAIL_PORT' => 'mail.mailers.smtp.port',
        'MAIL_USERNAME' => 'mail.mailers.smtp.username',
        'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
        'MAIL_SCHEME' => 'mail.mailers.smtp.scheme',
        'RESEND_API_KEY' => 'services.resend.key',
    ];

    /**
     * @param  bool  $keepSecrets  an empty password / API key keeps the saved one (the settings
     *                             page never shows secrets, so "empty" means "unchanged")
     * @return array<string, mixed>
     */
    public static function rules(Request $request, bool $keepSecrets = false): array
    {
        $mailer = $request->input('mailer');
        $savedKey = $keepSecrets && filled(config('services.resend.key'));

        return [
            'mailer' => ['required', Rule::in(self::MAILERS)],
            'host' => [Rule::requiredIf($mailer === 'smtp'), 'nullable', 'string', 'max:255'],
            'port' => [Rule::requiredIf($mailer === 'smtp'), 'nullable', 'integer', 'between:1,65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            // Resend API keys look like re_xxxxxxxx.
            'resend_key' => [Rule::requiredIf($mailer === 'resend' && ! $savedKey), 'nullable', 'string', 'max:255', 'regex:/^re_[A-Za-z0-9_]+$/'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * The .env values for validated settings.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|int>
     */
    public static function env(array $data, bool $keepSecrets = false): array
    {
        $env = ['MAIL_MAILER' => $data['mailer'], 'MAIL_FROM_ADDRESS' => $data['from_address']];

        if (array_key_exists('from_name', $data)) {
            $env['MAIL_FROM_NAME'] = $data['from_name'] ?: config('app.name');
        }

        if ($data['mailer'] === 'smtp') {
            $env += [
                'MAIL_HOST' => $data['host'],
                'MAIL_PORT' => (int) $data['port'],
                'MAIL_USERNAME' => $data['username'] ?? '',
                // 465 = implicit TLS; other ports upgrade with STARTTLS automatically.
                'MAIL_SCHEME' => (int) $data['port'] === 465 ? 'smtps' : 'smtp',
            ];
            if (filled($data['password'] ?? null) || ! $keepSecrets) {
                $env['MAIL_PASSWORD'] = $data['password'] ?? '';
            }
        }

        if ($data['mailer'] === 'resend' && filled($data['resend_key'] ?? null)) {
            $env['RESEND_API_KEY'] = $data['resend_key'];
        }

        return $env;
    }

    /** Uses these .env values for the rest of this request (to send the test message). */
    public static function apply(array $env): void
    {
        foreach ($env as $key => $value) {
            config([self::CONFIG[$key] => $value]);
        }

        foreach (self::MAILERS as $mailer) {
            app('mail.manager')->purge($mailer);
        }
    }

    /** Sends a short test message; returns the error to show, or null when it was accepted. */
    public static function sendTest(string $to, string $subject, string $body): ?string
    {
        try {
            Mail::raw($body, fn ($message) => $message->to($to)->subject($subject));
        } catch (Throwable $e) {
            return Str::limit($e->getMessage(), 300);
        }

        return null;
    }

    /**
     * The current settings for the form. Secrets are never sent to the browser, only whether one is saved.
     *
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        $mailer = config('mail.default');
        $configured = in_array($mailer, self::MAILERS, true);
        // Before mail is set up, .env holds placeholders (127.0.0.1, hello@example.com): don't prefill those.
        $smtp = $mailer === 'smtp';

        return [
            'mailer' => $configured ? $mailer : 'resend',
            'configured' => $configured,
            'host' => $smtp ? config('mail.mailers.smtp.host') : null,
            'port' => $smtp ? config('mail.mailers.smtp.port') : 465,
            'username' => $smtp ? config('mail.mailers.smtp.username') : null,
            'has_password' => filled(config('mail.mailers.smtp.password')),
            'has_resend_key' => filled(config('services.resend.key')),
            'from_address' => $configured ? config('mail.from.address') : null,
            'from_name' => config('mail.from.name'),
        ];
    }
}
