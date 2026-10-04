<?php

namespace App\Enums;

/**
 * Company-wide permissions (section 7 of idea-wafiq.md), checked with
 * Gate / $user->can('manage_team'). Rules that depend on a specific document
 * ("Sales: own quotes only") live in the document Policies instead.
 */
enum Ability: string
{
    case ManageSettings = 'manage_settings';
    case ManageTeam = 'manage_team';
    case ReviewDocuments = 'review_documents';
    case CreateDocuments = 'create_documents';
    case RecordPayments = 'record_payments';
    case ViewAllDocuments = 'view_all_documents';
    case ViewReports = 'view_reports';

    /** @return list<Role> */
    public function roles(): array
    {
        return match ($this) {
            self::ManageSettings, self::ManageTeam, self::ReviewDocuments => [Role::Owner, Role::Admin],
            self::CreateDocuments => [Role::Owner, Role::Admin, Role::Accountant, Role::Sales],
            self::RecordPayments => [Role::Owner, Role::Admin, Role::Accountant],
            self::ViewAllDocuments, self::ViewReports => [Role::Owner, Role::Admin, Role::Accountant, Role::Viewer],
        };
    }
}
