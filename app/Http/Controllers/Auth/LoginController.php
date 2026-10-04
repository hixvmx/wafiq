<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LoginToken;
use App\Models\User;
use App\Notifications\LoginLinkNotification;
use App\Services\MagicLink;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Magic link login: email → link in the inbox → confirm page → logged in. */
class LoginController extends Controller
{
    /** Requests per 10 minutes. Applied to every email, known or not, so it reveals nothing. */
    private const MAX_PER_EMAIL = 3;

    private const MAX_PER_IP = 10;

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'sentTo' => $request->session()->get('login_link_sent'),
            'minutes' => config('wafiq.login_link_minutes'),
        ]);
    }

    public function store(Request $request, MagicLink $links): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);
        $email = Str::lower(trim($request->input('email')));

        $this->throttle([
            'login-link:email:'.sha1($email) => self::MAX_PER_EMAIL,
            'login-link:ip:'.$request->ip() => self::MAX_PER_IP,
        ]);

        $user = User::where('email', $email)->first();

        if ($user && $links->canSignIn($user)) {
            Notifier::send($user, new LoginLinkNotification($links->create($user, $request->ip())));
        }

        // Same answer whether or not the email has an account.
        return redirect()->route('login')->with('login_link_sent', $email);
    }

    /** The page behind the emailed link. Opening it doesn't use the link (scanners open links too). */
    public function show(string $token): Response
    {
        return Inertia::render('Auth/ConfirmLogin', [
            'token' => $token,
            'valid' => LoginToken::findUsable($token) !== null,
        ]);
    }

    public function confirm(Request $request, string $token, MagicLink $links): RedirectResponse
    {
        $user = $links->redeem($token);

        if (! $user || ! $links->canSignIn($user)) {
            return redirect()->route('login.confirm', $token);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'email_verified_at' => $user->email_verified_at ?? now(), // the link proves the address
        ])->save();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** @param array<string, int> $limits key => max attempts per 10 minutes */
    private function throttle(array $limits): void
    {
        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, 600);
        }
    }
}
