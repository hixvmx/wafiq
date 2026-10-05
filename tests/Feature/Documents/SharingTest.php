<?php

namespace Tests\Feature\Documents;

use App\Actions\SaveDocument;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Mail\DocumentMail;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentSend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class SharingTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

    private User $sales;

    private Document $quote;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 10:00');
        $this->sales = $this->member(Role::Sales);
        $this->company()->update(['name' => 'شركة الإتقان']);
        $client = Client::factory()->create(['name' => 'مؤسسة النخبة', 'contact_name' => 'خالد', 'phone' => '+966501234567', 'email' => 'khalid@example.com']);

        $this->quote = app(SaveDocument::class)->handle(null, 'quote', [
            'client_id' => $client->id, 'currency' => 'SAR', 'issue_date' => '2026-10-04', 'valid_until' => '2026-10-19',
            'discount_type' => 'percent', 'discount_value' => '', 'notes' => null, 'terms' => null,
            'lines' => [['name' => 'تركيب', 'qty' => '1', 'unit_price' => '12500', 'discount' => '0', 'tax_rate_id' => null]],
        ], $this->sales);
    }

    /** Sends by link as the sales member and returns the token. */
    private function sendLink(?Document $document = null): string
    {
        $document ??= $this->quote;
        $url = $this->actingAs($this->sales)->postJson("/quotes/{$document->id}/send", ['channel' => 'link'])->assertCreated()->json('url');
        $this->post('/logout');

        return basename($url);
    }

    public function test_the_send_dialog_starts_from_the_company_templates(): void
    {
        $this->actingAs($this->sales)->get("/quotes/{$this->quote->id}")->assertInertia(fn (Assert $page) => $page
            ->where('can.send', true)
            ->where('sendDefaults.whatsapp.phone', '+966501234567')
            ->where('sendDefaults.whatsapp.message', fn ($message) => str_contains($message, 'مرحباً خالد')
                && str_contains($message, 'QT-2026-0001')
                && str_contains($message, '12,500.00 ريال سعودي')
                && str_contains($message, '{link}')
                && str_contains($message, '19 أكتوبر 2026'))
            ->where('sendDefaults.email.to', 'khalid@example.com'));
    }

    public function test_send_by_whatsapp(): void
    {
        $response = $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", [
            'channel' => 'whatsapp',
            'recipient' => '+966 50 123 4567',
            'message' => "مرحباً خالد\n{link}",
        ])->assertCreated();

        $whatsapp = $response->json('whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/966501234567?text=', $whatsapp);
        $this->assertStringContainsString($response->json('url'), rawurldecode($whatsapp));

        $send = DocumentSend::sole();
        $this->assertSame('whatsapp', $send->channel);
        $this->assertSame('+966501234567', $send->recipient);
        $this->assertSame(hash('sha256', basename($response->json('url'))), $send->token_hash);

        $quote = $this->quote->fresh();
        $this->assertSame(DocumentStatus::Sent, $quote->status);
        $this->assertNotNull($quote->sent_at);
        $this->assertSame('مؤسسة النخبة', $quote->client_snapshot['name']);
        $this->assertSame('شركة الإتقان', $quote->company_snapshot['name']);
    }

    public function test_the_link_is_added_when_the_message_has_none(): void
    {
        $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", ['channel' => 'whatsapp', 'recipient' => '+966501234567', 'message' => 'مرحباً'])->assertCreated();

        $this->assertMatchesRegularExpression('#^مرحباً\nhttp.+/d/\w{64}$#u', DocumentSend::sole()->message);
    }

    public function test_send_by_email(): void
    {
        Mail::fake();

        $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", [
            'channel' => 'email', 'recipient' => 'Khalid@Example.com', 'subject' => 'عرض سعر {number}', 'message' => "مرحباً\n{link}",
        ])->assertCreated()->assertJsonPath('email_failed', false);

        $send = DocumentSend::sole();
        $this->assertSame('sent', $send->email_status);
        Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('khalid@example.com') && str_contains($mail->body, '/d/') && str_contains($mail->url, '/d/'));
    }

    public function test_a_failed_email_is_recorded_not_thrown(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", [
            'channel' => 'email', 'recipient' => 'khalid@example.com', 'subject' => 'x', 'message' => 'y',
        ])->assertCreated()->assertJsonPath('email_failed', true);

        $this->assertSame('failed', DocumentSend::sole()->email_status);
    }

    public function test_validation_and_permissions(): void
    {
        $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", ['channel' => 'whatsapp', 'recipient' => '123', 'message' => 'x'])->assertJsonValidationErrors('recipient');
        $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", ['channel' => 'email', 'recipient' => 'nope', 'subject' => '', 'message' => 'x'])->assertJsonValidationErrors(['recipient', 'subject']);
        $this->actingAs($this->member(Role::Viewer))->postJson("/quotes/{$this->quote->id}/send", ['channel' => 'link'])->assertForbidden();

        $this->quote->update(['status' => DocumentStatus::Approved]);
        $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", ['channel' => 'link'])->assertForbidden();
    }

    public function test_every_send_has_its_own_link(): void
    {
        $first = $this->sendLink();
        $second = $this->sendLink();

        $this->assertNotSame($first, $second);
        $this->assertSame(2, DocumentSend::count());
    }

    public function test_the_client_page_and_its_link_preview(): void
    {
        $token = $this->sendLink();

        $this->get("/d/{$token}")
            ->assertOk()
            ->assertSee('<meta property="og:title" content="عرض سعر QT-2026-0001 — شركة الإتقان">', false)
            ->assertSee('<meta property="og:description" content="12,500.00 SAR">', false)
            ->assertSee('noindex', false)
            ->assertDontSee('og:title" content="مؤسسة', false);

        $this->get("/d/{$token}")->assertInertia(fn (Assert $page) => $page->component('Public/Document'));
        $this->get('/d/'.str_repeat('x', 64))->assertNotFound();

        // Just opening the page (what preview robots do) isn't a view.
        $this->assertSame(DocumentStatus::Sent, $this->quote->fresh()->status);
    }

    public function test_a_real_view_marks_the_document_viewed(): void
    {
        $token = $this->sendLink();

        $this->post("/d/{$token}/view", [], ['User-Agent' => self::BROWSER])->assertNoContent();

        $quote = $this->quote->fresh();
        $send = DocumentSend::sole();
        $this->assertSame(DocumentStatus::Viewed, $quote->status);
        $this->assertSame(1, $quote->views_count);
        $this->assertNotNull($quote->first_viewed_at);
        $this->assertSame(1, $send->views_count);
        $this->assertSame('mobile', $send->views()->sole()->device);
    }

    public function test_robots_team_members_and_quick_reloads_are_not_counted(): void
    {
        $token = $this->sendLink();

        foreach (['WhatsApp/2.23.20.0', 'facebookexternalhit/1.1', 'TelegramBot (like TwitterBot)', 'Mozilla/5.0 (compatible; Googlebot/2.1)', ''] as $robot) {
            $this->post("/d/{$token}/view", [], ['User-Agent' => $robot]);
        }
        $this->actingAs($this->sales)->post("/d/{$token}/view", [], ['User-Agent' => self::BROWSER]);
        $this->post('/logout');
        $this->assertSame(DocumentStatus::Sent, $this->quote->fresh()->status);

        $this->post("/d/{$token}/view", [], ['User-Agent' => self::BROWSER]);
        $this->post("/d/{$token}/view", [], ['User-Agent' => self::BROWSER]); // reload
        $this->assertSame(1, $this->quote->fresh()->views_count);

        $this->travel(11)->minutes();
        $this->post("/d/{$token}/view", [], ['User-Agent' => self::BROWSER]);
        $this->assertSame(2, $this->quote->fresh()->views_count);
    }

    public function test_the_client_approves_once(): void
    {
        $token = $this->sendLink();

        $this->post("/d/{$token}/approve", ['name' => ''])->assertSessionHasErrors(['name', 'agree']);

        $this->post("/d/{$token}/approve", ['name' => 'خالد العتيبي', 'agree' => '1'], ['User-Agent' => self::BROWSER])->assertSessionHas('success');

        $quote = $this->quote->fresh();
        $this->assertSame(DocumentStatus::Approved, $quote->status);
        $this->assertSame('خالد العتيبي', $quote->approved_by_name);
        $this->assertNotNull($quote->approved_ip);
        $this->assertSame(self::BROWSER, $quote->approved_user_agent);

        // A second click (or a reject afterwards) changes nothing.
        $this->post("/d/{$token}/approve", ['name' => 'آخر', 'agree' => '1'])->assertSessionHas('error');
        $this->post("/d/{$token}/reject", ['reason' => 'x'])->assertSessionHas('error');
        $this->assertSame('خالد العتيبي', $quote->fresh()->approved_by_name);

        $this->get("/d/{$token}")->assertInertia(fn (Assert $page) => $page->where('state', 'approved')->where('canRespond', false));
    }

    public function test_the_client_rejects_with_a_reason(): void
    {
        $token = $this->sendLink();

        $this->post("/d/{$token}/reject", ['reason' => 'السعر مرتفع', 'details' => 'وجدنا عرضاً أقل'])->assertSessionHas('success');

        $quote = $this->quote->fresh();
        $this->assertSame(DocumentStatus::Rejected, $quote->status);
        $this->assertSame('السعر مرتفع — وجدنا عرضاً أقل', $quote->rejection_reason);
    }

    public function test_team_members_cannot_answer_for_the_client(): void
    {
        $token = $this->sendLink();

        $this->actingAs($this->sales)->post("/d/{$token}/approve", ['name' => 'x', 'agree' => '1'])->assertForbidden();
        $this->actingAs($this->sales)->get("/d/{$token}")->assertInertia(fn (Assert $page) => $page->where('isTeam', true)->where('canRespond', false));
    }

    public function test_an_expired_quote_cannot_be_approved_until_extended(): void
    {
        $token = $this->sendLink();
        Carbon::setTestNow('2026-10-20 09:00');

        // The page expires it on the spot, even without the scheduler.
        $this->get("/d/{$token}")->assertInertia(fn (Assert $page) => $page->where('state', 'expired'));
        $this->post("/d/{$token}/approve", ['name' => 'خالد', 'agree' => '1'])->assertSessionHas('error');
        $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", ['channel' => 'link'])->assertForbidden();

        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/extend", ['valid_until' => '2026-10-10'])->assertSessionHasErrors('valid_until');
        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/extend", ['valid_until' => '2026-11-01'])->assertSessionHas('success');
        $this->assertSame(DocumentStatus::Sent, $this->quote->fresh()->status);

        $this->post('/logout');
        $this->post("/d/{$token}/approve", ['name' => 'خالد', 'agree' => '1'])->assertSessionHas('success');
    }

    public function test_the_scheduler_expires_overdue_documents(): void
    {
        $this->sendLink();
        $draft = Document::factory()->create(['valid_until' => '2026-10-01']);
        Carbon::setTestNow('2026-10-20');

        $this->artisan('wafiq:expire-documents')->expectsOutputToContain('1 document(s) expired')->assertSuccessful();

        $this->assertSame(DocumentStatus::Expired, $this->quote->fresh()->status);
        $this->assertSame(DocumentStatus::Draft, $draft->fresh()->status); // drafts never expire
    }

    public function test_an_old_revision_link_points_to_the_newest_sent_version(): void
    {
        $oldToken = $this->sendLink();

        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/revise");
        $v2 = Document::latest('id')->first();
        $this->post('/logout');

        // v2 is still a draft: the v1 link works as before.
        $this->get("/d/{$oldToken}")->assertInertia(fn (Assert $page) => $page->where('state', 'sent'));

        $this->sendLink($v2);

        $this->get("/d/{$oldToken}")->assertInertia(fn (Assert $page) => $page->where('state', 'replaced')->where('canRespond', false));
        $this->post("/d/{$oldToken}/approve", ['name' => 'خالد', 'agree' => '1'])->assertSessionHas('error');

        $redirect = $this->post("/d/{$oldToken}/latest")->assertRedirect()->headers->get('Location');
        $this->get($redirect)->assertInertia(fn (Assert $page) => $page->where('document.number', 'QT-2026-0001-v2')->where('state', 'sent'));
    }

    public function test_the_document_page_shows_the_tracking_timeline(): void
    {
        $token = $this->sendLink();
        $this->post("/d/{$token}/view", [], ['User-Agent' => self::BROWSER]);

        $this->actingAs($this->sales)->get("/quotes/{$this->quote->id}")->assertInertia(fn (Assert $page) => $page
            ->where('document.status', 'viewed')
            ->has('sends', 1)
            ->where('sends.0.channel', 'link')
            ->where('sends.0.views_count', 1)
            ->where('sends.0.sent_by', $this->sales->name));
    }
}
