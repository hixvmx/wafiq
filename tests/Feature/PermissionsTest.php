<?php

namespace Tests\Feature;

use App\Enums\Ability;
use App\Enums\Role;
use App\Models\Company;
use App\Services\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The permissions table from section 7 of idea-wafiq.md, written out by hand on purpose. */
class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const MATRIX = [
        //                     owner  admin  accountant sales  viewer
        'manage_settings' => [true,  true,  false,     false, false],
        'manage_team' => [true,  true,  false,     false, false],
        'review_documents' => [true,  true,  false,     false, false],
        'create_documents' => [true,  true,  true,      true,  false],
        'record_payments' => [true,  true,  true,      false, false],
        'view_all_documents' => [true,  true,  true,      false, true],
        'view_reports' => [true,  true,  true,      false, true],
        'manage_clients' => [true,  true,  true,      true,  false],
        'manage_items' => [true,  true,  true,      false, false],
        'create_invoices' => [true,  true,  true,      false, false],
    ];

    private const ROLES = [Role::Owner, Role::Admin, Role::Accountant, Role::Sales, Role::Viewer];

    /** @return iterable<string, array{string}> */
    public static function abilities(): iterable
    {
        foreach (Ability::cases() as $ability) {
            yield $ability->value => [$ability->value];
        }
    }

    #[DataProvider('abilities')]
    public function test_each_role_gets_exactly_the_listed_permissions(string $ability): void
    {
        $this->assertArrayHasKey($ability, self::MATRIX, "Add {$ability} to the matrix.");

        foreach (self::ROLES as $i => $role) {
            $user = $this->member($role);

            $this->assertSame(self::MATRIX[$ability][$i], $user->can($ability), "{$role->value} → {$ability}");
        }
    }

    public function test_no_permissions_outside_the_current_company(): void
    {
        $owner = $this->member(Role::Owner);
        app(CurrentCompany::class)->set(Company::factory()->create());

        foreach (Ability::cases() as $ability) {
            $this->assertFalse($owner->fresh()->can($ability->value));
        }
    }

    public function test_permissions_are_shared_with_the_frontend(): void
    {
        $this->actingAs($this->member(Role::Sales))
            ->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('auth.role', 'sales')
                ->where('auth.can.create_documents', true)
                ->where('auth.can.manage_team', false));
    }
}
