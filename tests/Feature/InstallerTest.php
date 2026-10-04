<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Support\Edition;
use App\Support\EnvFile;
use App\Support\Installer;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The web installer, with .env and storage pointed at a temporary folder. */
class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        // The installer belongs to the Picalica edition; the SaaS edition never shows it.
        config(['wafiq.force_installed' => null, 'edition.name' => Edition::SELF_HOSTED]);
        Installer::forget();
        app(CurrentCompany::class)->forget();

        $this->dir = sys_get_temp_dir().'/wafiq-install-'.uniqid();
        File::makeDirectory($this->dir.'/storage/app', 0777, true);
        File::copy(base_path('deploy/env.example'), $this->dir.'/.env.example');
        $this->app->useEnvironmentPath($this->dir);
        $this->app->useStoragePath($this->dir.'/storage');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        Installer::forget();

        parent::tearDown();
    }

    private function env(): string
    {
        return (string) @file_get_contents($this->dir.'/.env');
    }

    public function test_everything_leads_to_the_installer_until_installed(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');

        $this->get('/install')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Install/Index')
            ->where('step', 'requirements')
            ->has('requirements'));
    }

    public function test_steps_must_happen_in_order(): void
    {
        $this->post('/install/company', ['company' => 'x', 'currency' => 'SAR', 'name' => 'x', 'email' => 'x@example.com'])->assertStatus(409);
        $this->post('/install/finish')->assertStatus(409);
    }

    public function test_a_wrong_database_gives_a_clear_error_and_leaves_env_alone(): void
    {
        $this->withSession(['install.step' => 'database'])
            ->post('/install/database', ['host' => '127.0.0.1', 'port' => 1, 'database' => 'nope', 'username' => 'nope', 'password' => 'x'])
            ->assertSessionHasErrors('database');

        $this->assertStringNotContainsString('DB_DATABASE=nope', $this->env());
    }

    public function test_company_mail_and_finish(): void
    {
        Mail::fake();

        // Company & owner
        $this->withSession(['install.step' => 'company'])
            ->post('/install/company', ['company' => 'شركة الإتقان', 'currency' => 'SAR', 'name' => 'أحمد', 'email' => 'Owner@Example.com'])
            ->assertRedirect('/install');

        $company = Company::sole();
        $owner = User::where('email', 'owner@example.com')->sole();
        $this->assertSame(Role::Owner, $owner->roleIn($company));
        $this->assertSame(15.0, TaxRate::withoutGlobalScopes()->sole()->rate); // SAR → VAT 15% preset

        // The company exists now, but the installer stays open to finish.
        $this->get('/install')->assertOk()->assertInertia(fn (Assert $page) => $page->where('step', 'mail')->where('ownerEmail', 'owner@example.com'));

        // Mail: settings written to .env, test message sent to the owner
        $this->post('/install/mail', ['mailer' => 'smtp', 'host' => 'mail.example.com', 'port' => 465, 'username' => 'u@example.com', 'password' => 'p#ss word', 'from_address' => 'noreply@example.com'])
            ->assertRedirect('/install');
        $this->assertStringContainsString('MAIL_HOST=mail.example.com', $this->env());
        $this->assertStringContainsString('MAIL_SCHEME=smtps', $this->env());
        $this->assertStringContainsString('MAIL_PASSWORD="p#ss word"', $this->env());

        $this->get('/install')->assertInertia(fn (Assert $page) => $page
            ->where('step', 'done')
            ->where('cron', fn ($cron) => str_contains($cron, 'php artisan schedule:run')));

        // Finish: logged in, marker written, installer gone
        $this->post('/install/finish')->assertRedirect('/');
        $this->assertAuthenticatedAs($owner);
        $this->assertFileExists($this->dir.'/storage/app/installed');
        $this->get('/install')->assertNotFound();
        $this->get('/')->assertOk();
    }

    public function test_the_step_follows_reality_not_just_the_session(): void
    {
        // The session says "mail" but the owner it remembers is gone: back to the company step.
        $this->withSession(['install.step' => 'mail', 'install.owner_id' => 999])
            ->get('/install')
            ->assertInertia(fn (Assert $page) => $page->where('step', 'company'));

        // The session says "company" but the tables are gone (database wiped): back to the database step.
        Schema::withoutForeignKeyConstraints(fn () => Schema::dropIfExists('companies'));
        $this->withSession(['install.step' => 'company'])
            ->get('/install')
            ->assertInertia(fn (Assert $page) => $page->where('step', 'database'));
    }

    public function test_start_over(): void
    {
        $this->withSession(['install.step' => 'mail', 'install.owner_email' => 'o@example.com'])
            ->post('/install/restart')
            ->assertRedirect('/install')
            ->assertSessionMissing(['install.step', 'install.owner_email']);

        $this->get('/install')->assertInertia(fn (Assert $page) => $page->where('step', 'requirements'));
    }

    public function test_mail_can_be_skipped(): void
    {
        $this->withSession(['install.step' => 'mail', 'install.owner_email' => 'o@example.com'])
            ->post('/install/mail/skip')
            ->assertRedirect('/install');

        $this->assertStringContainsString('MAIL_MAILER=log', $this->env());
        $this->get('/install')->assertInertia(fn (Assert $page) => $page
            ->where('mailSkipped', true)
            ->where('rescue', 'php artisan wafiq:login-link o@example.com'));
    }

    public function test_a_copy_with_a_company_but_no_marker_counts_as_installed(): void
    {
        $this->member(); // e.g. set up with `php artisan wafiq:setup` before the installer existed
        app(CurrentCompany::class)->forget();
        Installer::forget();

        $this->get('/install')->assertNotFound();
        $this->assertFileExists($this->dir.'/storage/app/installed');
    }

    public function test_env_values_are_quoted_safely(): void
    {
        EnvFile::set(['APP_NAME' => 'شركة الإتقان', 'DB_PASSWORD' => 'a"b$c\\d', 'NEW_KEY' => 'plain']);

        $env = $this->env();
        $this->assertStringContainsString('APP_NAME="شركة الإتقان"', $env);
        $this->assertStringContainsString('DB_PASSWORD="a\\"b\\$c\\\\d"', $env);
        $this->assertStringContainsString("NEW_KEY=plain\n", $env);

        // Laravel reads them back unchanged.
        $parsed = Dotenv::parse($env);
        $this->assertSame('a"b$c\\d', $parsed['DB_PASSWORD']);
        $this->assertSame('شركة الإتقان', $parsed['APP_NAME']);
    }
}
