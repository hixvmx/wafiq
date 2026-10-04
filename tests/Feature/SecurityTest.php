<?php

namespace Tests\Feature;

use App\Actions\SaveDocument;
use App\Enums\Role;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_sends_the_security_headers(): void
    {
        foreach (['/login', '/missing-page'] as $url) {
            $this->get($url)
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'same-origin');
        }

        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_session_cookies_are_secure_on_https(): void
    {
        $cookie = collect($this->get('https://localhost/login')->headers->getCookies())->first(fn ($c) => str_contains($c->getName(), 'session'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_the_client_page_does_not_reveal_internal_tracking(): void
    {
        $sales = $this->member(Role::Sales, ['name' => 'سارة']);
        $quote = app(SaveDocument::class)->handle(null, 'quote', [
            'client_id' => Client::factory()->create()->id, 'currency' => 'SAR', 'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(), 'discount_type' => 'percent', 'discount_value' => '',
            'notes' => null, 'terms' => null, 'lines' => [['name' => 'بند', 'qty' => '1', 'unit_price' => '100', 'discount' => '0', 'tax_rate_id' => null]],
        ], $sales);
        $token = basename($this->actingAs($sales)->postJson("/quotes/{$quote->id}/send", ['channel' => 'link'])->json('url'));
        $this->post('/logout');

        $this->get("/d/{$token}")->assertInertia(fn (Assert $page) => $page
            ->has('document.number')
            ->has('document.total')
            ->missing('document.views_count')
            ->missing('document.first_viewed_at')
            ->missing('document.last_viewed_at')
            ->missing('document.sent_at')
            ->missing('document.created_by')
            ->missing('document.not_viewed_warning'));
    }
}
