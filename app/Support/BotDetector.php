<?php

namespace App\Support;

/**
 * Link-preview robots and scanners open every link they see (WhatsApp, Telegram, Slack,
 * Outlook Safe Links…). Counting them would mark every document "Viewed" instantly.
 * The main defence is that a view needs JavaScript + 2 visible seconds; this list catches
 * the rest.
 */
final class BotDetector
{
    private const SIGNATURES = [
        'bot', 'crawler', 'spider', 'preview', 'scanner', 'fetch',
        'whatsapp', 'facebookexternalhit', 'facebot', 'telegram', 'slack', 'twitter', 'linkedin',
        'discord', 'skype', 'pinterest', 'embedly', 'quora link preview', 'vkshare', 'w3c_validator',
        'headlesschrome', 'phantomjs', 'curl', 'wget', 'python-requests', 'go-http-client', 'okhttp',
        'microsoft office', 'outlook', 'safelinks', 'proofpoint', 'mimecast', 'barracuda',
    ];

    public static function isBot(?string $userAgent): bool
    {
        $agent = strtolower(trim((string) $userAgent));

        if ($agent === '') {
            return true;
        }

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($agent, $signature)) {
                return true;
            }
        }

        return false;
    }

    public static function device(?string $userAgent): string
    {
        return preg_match('/Mobile|Android|iPhone|iPad|iPod/i', (string) $userAgent) ? 'mobile' : 'desktop';
    }
}
