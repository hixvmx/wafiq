<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = $this->member(Role::Sales);
    }

    public function test_sales_adds_a_client_with_a_whatsapp_number(): void
    {
        $this->actingAs($this->sales)->post('/clients', [
            'type' => 'company',
            'name' => 'مؤسسة النخبة',
            'contact_name' => 'خالد',
            'email' => ' Khalid@Example.com ',
            'phone_code' => '966',
            'phone_number' => '050 123 4567',
        ])->assertSessionHas('success');

        $client = Client::sole();
        $this->assertSame('+966501234567', $client->phone);
        $this->assertSame('khalid@example.com', $client->email);
        $this->assertSame($this->sales->id, $client->owner_id);
        $this->assertSame($this->company()->id, $client->company_id);
    }

    public function test_invalid_phone_and_missing_name(): void
    {
        $this->actingAs($this->sales)
            ->post('/clients', ['type' => 'person', 'name' => '', 'phone_code' => '966', 'phone_number' => '12'])
            ->assertSessionHasErrors(['name', 'phone_number']);

        $this->assertSame(0, Client::count());
    }

    public function test_a_client_without_phone_or_email_is_allowed(): void
    {
        $this->actingAs($this->sales)->post('/clients', ['type' => 'person', 'name' => 'محمد'])->assertSessionHasNoErrors();

        $this->assertNull(Client::sole()->phone);
    }

    public function test_edit_shows_the_phone_split_and_update_saves(): void
    {
        $client = Client::factory()->create(['phone' => '+971501234567']);

        $this->actingAs($this->sales)->get('/clients')->assertInertia(fn (Assert $page) => $page
            ->component('Clients/Index')
            ->where('clients.data.0.phone_code', '971')
            ->where('clients.data.0.phone_number', '501234567'));

        $this->actingAs($this->sales)->put("/clients/{$client->id}", [
            'type' => 'company', 'name' => 'اسم جديد', 'phone_code' => '971', 'phone_number' => '501234567',
        ])->assertSessionHas('success');

        $this->assertSame('اسم جديد', $client->fresh()->name);
    }

    public function test_search_by_name_and_by_phone(): void
    {
        Client::factory()->create(['name' => 'شركة الأفق', 'phone' => '+966551112233']);
        Client::factory()->create(['name' => 'مؤسسة النخبة', 'phone' => '+966501234567']);

        $this->actingAs($this->sales)->get('/clients?q=الأفق')->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.name', 'شركة الأفق'));
        $this->actingAs($this->sales)->get('/clients?q=0501234567')->assertInertia(fn (Assert $page) => $page->has('clients.data', 1)->where('clients.data.0.name', 'مؤسسة النخبة'));
        $this->actingAs($this->sales)->getJson('/clients/search?q=النخبة')->assertJsonCount(1, 'data')->assertJsonPath('data.0.phone', '+966501234567');
    }

    public function test_type_filter_and_soft_delete(): void
    {
        $person = Client::factory()->create(['type' => 'person', 'name' => 'محمد']);
        Client::factory()->create(['type' => 'company']);

        $this->actingAs($this->sales)->get('/clients?type=person')->assertInertia(fn (Assert $page) => $page->has('clients.data', 1));

        $this->actingAs($this->sales)->delete("/clients/{$person->id}")->assertSessionHas('success');
        $this->assertSoftDeleted($person);
    }

    public function test_viewers_can_look_but_not_change(): void
    {
        $viewer = $this->member(Role::Viewer);
        $client = Client::factory()->create();

        $this->actingAs($viewer)->get('/clients')->assertOk();
        $this->actingAs($viewer)->post('/clients', ['type' => 'person', 'name' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->put("/clients/{$client->id}", ['type' => 'person', 'name' => 'x'])->assertForbidden();
        $this->actingAs($viewer)->delete("/clients/{$client->id}")->assertForbidden();
    }

    public function test_another_companys_clients_are_invisible(): void
    {
        $mine = $this->company();
        app(CurrentCompany::class)->set(Company::factory()->create());
        $foreign = Client::factory()->create(['name' => 'عميل شركة أخرى']);
        app(CurrentCompany::class)->set($mine);

        $this->actingAs($this->sales)->get('/clients')->assertInertia(fn (Assert $page) => $page->has('clients.data', 0));
        $this->actingAs($this->sales)->getJson('/clients/search?q=أخرى')->assertJsonCount(0, 'data');
        $this->actingAs($this->sales)->put("/clients/{$foreign->id}", ['type' => 'person', 'name' => 'x'])->assertNotFound();
    }
}
