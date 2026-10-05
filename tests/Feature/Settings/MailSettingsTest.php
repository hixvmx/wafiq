<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\User;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Settings → Email, with .env pointed at a temporary folder. */
class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['edition.name' => Edition::SELF_HOSTED]);
        $this->dir = sys_get_temp_dir().'/wafiq-mail-'.uniqid();
        File::makeDirectory($this->dir);
        File::copy(base_path('deploy/env.example'), $this->dir.'/.env');
        $this->app->useEnvironmentPath($this->dir);

        $this->admin = $this->member(Role::Admin, ['email' => 'admin@example.com']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    private function env(): string
    {
        return (string) file_get_contents($this->dir.'/.env');
    }

    public function test_the_page_shows_the_settings_but_never_the_secrets(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'mail.example.com',
            'mail.mailers.smtp.password' => 'smtp-secret',
            'services.resend.key' => 're_secret123',
        ]);

        $response = $this->actingAs($this->admin)->get('/settings/mail');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Mail')
            ->where('mail.mailer', 'smtp')
            ->where('mail.configured', true)
            ->where('mail.host', 'mail.example.com')
            ->where('mail.has_password', true)
            ->where('mail.has_resend_key', true)
            ->where('testEmail', 'admin@example.com'));
        $this->assertStringNotContainsString('smtp-secret', $response->getContent());
        $this->assertStringNotContainsString('re_secret123', $response->getContent());
    }

    public function test_an_admin_connects_resend(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->put('/settings/mail', ['mailer' => 'resend', 'resend_key' => 're_abc_123', 'from_address' => 'sales@alitqan.example', 'from_name' => 'مؤسسة الإتقان'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $env = $this->env();
        $this->assertStringContainsString('MAIL_MAILER=resend', $env);
        $this->assertStringContainsString('RESEND_API_KEY=re_abc_123', $env);
        $this->assertStringContainsString('MAIL_FROM_ADDRESS=sales@alitqan.example', $env);
        $this->assertStringContainsString('MAIL_FROM_NAME="مؤسسة الإتقان"', $env);
        Mail::assertSentCount(0); // Mail::raw isn't a Mailable…
        $this->assertSame('resend', config('mail.default')); // …but the new settings were used for it
    }

    public function test_the_saved_key_is_kept_when_the_field_is_left_empty(): void
    {
        Mail::fake();
        config(['mail.default' => 'resend', 'services.resend.key' => 're_saved']);

        $this->actingAs($this->admin)
            ->put('/settings/mail', ['mailer' => 'resend', 'resend_key' => '', 'from_address' => 'new@alitqan.example'])
            ->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('RESEND_API_KEY=re_', $this->env());
        $this->assertStringContainsString('MAIL_FROM_ADDRESS=new@alitqan.example', $this->env());
    }

    public function test_resend_needs_a_valid_key(): void
    {
        $this->actingAs($this->admin)
            ->put('/settings/mail', ['mailer' => 'resend', 'resend_key' => '', 'from_address' => 'a@example.com'])
            ->assertSessionHasErrors('resend_key');

        $this->actingAs($this->admin)
            ->put('/settings/mail', ['mailer' => 'resend', 'resend_key' => 'not a key', 'from_address' => 'a@example.com'])
            ->assertSessionHasErrors('resend_key');
    }

    public function test_settings_that_cannot_send_are_not_saved(): void
    {
        $before = $this->env();

        // Nothing listens on port 1: the test message fails at once.
        $this->actingAs($this->admin)
            ->put('/settings/mail', ['mailer' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'from_address' => 'a@example.com'])
            ->assertSessionHasErrors('mailer');

        $this->assertSame($before, $this->env());
    }

    public function test_the_resend_transport_is_available(): void
    {
        config(['services.resend.key' => 're_test_key']);

        $mailer = app('mail.manager')->mailer('resend');

        $this->assertInstanceOf(ResendTransport::class, $mailer->getSymfonyTransport());
    }

    public function test_only_admins_and_only_self_hosted(): void
    {
        $this->actingAs($this->member(Role::Accountant))->get('/settings/mail')->assertForbidden();

        config(['edition.name' => Edition::SAAS]);
        $this->actingAs($this->admin)->get('/settings/mail')->assertNotFound();
    }
}
