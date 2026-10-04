<?php

namespace Tests\Feature\Documents;

use App\Actions\SaveDocument;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private Client $client;

    private TaxRate $vat;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 10:00');
        $this->sales = $this->member(Role::Sales);
        $this->client = Client::factory()->create(['name' => 'مؤسسة النخبة']);
        $this->vat = TaxRate::create(['name' => 'ضريبة القيمة المضافة', 'rate' => 15, 'is_default' => true]);
    }

    /** A valid quote form; override any field. */
    private function quoteData(array $overrides = []): array
    {
        return [
            'client_id' => $this->client->id,
            'currency' => 'SAR',
            'issue_date' => '2026-10-04',
            'valid_until' => '2026-10-19',
            'discount_type' => 'percent',
            'discount_value' => '10',
            'notes' => 'شكراً',
            'terms' => 'الشروط',
            'lines' => [
                ['name' => 'تركيب أرضيات', 'qty' => '100', 'unit' => 'م²', 'unit_price' => '85', 'discount' => '', 'tax_rate_id' => $this->vat->id],
                ['name' => 'زيارة فنية', 'qty' => '١', 'unit_price' => '1,500', 'discount' => '0', 'tax_rate_id' => null],
            ],
            ...$overrides,
        ];
    }

    private function createQuote(?User $user = null, array $overrides = []): Document
    {
        $this->actingAs($user ?? $this->sales)->post('/quotes', $this->quoteData($overrides))->assertSessionHasNoErrors();

        return Document::latest('id')->first();
    }

    public function test_sales_creates_a_quote_and_the_server_calculates_totals(): void
    {
        $quote = $this->createQuote();

        $this->assertSame('QT-2026-0001', $quote->number);
        $this->assertSame(DocumentStatus::Draft, $quote->status);
        $this->assertSame($this->sales->id, $quote->created_by);
        $this->assertSame($quote->id, $quote->root_id);

        // 100 × 85 = 8500.00 (VAT 15%) + 1500.00 (no tax) = 10000.00; 10% off = 1000.00 spread 850 / 150.
        // VAT on 8500 − 850 = 7650 → 1147.50. Total 9000 + 1147.50 = 10147.50.
        $this->assertSame(1000000, $quote->subtotal_minor);
        $this->assertSame(100000, $quote->discount_minor);
        $this->assertSame(114750, $quote->tax_minor);
        $this->assertSame(1014750, $quote->total_minor);

        $lines = $quote->lines;
        $this->assertCount(2, $lines);
        $this->assertSame('ضريبة القيمة المضافة', $lines[0]->tax_name);
        $this->assertSame('1', SaveDocument::plainDecimal($lines[1]->qty)); // Arabic digit ١ accepted (MySQL says 1.000, SQLite 1)
        $this->assertSame(150000, $lines[1]->unit_price_minor); // "1,500" accepted
    }

    public function test_totals_sent_by_the_browser_are_ignored(): void
    {
        $quote = $this->createQuote(overrides: ['total_minor' => 1, 'subtotal_minor' => 1]);

        $this->assertSame(1014750, $quote->total_minor);
    }

    public function test_validation(): void
    {
        $mine = $this->company();
        app(CurrentCompany::class)->set(Company::factory()->create());
        $foreignClient = Client::factory()->create();
        app(CurrentCompany::class)->set($mine);

        $this->actingAs($this->sales)->post('/quotes', $this->quoteData([
            'client_id' => $foreignClient->id,
            'valid_until' => '2026-10-01',
            'lines' => [['name' => '', 'qty' => '0', 'unit_price' => '1.005']],
        ]))->assertSessionHasErrors(['client_id', 'valid_until', 'lines.0.name', 'lines.0.qty', 'lines.0.unit_price']);

        $this->actingAs($this->sales)->post('/quotes', $this->quoteData(['lines' => []]))->assertSessionHasErrors('lines');
        $this->actingAs($this->sales)->post('/quotes', $this->quoteData(['discount_value' => '120']))->assertSessionHasErrors('discount_value');

        $this->assertSame(0, Document::count());
    }

    public function test_a_fixed_document_discount(): void
    {
        $quote = $this->createQuote(overrides: ['discount_type' => 'amount', 'discount_value' => '500.50']);

        $this->assertSame(50050, $quote->discount_minor);
        $this->assertSame('500.5', SaveDocument::plainDecimal($quote->discount_value));

        // Saving again reads the stored "500.500" back correctly.
        $this->actingAs($this->sales)->put("/quotes/{$quote->id}", $this->quoteData(['discount_type' => 'amount', 'discount_value' => '500.50']))->assertSessionHasNoErrors();
        $this->assertSame(50050, $quote->fresh()->discount_minor);
    }

    public function test_sales_cannot_start_an_invoice_but_the_accountant_can(): void
    {
        $this->actingAs($this->sales)->get('/invoices/create')->assertForbidden();
        $this->actingAs($this->sales)->post('/invoices', $this->quoteData(['due_date' => '2026-11-03']))->assertForbidden();

        $accountant = $this->member(Role::Accountant);
        $this->actingAs($accountant)->post('/invoices', $this->quoteData(['due_date' => '2026-11-03']))->assertSessionHasNoErrors();

        $invoice = Document::sole();
        $this->assertSame('invoice', $invoice->type);
        $this->assertSame('INV-2026-0001', $invoice->number);
        $this->assertNull($invoice->valid_until);
    }

    public function test_drafts_are_edited_in_place_and_recalculated(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->sales)->put("/quotes/{$quote->id}", $this->quoteData([
            'discount_value' => '',
            'lines' => [['name' => 'بند واحد', 'qty' => '2', 'unit_price' => '50', 'tax_rate_id' => $this->vat->id]],
        ]))->assertRedirect("/quotes/{$quote->id}");

        $quote->refresh();
        $this->assertSame('QT-2026-0001', $quote->number);
        $this->assertCount(1, $quote->lines);
        $this->assertSame(11500, $quote->total_minor);
    }

    public function test_a_sent_document_is_not_edited_but_revised(): void
    {
        $quote = $this->createQuote();
        $quote->update(['status' => DocumentStatus::Sent, 'sent_at' => now()]);

        $this->actingAs($this->sales)->get("/quotes/{$quote->id}/edit")->assertForbidden();
        $this->actingAs($this->sales)->put("/quotes/{$quote->id}", $this->quoteData())->assertForbidden();

        $this->actingAs($this->sales)->post("/quotes/{$quote->id}/revise")->assertRedirect();

        $v2 = Document::latest('id')->first();
        $this->assertSame('QT-2026-0001', $v2->number);
        $this->assertSame(2, $v2->revision);
        $this->assertSame('QT-2026-0001-v2', $v2->displayNumber());
        $this->assertSame(DocumentStatus::Draft, $v2->status);
        $this->assertSame($quote->id, $v2->parent_id);
        $this->assertSame($quote->root_id, $v2->root_id);
        $this->assertCount(2, $v2->lines);
        $this->assertSame($quote->total_minor, $v2->total_minor);
        $this->assertFalse($quote->fresh()->is_latest);

        // Lists show the latest revision only; v1 can't be revised again.
        $this->actingAs($this->sales)->get('/quotes')->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.number', 'QT-2026-0001-v2'));
        $this->actingAs($this->sales)->post("/quotes/{$quote->id}/revise")->assertForbidden();

        // Deleting the v2 draft makes v1 current again.
        $this->actingAs($this->sales)->delete("/quotes/{$v2->id}")->assertRedirect("/quotes/{$quote->id}");
        $this->assertTrue($quote->fresh()->is_latest);
    }

    public function test_duplicate_gets_a_new_number_and_fresh_dates(): void
    {
        $quote = $this->createQuote();
        Carbon::setTestNow('2026-10-10');

        $this->actingAs($this->sales)->post("/quotes/{$quote->id}/duplicate");

        $copy = Document::latest('id')->first();
        $this->assertSame('QT-2026-0002', $copy->number);
        $this->assertSame('2026-10-10', $copy->issue_date->toDateString());
        $this->assertSame('2026-10-25', $copy->valid_until->toDateString()); // default 15 days
        $this->assertSame($quote->total_minor, $copy->total_minor);
    }

    public function test_an_approved_quote_becomes_one_invoice(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->sales)->post("/quotes/{$quote->id}/convert")->assertForbidden(); // still a draft

        $quote->update(['status' => DocumentStatus::Approved]);
        $this->actingAs($this->sales)->post("/quotes/{$quote->id}/convert")->assertRedirect();

        $invoice = Document::where('type', 'invoice')->sole();
        $this->assertSame('INV-2026-0001', $invoice->number);
        $this->assertSame($quote->id, $invoice->quote_id);
        $this->assertSame($this->sales->id, $invoice->created_by);
        $this->assertSame($quote->total_minor, $invoice->total_minor);
        $this->assertSame('2026-11-03', $invoice->due_date->toDateString()); // default 30 days

        // Converting again opens the same invoice.
        $this->actingAs($this->sales)->post("/quotes/{$quote->id}/convert")->assertRedirect("/invoices/{$invoice->id}");
        $this->assertSame(1, Document::where('type', 'invoice')->count());

        // The sales member can edit the invoice made from their quote.
        $this->actingAs($this->sales)->get("/invoices/{$invoice->id}/edit")->assertOk();
    }

    public function test_sales_see_only_their_own_documents(): void
    {
        $other = $this->member(Role::Sales);
        $theirs = $this->createQuote($other);
        $mine = $this->createQuote();

        $this->actingAs($this->sales)->get('/quotes')->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.id', $mine->id));
        $this->actingAs($this->sales)->get("/quotes/{$theirs->id}")->assertForbidden();
        $this->actingAs($this->sales)->post("/quotes/{$theirs->id}/duplicate")->assertForbidden();

        $viewer = $this->member(Role::Viewer);
        $this->actingAs($viewer)->get('/quotes')->assertInertia(fn (Assert $page) => $page->has('documents.data', 2)->where('canCreate', false));
        $this->actingAs($viewer)->get("/quotes/{$theirs->id}")->assertOk();
        $this->actingAs($viewer)->get('/quotes/create')->assertForbidden();
    }

    public function test_list_filters(): void
    {
        $draft = $this->createQuote();
        $old = $this->createQuote();
        $old->update(['status' => DocumentStatus::Sent, 'sent_at' => now()->subDays(4)]);
        $recent = $this->createQuote();
        $recent->update(['status' => DocumentStatus::Sent, 'sent_at' => now()->subDay()]);

        $this->actingAs($this->sales)->get('/quotes?status=sent')->assertInertia(fn (Assert $page) => $page->has('documents.data', 2)->where('counts.sent', 2)->where('counts.draft', 1));
        $this->actingAs($this->sales)->get('/quotes?not_viewed=1')->assertInertia(fn (Assert $page) => $page
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $old->id)
            ->where('documents.data.0.not_viewed_warning', true));
        $this->actingAs($this->sales)->get('/quotes?q=النخبة')->assertInertia(fn (Assert $page) => $page->has('documents.data', 3));
        $this->actingAs($this->sales)->get('/quotes?q=0001')->assertInertia(fn (Assert $page) => $page->has('documents.data', 1)->where('documents.data.0.id', $draft->id));
    }

    public function test_document_page_shows_totals_tax_breakdown_and_words(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->sales)->get("/quotes/{$quote->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Show')
            ->where('document.number', 'QT-2026-0001')
            ->where('document.total', '10147.50')
            ->where('document.discount_label', '10%')
            ->where('document.tax_breakdown.0.amount', '1147.50')
            ->where('document.lines.0.qty', '100')
            ->where('document.amount_in_words', 'فقط عشرة آلاف ومئة وسبعة وأربعون ريالاً سعودياً وخمسون هللة لا غير')
            ->where('can.update', true)
            ->where('can.revise', false));
    }

    public function test_delete_rules(): void
    {
        $quote = $this->createQuote();
        $quote->update(['status' => DocumentStatus::Sent, 'sent_at' => now()]);

        $this->actingAs($this->sales)->delete("/quotes/{$quote->id}")->assertForbidden();
        $this->actingAs($this->member(Role::Accountant))->delete("/quotes/{$quote->id}")->assertForbidden();
        $this->actingAs($this->member(Role::Owner))->delete("/quotes/{$quote->id}")->assertRedirect('/quotes');

        $this->assertModelMissing($quote);
    }

    public function test_wrong_type_and_other_companies_are_not_found(): void
    {
        $quote = $this->createQuote();
        $this->actingAs($this->sales)->get("/invoices/{$quote->id}")->assertNotFound();

        $mine = $this->company();
        app(CurrentCompany::class)->set(Company::factory()->create());
        $foreign = Document::factory()->create();
        app(CurrentCompany::class)->set($mine);

        $owner = $this->member(Role::Owner);
        $this->actingAs($owner)->get("/quotes/{$foreign->id}")->assertNotFound();
    }

    public function test_editor_pages_render(): void
    {
        $quote = $this->createQuote();

        $this->actingAs($this->sales)->get("/quotes/create?client_id={$this->client->id}")->assertInertia(fn (Assert $page) => $page
            ->component('Documents/Form')
            ->where('values.client.id', $this->client->id)
            ->where('values.valid_until', '2026-10-19')
            ->where('values.terms', $this->company()->preferences()->get('documents.quote.terms')));

        $this->actingAs($this->sales)->get("/quotes/{$quote->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('values.lines.1.unit_price', '1500.00')
            ->where('values.discount_value', '10'));
    }
}
