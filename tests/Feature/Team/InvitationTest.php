<?php

namespace Tests\Feature\Team;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = $this->member(Role::Admin);
    }

    public function test_an_admin_invites_a_teammate(): void
    {
        $this->actingAs($this->admin)
            ->post('/team/invitations', ['email' => 'New@Example.com', 'role' => 'accountant'])
            ->assertSessionHas('success');

        $invitation = Invitation::sole();
        $this->assertSame('new@example.com', $invitation->email);
        $this->assertSame(Role::Accountant, $invitation->role);
        $this->assertSame($this->company()->id, $invitation->company_id);

        Notification::assertSentOnDemand(InvitationNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'new@example.com');
    }

    public function test_the_owner_role_cannot_be_given_by_invitation(): void
    {
        $this->actingAs($this->admin)
            ->post('/team/invitations', ['email' => 'x@example.com', 'role' => 'owner'])
            ->assertSessionHasErrors('role');
    }

    public function test_existing_members_cannot_be_invited_again(): void
    {
        $this->member(Role::Sales, ['email' => 'sara@example.com']);

        $this->actingAs($this->admin)
            ->post('/team/invitations', ['email' => 'sara@example.com', 'role' => 'viewer'])
            ->assertSessionHasErrors('email');
    }

    public function test_inviting_the_same_email_twice_keeps_one_invitation(): void
    {
        $this->actingAs($this->admin)->post('/team/invitations', ['email' => 'a@example.com', 'role' => 'sales']);
        $this->actingAs($this->admin)->post('/team/invitations', ['email' => 'a@example.com', 'role' => 'viewer']);

        $this->assertSame(Role::Viewer, Invitation::sole()->role);
    }

    public function test_a_new_person_accepts_and_is_logged_in(): void
    {
        $token = $this->invite('new@example.com', 'sales');

        $this->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page
            ->component('Auth/AcceptInvitation')
            ->where('invitation.email', 'new@example.com')
            ->where('invitation.has_account', false));

        $this->post("/invitations/{$token}")->assertSessionHasErrors('name');
        $this->post("/invitations/{$token}", ['name' => 'سارة'])->assertRedirect('/');

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Sales, $user->roleIn($this->company()));
        $this->assertNotNull(Invitation::sole()->accepted_at);
    }

    public function test_an_invitation_works_only_once(): void
    {
        $token = $this->invite('new@example.com', 'sales');
        $this->post("/invitations/{$token}", ['name' => 'سارة']);
        $this->post('/logout');

        $this->post("/invitations/{$token}", ['name' => 'سارة'])->assertRedirect("/invitations/{$token}");
        $this->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page->where('invitation', null));
    }

    public function test_invitations_expire_after_7_days(): void
    {
        $token = $this->invite('new@example.com', 'sales');

        $this->travel(8)->days();

        $this->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page->where('invitation', null));
    }

    public function test_resend_gives_a_new_link_and_kills_the_old_one(): void
    {
        $old = $this->invite('new@example.com', 'sales');
        Notification::fake();

        $this->actingAs($this->admin)->post('/team/invitations/'.Invitation::sole()->id.'/resend')->assertSessionHas('success');
        Notification::assertSentOnDemandTimes(InvitationNotification::class, 1);

        $this->post('/logout');
        $this->get("/invitations/{$old}")->assertInertia(fn (Assert $page) => $page->where('invitation', null));
    }

    public function test_revoke(): void
    {
        $token = $this->invite('new@example.com', 'sales');

        $this->actingAs($this->admin)->delete('/team/invitations/'.Invitation::sole()->id)->assertSessionHas('success');

        $this->assertSame(0, Invitation::count());
        $this->post('/logout');
        $this->get("/invitations/{$token}")->assertInertia(fn (Assert $page) => $page->where('invitation', null));
    }

    public function test_another_companys_invitation_is_out_of_reach(): void
    {
        $other = Company::factory()->create();
        $foreign = Invitation::withoutGlobalScope('company')->forceCreate([
            'company_id' => $other->id,
            'email' => 'x@example.com',
            'role' => 'sales',
            'token_hash' => str_repeat('a', 64),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($this->admin)->delete("/team/invitations/{$foreign->id}")->assertNotFound();
    }

    public function test_sales_and_viewers_cannot_manage_the_team(): void
    {
        foreach ([Role::Sales, Role::Viewer, Role::Accountant] as $role) {
            $user = $this->member($role);

            $this->actingAs($user)->get('/team')->assertForbidden();
            $this->actingAs($user)->post('/team/invitations', ['email' => 'x@example.com', 'role' => 'admin'])->assertForbidden();
        }
    }

    public function test_team_page_lists_members_and_pending_invitations(): void
    {
        $this->invite('new@example.com', 'viewer');

        $this->actingAs($this->admin)->get('/team')->assertInertia(fn (Assert $page) => $page
            ->component('Team/Index')
            ->has('members', 1)
            ->has('invitations', 1)
            ->where('invitations.0.email', 'new@example.com'));
    }

    private function invite(string $email, string $role): string
    {
        $this->actingAs($this->admin)->post('/team/invitations', compact('email', 'role'));
        $this->post('/logout');

        $url = null;
        Notification::assertSentOnDemand(InvitationNotification::class, function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });

        return basename($url);
    }
}
