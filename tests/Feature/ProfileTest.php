<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_every_member_can_open_their_profile(): void
    {
        $user = $this->member(Role::Viewer, ['name' => 'سارة', 'job_title' => 'مندوبة مبيعات']);

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profile.name', 'سارة')
            ->where('profile.job_title', 'مندوبة مبيعات')
            ->where('profile.email', $user->email)
            ->where('profile.role', 'viewer')
            ->where('profile.avatar', null));
    }

    public function test_a_member_updates_their_details_but_not_their_email(): void
    {
        $user = $this->member(Role::Sales, ['email' => 'sara@example.com']);

        $this->actingAs($user)
            ->put('/profile', ['name' => 'سارة العتيبي', 'job_title' => 'مديرة المبيعات', 'phone' => '+966 55 123 4567', 'email' => 'hacker@example.com'])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('سارة العتيبي', $user->name);
        $this->assertSame('مديرة المبيعات', $user->job_title);
        $this->assertSame('+966 55 123 4567', $user->phone);
        $this->assertSame('sara@example.com', $user->email);
    }

    public function test_details_are_validated(): void
    {
        $user = $this->member(Role::Sales);

        $this->actingAs($user)
            ->put('/profile', ['name' => '', 'phone' => 'call me'])
            ->assertSessionHasErrors(['name', 'phone']);
    }

    public function test_a_member_uploads_replaces_and_removes_their_picture(): void
    {
        $user = $this->member(Role::Sales);

        $this->actingAs($user)
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg', 900, 600)])
            ->assertSessionHas('success');

        $first = $user->refresh()->avatar;
        $this->assertStringStartsWith("users/{$user->id}/avatar-", $first);
        Storage::disk('local')->assertExists($first);
        $this->assertSame([256, 256], array_slice(getimagesizefromstring(Storage::disk('local')->get($first)), 0, 2));

        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('new.png')]);
        Storage::disk('local')->assertMissing($first);

        $this->actingAs($user)->delete('/profile/avatar')->assertSessionHas('success');
        $this->assertNull($user->refresh()->avatar);
        $this->assertSame([], Storage::disk('local')->allFiles("users/{$user->id}"));
    }

    public function test_only_images_are_accepted(): void
    {
        $user = $this->member(Role::Sales);

        $this->actingAs($user)
            ->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('evil.svg', 1, 'image/svg+xml')])
            ->assertSessionHasErrors('avatar');
    }

    public function test_pictures_are_shown_to_teammates_only(): void
    {
        $sara = $this->member(Role::Sales);
        $teammate = $this->member(Role::Viewer);
        $this->actingAs($sara)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg')]);
        $url = $sara->refresh()->avatarUrl();

        $this->actingAs($teammate)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($sara)->get('/profile')->assertInertia(fn (Assert $page) => $page->where('auth.user.avatar', $url));

        // Pictures of people from another company can't be loaded.
        $stranger = User::factory()->create(['avatar' => 'users/999/avatar-x.png']);
        Storage::disk('local')->put('users/999/avatar-x.png', 'png');
        Company::factory()->create()->addMember($stranger, Role::Owner);
        $this->actingAs($teammate)->get($stranger->avatarUrl())->assertNotFound();
    }

    public function test_the_team_page_shows_profiles(): void
    {
        $owner = $this->member(Role::Owner);
        $this->member(Role::Sales, ['name' => 'خالد', 'job_title' => 'مندوب مبيعات', 'phone' => '+966500000000']);

        $this->actingAs($owner)->get('/team')->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->contains(fn ($member) => $member['name'] === 'خالد'
                && $member['job_title'] === 'مندوب مبيعات' && $member['phone'] === '+966500000000')));
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->member(Role::Owner);

        $this->get('/profile')->assertRedirect('/login');
        $this->put('/profile', ['name' => 'x'])->assertRedirect('/login');
    }
}
