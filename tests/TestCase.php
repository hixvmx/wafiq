<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Company;
use App\Models\User;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests don't need compiled assets (public/build may not exist, e.g. in CI).
        $this->withoutVite();
    }

    /**
     * A member of the current company (created on first use and made current,
     * so tests behave the same in both editions).
     */
    protected function member(Role $role = Role::Owner, array $attributes = []): User
    {
        $current = app(CurrentCompany::class);

        if (! $current->get()) {
            $current->set(Company::factory()->create());
        }

        $user = User::factory()->create($attributes);
        $current->get()->addMember($user, $role);

        return $user;
    }

    protected function company(): Company
    {
        return app(CurrentCompany::class)->get();
    }
}
