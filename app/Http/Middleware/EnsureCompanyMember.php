<?php

namespace App\Http\Middleware;

use App\Services\CurrentCompany;
use App\Support\Edition;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The logged-in user must be a member of the current company.
 * A member who was removed from the team is logged out on their next click.
 */
class EnsureCompanyMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $current = app(CurrentCompany::class);

        // Until company subdomains exist (SaaS edition), members work in their first company.
        if (Edition::isSaas() && ! $current->get()) {
            $current->set($user->companies()->orderBy('companies.id')->first());
        }

        if (! $user->currentRole()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('ui.auth.no_access'));
        }

        return $next($request);
    }
}
