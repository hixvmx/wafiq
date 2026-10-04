<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_shared_props(): void
    {
        $company = Company::factory()->create(['name' => 'شركة الإتقان']);
        app(CurrentCompany::class)->set($company); // the SaaS edition resolves it from the subdomain

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('company.name', $company->name)
                ->where('translations.nav.quotes', 'عروض الأسعار')
                ->has('app.edition'));
    }

    public function test_inertia_visits_get_the_react_error_page(): void
    {
        $this->get('/missing-page', ['X-Inertia' => 'true'])
            ->assertNotFound()
            ->assertJsonPath('component', 'Error')
            ->assertJsonPath('props.status', 404);
    }

    public function test_full_page_errors_use_the_arabic_blade_view(): void
    {
        $this->get('/missing-page')
            ->assertNotFound()
            ->assertSee('الصفحة غير موجودة');
    }
}
