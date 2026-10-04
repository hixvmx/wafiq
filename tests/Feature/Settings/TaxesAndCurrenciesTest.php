<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxesAndCurrenciesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->member(Role::Admin);
    }

    public function test_the_first_rate_becomes_the_default(): void
    {
        $this->actingAs($this->admin)->post('/settings/taxes', ['name' => 'ضريبة القيمة المضافة', 'rate' => 15])->assertSessionHas('success');

        $this->assertTrue(TaxRate::sole()->is_default);
        $this->assertSame(15.0, TaxRate::sole()->rate);
    }

    public function test_only_one_default_rate(): void
    {
        $vat = TaxRate::create(['name' => 'VAT', 'rate' => 15, 'is_default' => true]);
        $zero = TaxRate::create(['name' => 'معفى', 'rate' => 0]);

        $this->actingAs($this->admin)->put("/settings/taxes/{$zero->id}", ['name' => 'معفى', 'rate' => 0, 'is_default' => true]);

        $this->assertFalse($vat->fresh()->is_default);
        $this->assertTrue($zero->fresh()->is_default);
    }

    public function test_rate_validation_and_delete(): void
    {
        $this->actingAs($this->admin)->post('/settings/taxes', ['name' => 'X', 'rate' => 120])->assertSessionHasErrors('rate');

        $tax = TaxRate::create(['name' => 'VAT', 'rate' => 5]);
        $this->actingAs($this->admin)->delete("/settings/taxes/{$tax->id}")->assertSessionHas('success');
        $this->assertSame(0, TaxRate::count());
    }

    public function test_another_companys_rate_is_out_of_reach(): void
    {
        $mine = $this->company();
        app(CurrentCompany::class)->set(Company::factory()->create());
        $foreign = TaxRate::create(['name' => 'Other', 'rate' => 5]);
        app(CurrentCompany::class)->set($mine);

        $this->actingAs($this->admin)->delete("/settings/taxes/{$foreign->id}")->assertNotFound();
        $this->actingAs($this->admin)->put("/settings/taxes/{$foreign->id}", ['name' => 'x', 'rate' => 1])->assertNotFound();
    }

    public function test_currencies_always_include_the_default(): void
    {
        $this->actingAs($this->admin)->put('/settings/currencies', ['currency' => 'AED', 'currencies' => ['USD', 'SAR']])->assertSessionHas('success');

        $company = $this->company()->fresh();
        $this->assertSame('AED', $company->currency);
        $this->assertSame(['AED', 'USD', 'SAR'], $company->preferences()->get('currencies'));

        // A shorter list replaces the old one (lists are never merged with defaults).
        $this->actingAs($this->admin)->put('/settings/currencies', ['currency' => 'AED', 'currencies' => ['AED']]);
        $this->assertSame(['AED'], $this->company()->fresh()->preferences()->get('currencies'));
    }

    public function test_unknown_currencies_are_refused(): void
    {
        $this->actingAs($this->admin)
            ->put('/settings/currencies', ['currency' => 'XXX', 'currencies' => ['BTC']])
            ->assertSessionHasErrors(['currency', 'currencies.0']);
    }
}
