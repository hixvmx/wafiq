<?php

namespace App\Http\Controllers\Team;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Notifications\InvitationNotification;
use App\Services\CurrentCompany;
use App\Support\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function store(Request $request, CurrentCompany $current): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::assignable()))],
        ]);

        if ($current->get()->users()->where('email', $data['email'])->exists()) {
            return back()->withErrors(['email' => __('ui.team.already_member')]);
        }

        // One pending invitation per email: inviting again replaces the old one.
        Invitation::pending()->where('email', $data['email'])->delete();

        $invitation = new Invitation([...$data, 'invited_by' => $request->user()->id]);

        return $this->send($invitation, $request, 'ui.team.invited');
    }

    public function resend(Invitation $invitation, Request $request): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 404);

        return $this->send($invitation, $request, 'ui.team.resent');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 404);

        $invitation->delete();

        return back()->with('success', __('ui.team.revoked'));
    }

    /** Saves the invitation with a fresh token and emails it. */
    private function send(Invitation $invitation, Request $request, string $successKey): RedirectResponse
    {
        $token = $invitation->renew();

        $sent = Notifier::send(
            Notification::route('mail', $invitation->email),
            new InvitationNotification($invitation, route('invitations.show', $token), app(CurrentCompany::class)->get()->name, $request->user()->name),
        );

        return $sent
            ? back()->with('success', __($successKey, ['email' => $invitation->email]))
            : back()->with('error', __('ui.team.mail_failed'));
    }
}
