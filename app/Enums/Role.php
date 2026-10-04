<?php

namespace App\Enums;

/** A member's role inside a company (stored on the company_user pivot). */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Sales = 'sales';
    case Viewer = 'viewer';

    public function label(): string
    {
        return __("ui.roles.{$this->value}");
    }

    public function allows(Ability $ability): bool
    {
        return in_array($this, $ability->roles(), true);
    }

    /**
     * Roles that can be given through an invitation or a role change.
     * There is exactly one Owner, created by the installer.
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Accountant, self::Sales, self::Viewer];
    }
}
