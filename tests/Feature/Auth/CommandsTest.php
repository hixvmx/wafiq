<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_creates_the_company_and_owner_once(): void
    {
        config(['edition.name' => Edition::SELF_HOSTED]);

        $this->artisan('wafiq:setup', ['--company' => 'شركة الإتقان', '--name' => 'أحمد', '--email' => 'Owner@Example.com'])
            ->expectsOutputToContain('/login/')
            ->assertSuccessful();

        $company = Company::sole();
        $owner = User::where('email', 'owner@example.com')->sole();
        $this->assertSame(Role::Owner, $owner->roleIn($company));

        app(CurrentCompany::class)->forget();
        $this->artisan('wafiq:setup', ['--company' => 'Again', '--name' => 'X', '--email' => 'x@example.com'])->assertFailed();
        $this->assertSame(1, Company::count());
    }

    public function test_login_link_command_prints_a_working_link(): void
    {
        $user = $this->member(Role::Admin, ['email' => 'admin@example.com']);

        Artisan::call('wafiq:login-link', ['email' => 'admin@example.com']);
        preg_match('#/login/(\S+)#', Artisan::output(), $match);

        $this->post("/login/{$match[1]}")->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_link_command_refuses_non_members(): void
    {
        $this->member();
        User::factory()->create(['email' => 'outsider@example.com']);

        $this->artisan('wafiq:login-link', ['email' => 'outsider@example.com'])->assertFailed();
    }
}
