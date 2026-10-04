<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
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
    }
}
