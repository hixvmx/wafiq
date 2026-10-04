<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Support\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_hosted_edition_uses_the_single_company(): void
    {
        config(['edition.name' => Edition::SELF_HOSTED]);
        $company = Company::factory()->create();

        $this->assertSame($company->id, app(CurrentCompany::class)->id());
    }

    public function test_saas_edition_has_no_company_until_one_is_set(): void
    {
        config(['edition.name' => Edition::SAAS]);
        $company = Company::factory()->create();
        $current = app(CurrentCompany::class);

        $this->assertNull($current->get());

        $current->set($company);
        $this->assertSame($company->id, $current->id());
    }

    public function test_forget_resolves_again_after_the_installer_creates_the_company(): void
    {
        config(['edition.name' => Edition::SELF_HOSTED]);
        $current = app(CurrentCompany::class);
        $this->assertNull($current->get());

        $company = Company::factory()->create();
        $current->forget();

        $this->assertSame($company->id, $current->id());
    }

    public function test_a_user_has_a_role_per_company(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $user = User::factory()->create();

        $a->addMember($user, Role::Sales);

        $this->assertSame(Role::Sales, $user->roleIn($a));
        $this->assertNull($user->roleIn($b));
    }
}
