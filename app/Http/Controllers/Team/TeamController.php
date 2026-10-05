<?php

namespace App\Http\Controllers\Team;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamController extends Controller
{
    public function index(Request $request, CurrentCompany $current): Response
    {
        $members = $current->get()->users()->orderBy('users.name')->get()->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'job_title' => $user->job_title,
            'phone' => $user->phone,
            'avatar' => $user->avatarUrl(),
            'role' => $user->pivot->role,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'is_me' => $user->id === $request->user()->id,
        ]);

        $invitations = Invitation::pending()->latest()->get()->map(fn (Invitation $invitation) => [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'expired' => $invitation->isExpired(),
        ]);

        return Inertia::render('Team/Index', [
            'members' => $members,
            'invitations' => $invitations,
            'assignableRoles' => array_map(fn (Role $role) => $role->value, Role::assignable()),
        ]);
    }
}
