<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentView;
use App\Models\Item;
use App\Models\TaxRate;
use App\Services\CurrentCompany;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_builds_a_full_history(): void
    {
        $this->seed(DemoSeeder::class);

        $company = Company::sole();
        app(CurrentCompany::class)->set($company);

        $statuses = Document::where('type', 'quote')->latestRevisions()->pluck('status')->map->value->unique()->sort()->values()->all();
        $this->assertSame(['approved', 'draft', 'expired', 'rejected', 'sent', 'viewed'], $statuses);
        $this->assertTrue(Document::where('revision', 2)->exists());
        $this->assertTrue(Document::where('type', 'invoice')->whereNotNull('quote_id')->exists());
        $this->assertGreaterThan(50, Activity::count());
        $this->assertGreaterThan(10, DocumentView::count());
        $this->assertSame(5, $company->users()->count());
        // Whatever the time of day the seeder runs, nothing is dated in the future.
        $this->assertTrue(Document::all()->every(fn ($document) => $document->created_at->lte(now())
            && ($document->approved_at === null || $document->approved_at->lte(now()))));
        $this->assertSame(0, Activity::where('created_at', '>', now())->count());
        $this->assertSame(0, DocumentView::where('viewed_at', '>', now())->count());
    }

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
