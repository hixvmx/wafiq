<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\Company;
use App\Services\CurrentCompany;
use App\Services\NumberSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-04 10:00');
        $this->member();
    }

    public function test_default_format_and_reservation(): void
    {
        $numbers = app(NumberSequence::class);

        $this->assertSame('QT-2026-0001', $numbers->peek('quote'));
        $this->assertSame('QT-2026-0001', $numbers->reserve('quote'));
        $this->assertSame('QT-2026-0002', $numbers->reserve('quote'));
        $this->assertSame('INV-2026-0001', $numbers->reserve('invoice')); // separate counter
        $this->assertSame('QT-2026-0003', $numbers->peek('quote'));
    }

    public function test_numbers_restart_each_year_when_yearly_reset_is_on(): void
    {
        $numbers = app(NumberSequence::class);
        $numbers->reserve('quote');
        $numbers->reserve('quote');

        Carbon::setTestNow('2027-01-01 08:00');

        $this->assertSame('QT-2027-0001', $numbers->reserve('quote'));
    }

    public function test_numbers_continue_across_years_without_yearly_reset(): void
    {
        $this->company()->preferences()->put(['numbering.invoice' => ['prefix' => 'F', 'include_year' => false, 'padding' => 5, 'yearly_reset' => false]]);
        $numbers = app(NumberSequence::class);
        $numbers->reserve('invoice');

        Carbon::setTestNow('2027-03-01');

        $this->assertSame('F-00002', $numbers->reserve('invoice'));
    }

    public function test_each_company_has_its_own_counters(): void
    {
        $numbers = app(NumberSequence::class);
        $numbers->reserve('quote');

        app(CurrentCompany::class)->set(Company::factory()->create());

        $this->assertSame('QT-2026-0001', app(NumberSequence::class)->reserve('quote'));
    }

    public function test_admin_changes_the_format_and_next_number(): void
    {
        $admin = $this->member(Role::Admin);
        $format = fn (string $prefix, int $next) => ['prefix' => $prefix, 'include_year' => true, 'padding' => 3, 'yearly_reset' => true, 'next_number' => $next];

        $this->actingAs($admin)->put('/settings/numbering', [
            'quote' => $format('عرض', 120),
            'invoice' => $format('INV', 1),
        ])->assertSessionHas('success');

        $this->assertSame('عرض-2026-120', app(NumberSequence::class)->reserve('quote'));
        $this->assertSame('INV-2026-001', app(NumberSequence::class)->reserve('invoice'));
    }

    public function test_prefix_must_be_letters_and_digits(): void
    {
        $admin = $this->member(Role::Admin);
        $bad = ['prefix' => 'QT/../', 'include_year' => true, 'padding' => 4, 'yearly_reset' => true, 'next_number' => 1];

        $this->actingAs($admin)->put('/settings/numbering', ['quote' => $bad, 'invoice' => $bad])->assertSessionHasErrors(['quote.prefix', 'invoice.prefix']);
    }
}
