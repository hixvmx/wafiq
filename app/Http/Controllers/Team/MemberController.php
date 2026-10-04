<?php

namespace App\Http\Controllers\Team;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function update(Request $request, User $member, CurrentCompany $current): RedirectResponse
    {
        $this->ensureEditable($request, $member);

        $data = $request->validate([
            'role' => ['required', Rule::in(array_map(fn (Role $role) => $role->value, Role::assignable()))],
        ]);

        $current->get()->users()->updateExistingPivot($member->id, ['role' => $data['role']]);

        return back()->with('success', __('ui.team.role_changed', ['name' => $member->name]));
    }

    public function destroy(Request $request, User $member, CurrentCompany $current): RedirectResponse
    {
        $this->ensureEditable($request, $member);

        $current->get()->users()->detach($member->id);
        $member->loginTokens()->delete();

        return back()->with('success', __('ui.team.removed', ['name' => $member->name]));
    }

    /** Members of this company only; nobody changes the owner or themselves. */
    private function ensureEditable(Request $request, User $member): void
    {
        $role = $member->currentRole();

        abort_if($role === null, 404);
        abort_if($role === Role::Owner || $member->is($request->user()), 403);
    }
}
