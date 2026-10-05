<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserAvatar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The signed-in user's own details. The email is the login, so only an admin's invitation sets it. */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                ...$user->only('name', 'email', 'job_title', 'phone'),
                'avatar' => $user->avatarUrl(),
                'role' => $user->currentRole()?->value,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-()]{5,}$/'],
        ]);

        $request->user()->update($data);

        return back()->with('success', __('ui.profile.saved'));
    }

    public function uploadAvatar(Request $request, UserAvatar $avatars): RedirectResponse
    {
        // No SVG: it can carry scripts.
        $request->validate(['avatar' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096']]);

        $avatars->store($request->user(), $request->file('avatar'));

        return back()->with('success', __('ui.profile.avatar_saved'));
    }

    public function destroyAvatar(Request $request, UserAvatar $avatars): RedirectResponse
    {
        $avatars->delete($request->user());

        return back()->with('success', __('ui.profile.avatar_removed'));
    }

    /** Streams a profile picture, to people who share a company with its owner. */
    public function avatar(Request $request, User $user): StreamedResponse
    {
        $sharesACompany = $request->user()->is($user)
            || $request->user()->companies()->whereIn('companies.id', $user->companies()->select('companies.id'))->exists();
        abort_unless($sharesACompany, 404);
        abort_unless($user->avatar && Storage::disk('local')->exists($user->avatar), 404);

        return Storage::disk('local')->response($user->avatar, null, [
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
