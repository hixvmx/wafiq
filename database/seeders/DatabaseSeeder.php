<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\TaxRate;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

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
    }
}
