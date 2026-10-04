<?php

namespace Tests\Feature\Team;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_changes_a_role(): void
    {
        $admin = $this->member(Role::Admin);
        $sara = $this->member(Role::Sales);

        $this->actingAs($admin)->put("/team/members/{$sara->id}", ['role' => 'accountant'])->assertSessionHas('success');

        $this->assertSame(Role::Accountant, $sara->roleIn($this->company()));
    }

    public function test_nobody_can_make_someone_owner(): void
    {
        $admin = $this->member(Role::Admin);
        $sara = $this->member(Role::Sales);

        $this->actingAs($admin)->put("/team/members/{$sara->id}", ['role' => 'owner'])->assertSessionHasErrors('role');
    }

    public function test_the_owner_cannot_be_changed_or_removed(): void
    {
        $owner = $this->member(Role::Owner);
        $admin = $this->member(Role::Admin);

        $this->actingAs($admin)->put("/team/members/{$owner->id}", ['role' => 'viewer'])->assertForbidden();
        $this->actingAs($admin)->delete("/team/members/{$owner->id}")->assertForbidden();
        $this->assertSame(Role::Owner, $owner->roleIn($this->company()));
    }

    public function test_members_cannot_change_or_remove_themselves(): void
    {
        $admin = $this->member(Role::Admin);

        $this->actingAs($admin)->put("/team/members/{$admin->id}", ['role' => 'viewer'])->assertForbidden();
        $this->actingAs($admin)->delete("/team/members/{$admin->id}")->assertForbidden();
    }

    public function test_an_admin_removes_a_member(): void
    {
        $admin = $this->member(Role::Admin);
        $sara = $this->member(Role::Sales);

        $this->actingAs($admin)->delete("/team/members/{$sara->id}")->assertSessionHas('success');

        $this->assertNull($sara->roleIn($this->company()));
        $this->assertModelExists($sara); // the account stays; their documents keep their author
    }

    public function test_users_outside_the_company_are_not_found(): void
    {
        $admin = $this->member(Role::Admin);
        $stranger = User::factory()->create();

        $this->actingAs($admin)->delete("/team/members/{$stranger->id}")->assertNotFound();
    }
}
