<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Secret link tokens (login links, invitations, later the client share links).
 * The plain token only ever lives in the email / URL; the database keeps its hash.
 */
final class Token
{
    public static function generate(): string
    {
        return Str::random(64);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
