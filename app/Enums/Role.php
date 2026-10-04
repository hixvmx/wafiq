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

    /** Owner and Admin manage settings, branding and the team. */
    public function managesCompany(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }
}
