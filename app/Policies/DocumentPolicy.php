<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Models\Document;
use App\Models\User;

/**
 * Who may do what with a quotation or invoice (section 7 of idea-wafiq.md).
 * Sales work on their own documents; Owner, Admin and Accountant on all of them.
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return $user->can('view_all_documents') || $this->owns($user, $document);
    }

    /** Sales can't start an invoice from scratch: only by converting their own approved quote. */
    public function create(User $user, string $type): bool
    {
        return $type === 'invoice' ? $user->can('create_invoices') : $user->can('create_documents');
    }

    /** Only drafts are edited in place; anything sent gets a new revision instead. */
    public function update(User $user, Document $document): bool
    {
        return $document->isDraft() && $this->manages($user, $document);
    }

    public function revise(User $user, Document $document): bool
    {
        return ! $document->isDraft() && $document->is_latest && $this->manages($user, $document);
    }

    public function duplicate(User $user, Document $document): bool
    {
        return $this->view($user, $document) && $this->create($user, $document->type);
    }

    public function convert(User $user, Document $document): bool
    {
        return $document->type === 'quote'
            && $document->status === DocumentStatus::Approved
            && ($user->can('create_invoices') || ($user->currentRole() === Role::Sales && $this->owns($user, $document)));
    }

    /** Owner / Admin: any document. Accountant: drafts. Sales: own drafts. */
    public function delete(User $user, Document $document): bool
    {
        return match ($user->currentRole()) {
            Role::Owner, Role::Admin => true,
            Role::Accountant => $document->isDraft(),
            Role::Sales => $document->isDraft() && $this->owns($user, $document),
            default => false,
        };
    }

    private function manages(User $user, Document $document): bool
    {
        return match ($user->currentRole()) {
            Role::Owner, Role::Admin, Role::Accountant => true,
            Role::Sales => $this->owns($user, $document),
            default => false,
        };
    }

    private function owns(User $user, Document $document): bool
    {
        return $document->created_by === $user->id;
    }
}
