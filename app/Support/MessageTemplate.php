<?php

namespace App\Support;

/**
 * WhatsApp / email texts with {variables}, e.g. "مرحباً {client_name}…".
 * Unknown variables are left as typed, so a typo is visible instead of silently empty.
 */
final class MessageTemplate
{
    /** @var array<string, list<string>> */
    public const VARIABLES = [
        'quote' => ['client_name', 'number', 'amount', 'link', 'valid_until', 'company'],
        'invoice' => ['client_name', 'number', 'amount', 'link', 'due_date', 'company'],
    ];

    /** @param array<string, string> $values */
    public static function render(string $template, array $values): string
    {
        return preg_replace_callback(
            '/\{([a-z_]+)\}/',
            fn (array $match) => array_key_exists($match[1], $values) ? (string) $values[$match[1]] : $match[0],
            $template,
        );
    }
}
