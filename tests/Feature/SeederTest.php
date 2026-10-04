<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\Item;
use App\Models\TaxRate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_development_seeder_runs_and_fills_company_id(): void
    {
        $this->seed(DatabaseSeeder::class);

        $company = Company::sole();

        foreach ([TaxRate::class, Client::class, Item::class, Document::class] as $model) {
            $rows = $model::withoutGlobalScopes()->get();
            $this->assertNotEmpty($rows, $model);
            $this->assertTrue($rows->every(fn ($row) => $row->company_id === $company->id), $model);
        }
    }
}
