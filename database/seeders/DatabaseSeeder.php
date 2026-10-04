<?php

namespace Database\Seeders;

use App\Actions\SaveDocument;
use App\Enums\Role;
use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Database\Seeder;

/**
 * Model events must stay on here (no WithoutModelEvents): BelongsToCompany
 * fills company_id in a "creating" event.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Local development data: one company and its owner.
     * (Realistic Arabic demo data comes with the demo seeder in Phase 10.)
     */
    public function run(): void
    {
        $company = Company::factory()->create([
            'name' => 'شركة الإتقان للمقاولات',
            'email' => 'info@alitqan.test',
        ]);

        $owner = User::factory()->create([
            'name' => 'أحمد علي',
            'email' => 'owner@wafiq.test',
        ]);

        $company->addMember($owner, Role::Owner);

        app(CurrentCompany::class)->set($company);
        TaxRate::create(['name' => 'ضريبة القيمة المضافة', 'rate' => 15, 'is_default' => true]);

        Client::factory()->createMany([
            ['name' => 'مؤسسة النخبة التجارية', 'contact_name' => 'خالد العتيبي', 'phone' => '+966501234567', 'owner_id' => $owner->id],
            ['name' => 'شركة الأفق للتقنية', 'contact_name' => 'سارة القحطاني', 'phone' => '+966551112233', 'owner_id' => $owner->id],
            ['type' => 'person', 'name' => 'محمد الشهري', 'contact_name' => null, 'phone' => '+966561239876', 'owner_id' => $owner->id],
        ]);

        Item::factory()->createMany([
            ['type' => 'service', 'name' => 'تركيب أرضيات بورسلان', 'unit' => 'م²', 'price_minor' => 8500],
            ['type' => 'service', 'name' => 'أعمال دهان داخلي', 'unit' => 'م²', 'price_minor' => 2500],
            ['type' => 'product', 'name' => 'مكيف سبليت 18000 وحدة', 'unit' => 'قطعة', 'price_minor' => 245000],
            ['type' => 'service', 'name' => 'زيارة فنية', 'unit' => 'زيارة', 'price_minor' => 15000],
        ]);

        // A sample draft quote: 120 m² of flooring + a visit, VAT 15%, 5% discount.
        $vat = TaxRate::first();
        app(SaveDocument::class)->handle(null, 'quote', [
            'client_id' => Client::first()->id,
            'currency' => 'SAR',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(15)->toDateString(),
            'discount_type' => 'percent',
            'discount_value' => '5',
            'notes' => 'شكراً لثقتكم.',
            'terms' => $company->preferences()->get('documents.quote.terms'),
            'lines' => [
                ['item_id' => Item::first()->id, 'name' => 'تركيب أرضيات بورسلان', 'qty' => '120', 'unit' => 'م²', 'unit_price' => '85.00', 'discount' => '0', 'tax_rate_id' => $vat->id],
                ['name' => 'زيارة فنية', 'qty' => '1', 'unit' => 'زيارة', 'unit_price' => '150.00', 'discount' => '0', 'tax_rate_id' => $vat->id],
            ],
        ], $owner);
    }
}
