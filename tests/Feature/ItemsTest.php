<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\Item;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItemsTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountant = $this->member(Role::Accountant);
        $this->company()->preferences()->put(['currencies' => ['SAR', 'KWD']]);
    }

    public function test_add_an_item_with_price_in_minor_units(): void
    {
        $vat = TaxRate::create(['name' => 'VAT', 'rate' => 15]);

        $this->actingAs($this->accountant)->post('/items', [
            'type' => 'service', 'name' => 'تركيب', 'unit' => 'م²', 'price' => '1,250.5', 'currency' => 'SAR', 'tax_rate_id' => $vat->id,
        ])->assertSessionHas('success');

        $item = Item::sole();
        $this->assertSame(125050, $item->price_minor);
        $this->assertSame($vat->id, $item->tax_rate_id);
        $this->assertSame('1250.50', $item->toFormArray()['price']);
    }

    public function test_price_decimals_follow_the_currency(): void
    {
        $base = ['type' => 'product', 'name' => 'x'];

        $this->actingAs($this->accountant)->post('/items', [...$base, 'price' => '1.005', 'currency' => 'SAR'])->assertSessionHasErrors('price');
        $this->actingAs($this->accountant)->post('/items', [...$base, 'price' => '1.005', 'currency' => 'KWD'])->assertSessionHasNoErrors();
        $this->actingAs($this->accountant)->post('/items', [...$base, 'price' => 'abc', 'currency' => 'SAR'])->assertSessionHasErrors('price');

        $this->assertSame(1005, Item::sole()->price_minor);
    }

    public function test_only_enabled_currencies_and_own_tax_rates(): void
    {
        $mine = $this->company();
        app(CurrentCompany::class)->set(Company::factory()->create());
        $foreignTax = TaxRate::create(['name' => 'Other', 'rate' => 5]);
        app(CurrentCompany::class)->set($mine);

        $this->actingAs($this->accountant)->post('/items', [
            'type' => 'product', 'name' => 'x', 'price' => '10', 'currency' => 'EUR', 'tax_rate_id' => $foreignTax->id,
        ])->assertSessionHasErrors(['currency', 'tax_rate_id']);
    }

    public function test_an_item_keeps_a_currency_that_was_disabled_later(): void
    {
        $item = Item::factory()->create(['currency' => 'KWD', 'price_minor' => 1000]);
        $this->company()->preferences()->put(['currencies' => ['SAR']]);

        $this->actingAs($this->accountant)->put("/items/{$item->id}", [
            'type' => 'product', 'name' => 'renamed', 'price' => '2', 'currency' => 'KWD',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2000, $item->fresh()->price_minor);
    }

    public function test_search_and_delete(): void
    {
        Item::factory()->create(['name' => 'مكيف سبليت']);
        $paint = Item::factory()->create(['name' => 'دهان', 'description' => 'دهان داخلي']);

        $this->actingAs($this->accountant)->get('/items?q=مكيف')->assertInertia(fn (Assert $page) => $page->component('Items/Index')->has('items.data', 1));
        $this->actingAs($this->accountant)->getJson('/items/search?q=داخلي')->assertJsonPath('data.0.id', $paint->id);

        $this->actingAs($this->accountant)->delete("/items/{$paint->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($paint);
    }

    public function test_sales_can_browse_but_not_change_prices(): void
    {
        $sales = $this->member(Role::Sales);
        $item = Item::factory()->create();

        $this->actingAs($sales)->get('/items')->assertOk();
        $this->actingAs($sales)->getJson('/items/search?q=')->assertOk();
        $this->actingAs($sales)->post('/items', ['type' => 'product', 'name' => 'x', 'price' => '1', 'currency' => 'SAR'])->assertForbidden();
        $this->actingAs($sales)->put("/items/{$item->id}", [])->assertForbidden();
    }
}
