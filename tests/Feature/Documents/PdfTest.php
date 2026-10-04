<?php

namespace Tests\Feature\Documents;

use App\Actions\SaveDocument;
use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Mail\DocumentMail;
use App\Models\Client;
use App\Models\Document;
use App\Models\User;
use App\Services\PdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    private Document $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = $this->member(Role::Sales);
        $this->quote = $this->makeQuote(2);
    }

    private function makeQuote(int $lines): Document
    {
        return app(SaveDocument::class)->handle(null, 'quote', [
            'client_id' => Client::factory()->create(['name' => 'مؤسسة النخبة'])->id,
            'currency' => 'SAR', 'issue_date' => now()->toDateString(), 'valid_until' => now()->addDays(15)->toDateString(),
            'discount_type' => 'percent', 'discount_value' => '5', 'notes' => 'شكراً', 'terms' => "شرط أول\nشرط ثانٍ",
            'lines' => array_map(fn ($i) => ['name' => "بند رقم {$i}", 'description' => 'وصف البند', 'qty' => '2.5', 'unit' => 'م²', 'unit_price' => '85.50', 'discount' => '0', 'tax_rate_id' => null], range(1, $lines)),
        ], $this->sales);
    }

    private function assertPdf(string $content): void
    {
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', $content);
    }

    public function test_the_team_downloads_the_pdf(): void
    {
        $response = $this->actingAs($this->sales)->get("/quotes/{$this->quote->id}/pdf")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="QT-'.now()->year.'-0001.pdf"');

        $this->assertPdf($response->getContent());
    }

    public function test_only_people_who_may_see_the_document_get_its_pdf(): void
    {
        $this->actingAs($this->member(Role::Sales))->get("/quotes/{$this->quote->id}/pdf")->assertForbidden();
        $this->actingAs($this->sales)->get("/invoices/{$this->quote->id}/pdf")->assertNotFound();
        $this->post('/logout');
        $this->get("/quotes/{$this->quote->id}/pdf")->assertRedirect('/login');
    }

    public function test_the_client_downloads_the_pdf_without_it_counting_as_a_view(): void
    {
        $url = $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", ['channel' => 'link'])->json('url');
        $this->post('/logout');

        $response = $this->get(parse_url($url, PHP_URL_PATH).'/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->assertPdf($response->getContent());
        $this->assertSame(DocumentStatus::Sent, $this->quote->fresh()->status);
        $this->get('/d/'.str_repeat('x', 64).'/pdf')->assertNotFound();
    }

    public function test_long_documents_with_images_and_an_approval_render(): void
    {
        Storage::fake('local');
        $admin = $this->member(Role::Admin);
        foreach (['logo', 'stamp', 'signature'] as $kind) {
            $this->actingAs($admin)->post("/settings/branding/{$kind}", ['image' => UploadedFile::fake()->image("{$kind}.png", 300, 120)]);
        }

        $long = $this->makeQuote(60);
        $long->update(['status' => DocumentStatus::Approved, 'sent_at' => now(), 'approved_at' => now(), 'approved_by_name' => 'خالد', 'approved_ip' => '5.1.2.3']);

        $content = app(PdfRenderer::class)->render($long->fresh());

        $this->assertPdf($content);
        $this->assertGreaterThanOrEqual(2, preg_match_all('#/Type\s*/Page[^s]#', $content)); // 60 lines → several pages
    }

    public function test_the_pdf_can_be_attached_to_the_email(): void
    {
        Mail::fake();

        $this->actingAs($this->sales)->postJson("/quotes/{$this->quote->id}/send", [
            'channel' => 'email', 'recipient' => 'client@example.com', 'subject' => 'عرض', 'message' => 'مرحباً', 'attach_pdf' => true,
        ])->assertCreated();

        Mail::assertSent(DocumentMail::class, function (DocumentMail $mail) {
            $attachments = $mail->attachments();

            return count($attachments) === 1 && $mail->attachPdf;
        });
    }
}
