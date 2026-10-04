<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** The invited person opens the emailed link, sets their name (new accounts) and joins the team. */
class AcceptInvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = Invitation::findUsable($token);

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'invitation' => $invitation ? [
                'email' => $invitation->email,
                'company' => $invitation->company->name,
                'role' => $invitation->role->label(),
                'has_account' => User::where('email', $invitation->email)->exists(),
            ] : null,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::findUsable($token);

        if (! $invitation) {
            return redirect()->route('invitations.show', $token);
        }

        $user = User::where('email', $invitation->email)->first();

        if (! $user) {
            $request->validate(['name' => ['required', 'string', 'max:100']]);
        }

        $user = DB::transaction(function () use ($invitation, $user, $request) {
            $user ??= User::create(['name' => trim($request->input('name')), 'email' => $invitation->email]);
            $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now(), 'last_login_at' => now()])->save();

            // Never change the role of someone who is already a member (e.g. the owner).
            if (! $user->roleIn($invitation->company)) {
                $invitation->company->addMember($user, $invitation->role);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        app(CurrentCompany::class)->set($invitation->company);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', __('ui.invitations.welcome', ['company' => $invitation->company->name]));
    }
}
