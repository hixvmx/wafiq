<?php

namespace App\Services;

use App\Models\LoginToken;
use App\Models\User;
use App\Support\Edition;
use App\Support\Token;

/** Creates and redeems single-use magic login links. */
class MagicLink
{
    /**
     * A new login link for the user. Older unused links stop working.
     * The link opens a confirmation page; the token is only used when the member presses
     * "Log in", so email security scanners that open links can't burn it.
     */
    public function create(User $user, ?string $ip = null): string
    {
        $user->loginTokens()->unused()->delete();

        $token = Token::generate();

        $user->loginTokens()->create([
            'token_hash' => Token::hash($token),
            'expires_at' => now()->addMinutes(config('wafiq.login_link_minutes')),
            'ip' => $ip,
        ]);

        return route('login.confirm', $token);
    }

    /** Only members of a company may log in (in the self-hosted edition: of the company). */
    public function canSignIn(User $user): bool
    {
        return Edition::isSaas()
            ? $user->companies()->exists()
            : $user->roleIn(app(CurrentCompany::class)->get()) !== null;
    }

    /** Marks the link as used and returns its user, or null when it's invalid, used or expired. */
    public function redeem(string $token): ?User
    {
        $loginToken = LoginToken::findUsable($token);

        if (! $loginToken) {
            return null;
        }

        // Atomic: two clicks at the same moment can't both log in.
        $claimed = LoginToken::whereKey($loginToken->id)->whereNull('used_at')->update(['used_at' => now()]);

        return $claimed ? $loginToken->user : null;
    }
}
