<?php

namespace App\Actions;

use App\Enums\ActivityType;
use App\Enums\DocumentStatus;
use App\Models\Activity;
use App\Models\Document;
use App\Models\User;
use App\Services\CurrentCompany;
use App\Services\NumberSequence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/** New drafts made from an existing document: duplicate, new revision, quote → invoice. */
class CopyDocument
{
    public function __construct(
        private NumberSequence $numbers,
        private CurrentCompany $current,
    ) {}

    /** Same content, new number, fresh dates. */
    public function duplicate(Document $source, User $user): Document
    {
        return DB::transaction(function () use ($source, $user) {
            $copy = $this->copy($source, [
                'number' => $this->numbers->reserve($source->type),
                'revision' => 1,
                'parent_id' => null,
                'created_by' => $user->id,
                ...$this->freshDates($source->type),
            ]);
            $copy->update(['root_id' => $copy->id]);
            Activity::log($copy, ActivityType::Duplicated, ['from' => $source->displayNumber()], $user);

            return $copy;
        });
    }

    /**
     * A sent / answered / expired document can't be edited: changes go into v2 (same number).
     * The old version stays as it was; Phase 5 shows its link as "replaced" once v2 is sent.
     */
    public function revise(Document $source, User $user): Document
    {
        if ($source->isDraft() || ! $source->is_latest) {
            throw new LogicException('Only the latest sent revision can be revised.');
        }

        return DB::transaction(function () use ($source, $user) {
            $source->update(['is_latest' => false]);

            $revision = $this->copy($source, [
                'revision' => $source->revisions()->max('revision') + 1,
                'root_id' => $source->root_id,
                'parent_id' => $source->id,
                'created_by' => $user->id,
                ...$this->freshDates($source->type),
            ]);
            Activity::log($revision, ActivityType::Revised, ['revision' => $revision->revision], $user);

            return $revision;
        });
    }

    /** Approved quote → invoice draft with the same client, currency, lines and terms. Only once per quote. */
    public function convertToInvoice(Document $quote, User $user): Document
    {
        if ($quote->type !== 'quote' || $quote->status !== DocumentStatus::Approved) {
            throw new LogicException('Only an approved quotation can become an invoice.');
        }

        if ($existing = $quote->invoice) {
            return $existing;
        }

        return DB::transaction(function () use ($quote, $user) {
            $invoice = $this->copy($quote, [
                'type' => 'invoice',
                'number' => $this->numbers->reserve('invoice'),
                'revision' => 1,
                'parent_id' => null,
                'quote_id' => $quote->id,
                'created_by' => $user->id,
                ...$this->freshDates('invoice'),
            ]);
            $invoice->update(['root_id' => $invoice->id]);
            Activity::log($invoice, ActivityType::CreatedFromQuote, ['quote' => $quote->displayNumber()], $user);
            Activity::log($quote, ActivityType::Converted, ['invoice' => $invoice->displayNumber()], $user);

            return $invoice;
        });
    }

    /** Copies the document and its lines as a new draft, with $overrides applied. */
    private function copy(Document $source, array $overrides): Document
    {
        $copy = $source->replicate([
            'root_id', 'quote_id', 'client_snapshot', 'company_snapshot',
            'sent_at', 'first_viewed_at', 'last_viewed_at', 'views_count',
            'approved_at', 'approved_by_name', 'approved_ip', 'approved_user_agent',
            'rejected_at', 'rejection_reason', 'expired_at',
        ]);

        $copy->fill([
            'status' => DocumentStatus::Draft,
            'is_latest' => true,
            'views_count' => 0,
            ...$overrides,
        ])->save();

        foreach ($source->lines as $line) {
            $copy->lines()->create($line->copyable());
        }

        return $copy;
    }

    /** Issue date today; validity / due date from the company's defaults. */
    private function freshDates(string $type): array
    {
        $settings = $this->current->get()->preferences();
        $today = Carbon::today();

        return $type === 'quote'
            ? ['issue_date' => $today, 'valid_until' => $today->copy()->addDays($settings->get('documents.quote.validity_days')), 'due_date' => null]
            : ['issue_date' => $today, 'due_date' => $today->copy()->addDays($settings->get('documents.invoice.due_days')), 'valid_until' => null];
    }
}
