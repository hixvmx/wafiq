<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->member(Role::Admin);
    }

    /** @return iterable<string, array{string, string}> */
    public static function pages(): iterable
    {
        yield 'company' => ['/settings/company', 'Settings/Company'];
        yield 'branding' => ['/settings/branding', 'Settings/Branding'];
        yield 'taxes' => ['/settings/taxes', 'Settings/Taxes'];
        yield 'numbering' => ['/settings/numbering', 'Settings/Numbering'];
        yield 'documents' => ['/settings/documents', 'Settings/Documents'];
        yield 'messages' => ['/settings/messages', 'Settings/Messages'];
    }

    #[DataProvider('pages')]
    public function test_admins_open_every_settings_page(string $url, string $component): void
    {
        $this->actingAs($this->admin)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    }

    #[DataProvider('pages')]
    public function test_other_roles_cannot_open_settings(string $url): void
    {
        foreach ([Role::Accountant, Role::Sales, Role::Viewer] as $role) {
            $this->actingAs($this->member($role))->get($url)->assertForbidden();
        }
    }

    public function test_company_profile_and_bank_details(): void
    {
        $this->actingAs($this->admin)->put('/settings/company', [
            'name' => 'شركة الإتقان',
            'vat_number' => '300000000000003',
            'email' => 'info@alitqan.sa',
            'bank_details' => "مصرف الراجحي\nSA0380000000608010167519",
        ])->assertSessionHas('success');

        $company = $this->company()->fresh();
        $this->assertSame('شركة الإتقان', $company->name);
        $this->assertSame('300000000000003', $company->vat_number);
        $this->assertStringContainsString('SA0380000000608010167519', $company->preferences()->get('bank_details'));

        $this->actingAs($this->admin)->put('/settings/company', ['name' => '', 'email' => 'not-an-email'])->assertSessionHasErrors(['name', 'email']);
    }

    public function test_document_defaults(): void
    {
        $this->actingAs($this->admin)->put('/settings/documents', [
            'quote' => ['validity_days' => 30, 'terms' => 'شروط', 'notes' => null],
            'invoice' => ['due_days' => 0, 'terms' => '', 'notes' => 'شكراً لتعاملكم'],
        ])->assertSessionHas('success');

        $settings = $this->company()->fresh()->preferences();
        $this->assertSame(30, $settings->get('documents.quote.validity_days'));
        $this->assertSame('', $settings->get('documents.quote.notes'));
        $this->assertSame(0, $settings->get('documents.invoice.due_days'));
        $this->assertSame('شكراً لتعاملكم', $settings->get('documents.invoice.notes'));

        $this->actingAs($this->admin)->put('/settings/documents', [
            'quote' => ['validity_days' => 0],
            'invoice' => ['due_days' => 400],
        ])->assertSessionHasErrors(['quote.validity_days', 'invoice.due_days']);
    }

    public function test_message_templates(): void
    {
        $quote = ['whatsapp' => 'مرحباً {client_name} {link}', 'email_subject' => 'عرض {number}', 'email_body' => 'نص'];
        $invoice = ['whatsapp' => 'فاتورة {link}', 'email_subject' => 'فاتورة {number}', 'email_body' => 'نص'];

        $this->actingAs($this->admin)->put('/settings/messages', compact('quote', 'invoice'))->assertSessionHas('success');

        $this->assertSame($quote, $this->company()->fresh()->preferences()->get('templates.quote'));

        $this->actingAs($this->admin)->put('/settings/messages', ['quote' => ['whatsapp' => ''], 'invoice' => $invoice])
            ->assertSessionHasErrors(['quote.whatsapp', 'quote.email_subject']);
    }

    public function test_a_new_company_has_arabic_defaults(): void
    {
        $settings = $this->company()->preferences();

        $this->assertSame('QT', $settings->get('numbering.quote.prefix'));
        $this->assertSame(15, $settings->get('documents.quote.validity_days'));
        $this->assertStringContainsString('{link}', $settings->get('templates.quote.whatsapp'));
        $this->assertStringContainsString('عرض السعر', $settings->get('templates.quote.whatsapp'));
    }
}
