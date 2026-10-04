<?php

namespace Tests\Feature\Documents;

use App\Actions\SaveDocument;
use App\Enums\ActivityType;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use App\Notifications\MentionedInComment;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';

    private User $sales;

    private User $accountant;

    private Document $quote;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 10:00');
        $this->sales = $this->member(Role::Sales, ['name' => 'سارة']);
        $this->accountant = $this->member(Role::Accountant, ['name' => 'خالد المحاسب']);
        $this->quote = $this->makeQuote($this->sales);
    }

    private function makeQuote(User $user): Document
    {
        return app(SaveDocument::class)->handle(null, 'quote', [
            'client_id' => Client::factory()->create()->id, 'currency' => 'SAR', 'issue_date' => '2026-10-04', 'valid_until' => '2026-10-19',
            'discount_type' => 'percent', 'discount_value' => '', 'notes' => null, 'terms' => null,
            'lines' => [['name' => 'بند', 'qty' => '1', 'unit_price' => '100', 'discount' => '0', 'tax_rate_id' => null]],
        ], $user);
    }

    private function types(Document $document): array
    {
        return Activity::where('document_id', $document->id)->orderBy('id')->get()->map(fn ($a) => $a->type->value)->all();
    }

    public function test_the_timeline_records_team_actions_and_client_events(): void
    {
        $this->actingAs($this->sales)->put("/quotes/{$this->quote->id}", [
            'client_id' => $this->quote->client_id, 'currency' => 'SAR', 'issue_date' => '2026-10-04', 'valid_until' => '2026-10-19',
            'discount_type' => 'percent', 'lines' => [['name' => 'بند', 'qty' => '2', 'unit_price' => '100']],
        ]);
        $token = basename($this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", ['channel' => 'whatsapp', 'recipient' => '+966501234567', 'message' => 'x'])->json('url'));
        $this->post('/logout');
        $this->post("/d/{$token}/view", [], ['User-Agent' => self::BROWSER]);
        $this->post("/d/{$token}/approve", ['name' => 'العميل', 'agree' => '1']);

        $this->assertSame(['created', 'updated', 'sent', 'viewed', 'approved'], $this->types($this->quote));

        $sent = Activity::where('type', ActivityType::Sent)->sole();
        $this->assertSame($this->sales->id, $sent->user_id);
        $this->assertSame(['channel' => 'whatsapp', 'recipient' => '+966501234567'], $sent->data);
        $this->assertSame('desktop', Activity::where('type', ActivityType::Viewed)->sole()->data['device']);
        $this->assertNull(Activity::where('type', ActivityType::Approved)->sole()->user_id); // the client, not a member

        $this->actingAs($this->sales)->get("/quotes/{$this->quote->id}")->assertInertia(fn (Assert $page) => $page
            ->has('feed', 5)
            ->where('feed.0.type', 'created')
            ->where('feed.4.type', 'approved')
            ->where('feed.4.data.name', 'العميل')
            ->where('feed.4.client_event', true));
    }

    public function test_copies_and_conversions_are_on_the_timeline(): void
    {
        $this->quote->update(['status' => DocumentStatus::Approved, 'sent_at' => now()]);

        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/convert");
        $invoice = Document::where('type', 'invoice')->sole();

        $this->assertContains('converted', $this->types($this->quote));
        $this->assertSame(['created_from_quote'], $this->types($invoice));
        $this->assertSame('QT-2026-0001', Activity::where('document_id', $invoice->id)->sole()->data['quote']);
    }

    public function test_the_expiry_job_logs_without_a_current_company(): void
    {
        $this->quote->update(['status' => DocumentStatus::Sent, 'sent_at' => now()]);
        Carbon::setTestNow('2026-10-25');
        app(CurrentCompany::class)->set(null);

        $this->artisan('wafiq:expire-documents')->assertSuccessful();

        $activity = Activity::withoutGlobalScopes()->where('type', ActivityType::Expired)->sole();
        $this->assertSame($this->quote->company_id, $activity->company_id);
    }

    public function test_a_mention_notifies_in_the_app_and_by_email(): void
    {
        Notification::fake();

        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/comments", [
            'body' => '@خالد المحاسب هل الأسعار صحيحة؟',
            'mentions' => [$this->accountant->id],
        ])->assertRedirect();

        $comment = Comment::sole();
        $this->assertSame([$this->accountant->id], $comment->mentions);

        Notification::assertSentTo($this->accountant, MentionedInComment::class, function ($notification, $channels) use ($comment) {
            $data = $notification->toArray($this->accountant);

            return $channels === ['database', 'mail']
                && $data['author'] === 'سارة'
                && $data['company_id'] === $this->quote->company_id
                && str_ends_with($data['url'], "/quotes/{$this->quote->id}#comment-{$comment->id}");
        });
    }

    public function test_only_real_mentions_of_people_who_can_see_the_document_notify(): void
    {
        Notification::fake();
        $otherSales = $this->member(Role::Sales, ['name' => 'فهد']); // can't see Sara's quote
        $stranger = User::factory()->create(['name' => 'غريب']);

        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/comments", [
            'body' => 'تعليق @فهد @غريب @سارة بدون ذكر خالد',
            'mentions' => [$this->accountant->id, $otherSales->id, $stranger->id, $this->sales->id],
        ]);

        Notification::assertNothingSent();
        $this->assertNull(Comment::sole()->mentions);
    }

    public function test_who_can_comment_and_delete(): void
    {
        $other = $this->member(Role::Sales);
        $this->actingAs($other)->post("/quotes/{$this->quote->id}/comments", ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->member(Role::Viewer))->post("/quotes/{$this->quote->id}/comments", ['body' => 'ملاحظة'])->assertRedirect();
        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/comments", ['body' => ''])->assertSessionHasErrors('body');

        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/comments", ['body' => 'تعليقي']);
        $mine = Comment::where('body', 'تعليقي')->sole();

        $this->actingAs($this->accountant)->delete("/comments/{$mine->id}")->assertForbidden();
        $this->actingAs($this->member(Role::Admin))->delete("/comments/{$mine->id}")->assertRedirect();
        $this->assertModelMissing($mine);
    }

    public function test_the_bell_shows_this_companys_notifications(): void
    {
        $this->actingAs($this->sales)->post("/quotes/{$this->quote->id}/comments", ['body' => '@خالد المحاسب انظر', 'mentions' => [$this->accountant->id]]);

        // A notification from another company must not show up here.
        $this->accountant->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => MentionedInComment::class,
            'data' => ['kind' => 'mention', 'company_id' => Company::factory()->create()->id, 'url' => '/x'],
        ]);

        $this->actingAs($this->accountant)->get('/quotes')->assertInertia(fn (Assert $page) => $page->where('auth.unread_notifications', 1));

        $notification = $this->accountant->notifications()->where('data->company_id', $this->quote->company_id)->sole();
        $this->actingAs($this->accountant)->get('/notifications')->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.data.number', 'QT-2026-0001'));

        $this->actingAs($this->accountant)->get("/notifications/{$notification->id}")->assertRedirect($notification->data['url']);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($this->accountant)->get('/quotes')->assertInertia(fn (Assert $page) => $page->where('auth.unread_notifications', 0));
    }
}
