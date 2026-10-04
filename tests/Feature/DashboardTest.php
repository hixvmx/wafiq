<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 12:00');
        $this->owner = $this->member(Role::Owner);
        $this->sales = $this->member(Role::Sales);
    }

    private function doc(DocumentStatus $status, int $total, array $attributes = []): Document
    {
        return Document::factory()->create([
            'status' => $status,
            'total_minor' => $total,
            'created_by' => $this->sales->id,
            'sent_at' => $status === DocumentStatus::Draft ? null : now()->subDays(5),
            ...$attributes,
        ]);
    }

    public function test_pipeline_and_kpis(): void
    {
        $this->doc(DocumentStatus::Draft, 100000);
        $this->doc(DocumentStatus::Sent, 250000);
        $this->doc(DocumentStatus::Viewed, 150000, ['first_viewed_at' => now()->subDays(4)]);
        $this->doc(DocumentStatus::Viewed, 4000, ['currency' => 'USD', 'first_viewed_at' => now()]);
        $this->doc(DocumentStatus::Approved, 500000, ['sent_at' => now()->subDays(3), 'approved_at' => now()->subDays(1)]); // 48 h
        $this->doc(DocumentStatus::Approved, 300000, ['sent_at' => now()->subHours(30), 'approved_at' => now()->subHours(6)]); // 24 h
        $this->doc(DocumentStatus::Rejected, 70000);
        $this->doc(DocumentStatus::Approved, 999999, ['issue_date' => now()->subDays(200)->toDateString()]); // outside 90 days

        $this->actingAs($this->owner)->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('type', 'quote')
            ->where('period', '90')
            ->where('pipeline.draft.count', 1)
            ->where('pipeline.viewed.count', 2)
            ->where('pipeline.viewed.value', '1500.00')
            ->where('pipeline.viewed.others.0', ['currency' => 'USD', 'value' => '40.00'])
            ->where('pipeline.approved.count', 2)
            ->where('pipeline.approved.value', '8000.00')
            ->where('kpis.sent', 6)
            ->where('kpis.approved', 2)
            ->where('kpis.approval_rate', 33) // 2 of 6
            ->where('kpis.avg_hours_to_approval', 36) // (48 + 24) / 2
            ->where('kpis.waiting.count', 3)
            ->where('kpis.waiting.value', '4000.00')
            ->where('kpis.waiting.others.0.currency', 'USD'));

        $this->actingAs($this->owner)->get('/?period=all')->assertInertia(fn (Assert $page) => $page->where('pipeline.approved.count', 3));
    }

    public function test_needs_attention(): void
    {
        $notViewed = $this->doc(DocumentStatus::Sent, 1, ['sent_at' => now()->subDays(4)]);
        $this->doc(DocumentStatus::Sent, 1, ['sent_at' => now()->subDay()]); // too recent
        $noAnswer = $this->doc(DocumentStatus::Viewed, 1, ['first_viewed_at' => now()->subDays(3)]);
        $expiring = $this->doc(DocumentStatus::Viewed, 1, ['first_viewed_at' => now(), 'valid_until' => now()->addDay()->toDateString()]);
        $this->doc(DocumentStatus::Approved, 1, ['valid_until' => now()->addDay()->toDateString()]); // answered: no follow-up

        $this->actingAs($this->owner)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('attention.not_viewed.count', 1)
            ->where('attention.not_viewed.items.0.id', $notViewed->id)
            ->where('attention.no_answer.count', 1)
            ->where('attention.no_answer.items.0.id', $noAnswer->id)
            ->where('attention.expiring.count', 1)
            ->where('attention.expiring.items.0.id', $expiring->id));
    }

    public function test_sales_see_only_their_own_numbers(): void
    {
        $this->doc(DocumentStatus::Sent, 100000);
        $this->doc(DocumentStatus::Sent, 900000, ['created_by' => $this->owner->id]);

        $this->actingAs($this->sales)->get('/')->assertInertia(fn (Assert $page) => $page->where('pipeline.sent.count', 1)->where('kpis.waiting.value', '1000.00'));
        $this->actingAs($this->owner)->get('/')->assertInertia(fn (Assert $page) => $page->where('pipeline.sent.count', 2));
    }

    public function test_invoices_tab_and_empty_state(): void
    {
        $this->doc(DocumentStatus::Sent, 1);

        $this->actingAs($this->owner)->get('/?type=invoice')->assertInertia(fn (Assert $page) => $page
            ->where('type', 'invoice')
            ->where('pipeline.sent.count', 0)
            ->where('kpis.approval_rate', null)
            ->where('kpis.avg_hours_to_approval', null));
    }
}
