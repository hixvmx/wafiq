<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\LoginLinkNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MagicLinkLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->member();

        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_a_member_receives_a_login_link(): void
    {
        $user = $this->member(Role::Sales, ['email' => 'sara@example.com']);

        $this->post('/login', ['email' => ' Sara@Example.com '])
            ->assertRedirect('/login')
            ->assertSessionHas('login_link_sent', 'sara@example.com');

        Notification::assertSentTo($user, LoginLinkNotification::class, fn ($n) => str_contains($n->url, '/login/'));
    }

    public function test_unknown_emails_get_the_same_answer_and_no_email(): void
    {
        $this->member();
        $outsider = User::factory()->create(['email' => 'outsider@example.com']); // has no company

        foreach (['nobody@example.com', 'outsider@example.com'] as $email) {
            $this->post('/login', ['email' => $email])
                ->assertRedirect('/login')
                ->assertSessionHas('login_link_sent', $email);
        }

        Notification::assertNothingSent();
        $this->assertSame(0, $outsider->loginTokens()->count());
    }

    public function test_opening_the_link_does_not_use_it_and_confirming_logs_in(): void
    {
        $user = $this->member();
        $token = $this->requestToken($user);

        // Email scanners open the link: still valid afterwards.
        $this->get("/login/{$token}")->assertInertia(fn (Assert $page) => $page->component('Auth/ConfirmLogin')->where('valid', true));
        $this->get("/login/{$token}")->assertInertia(fn (Assert $page) => $page->where('valid', true));

        $this->post("/login/{$token}")->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_a_link_works_only_once(): void
    {
        $user = $this->member();
        $token = $this->requestToken($user);

        $this->post("/login/{$token}");
        $this->post('/logout');

        $this->post("/login/{$token}")->assertRedirect("/login/{$token}");
        $this->assertGuest();
        $this->get("/login/{$token}")->assertInertia(fn (Assert $page) => $page->where('valid', false));
    }

    public function test_a_link_expires_after_15_minutes(): void
    {
        $user = $this->member();
        $token = $this->requestToken($user);

        $this->travel(16)->minutes();

        $this->post("/login/{$token}");
        $this->assertGuest();
    }

    public function test_a_new_link_cancels_the_older_one(): void
    {
        $user = $this->member();
        $old = $this->requestToken($user);
        $new = $this->requestToken($user);

        $this->post("/login/{$old}");
        $this->assertGuest();

        $this->post("/login/{$new}");
        $this->assertAuthenticatedAs($user);
    }

    public function test_tokens_are_stored_hashed(): void
    {
        $user = $this->member();
        $token = $this->requestToken($user);

        $this->assertDatabaseMissing('login_tokens', ['token_hash' => $token]);
        $this->assertDatabaseHas('login_tokens', ['token_hash' => hash('sha256', $token)]);
    }

    public function test_link_requests_are_throttled_per_email(): void
    {
        $this->member(Role::Owner, ['email' => 'owner@example.com']);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => 'owner@example.com'])->assertSessionHasNoErrors();
        }

        $this->post('/login', ['email' => 'owner@example.com'])->assertSessionHasErrors('email');
        // Unknown emails are throttled the same way, so throttling reveals nothing.
        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['email' => 'ghost@example.com'])->assertSessionHasNoErrors();
        }
        $this->post('/login', ['email' => 'ghost@example.com'])->assertSessionHasErrors('email');
    }

    public function test_logout(): void
    {
        $this->actingAs($this->member())->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_a_removed_member_is_logged_out_on_the_next_click(): void
    {
        $user = $this->member(Role::Sales);
        $this->company()->users()->detach($user->id);

        $this->actingAs($user)->get('/')->assertRedirect('/login')->assertSessionHas('error');
        $this->assertGuest();
    }

    private function requestToken(User $user): string
    {
        $this->post('/login', ['email' => $user->email]);

        $url = null;
        Notification::assertSentTo($user, LoginLinkNotification::class, function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });

        return basename($url);
    }
}
