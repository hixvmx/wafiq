<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\CurrentCompany;
use App\Support\DocumentPresenter;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The pipeline at a glance: what's waiting for the client, how often they say yes,
 * how fast, and which documents need a follow-up now.
 * Sales members see their own documents; everyone else sees the whole company.
 */
class DashboardController extends Controller
{
    public const PERIODS = ['30', '90', '365', 'all'];

    /** Days after which a sent-but-unopened, or opened-but-unanswered, document needs a follow-up. */
    private const NOT_VIEWED_DAYS = 3;

    private const NO_ANSWER_DAYS = 2;

    private const EXPIRING_DAYS = 2;

    public function __invoke(Request $request, CurrentCompany $current): Response
    {
        $type = $request->query('type') === 'invoice' ? 'invoice' : 'quote';
        $period = in_array($request->query('period'), self::PERIODS, true) ? $request->query('period') : '90';
        $currency = $current->get()->currency;

        $inPeriod = $this->visible($request, $type)
            ->when($period !== 'all', fn (Builder $query) => $query->where('issue_date', '>=', Carbon::today()->subDays((int) $period)->toDateString()));

        return Inertia::render('Dashboard', [
            'type' => $type,
            'period' => $period,
            'currency' => $currency,
            'pipeline' => $this->pipeline(clone $inPeriod, $currency),
            'kpis' => $this->kpis(clone $inPeriod, $currency),
            'attention' => $this->attention($request, $type),
            'canCreate' => $request->user()->can('create', [Document::class, $type]),
        ]);
    }

    /**
     * Count and value per status. Values are summed per currency: the company's currency is
     * the headline, other currencies are listed beside it (never converted).
     *
     * @return array<string, array{count: int, value: string, others: list<array{currency: string, value: string}>}>
     */
    private function pipeline(Builder $query, string $currency): array
    {
        $rows = $query->select('status', 'currency', DB::raw('count(*) as documents'), DB::raw('sum(total_minor) as amount'))
            ->groupBy('status', 'currency')
            ->get();

        return collect(DocumentStatus::cases())->mapWithKeys(function (DocumentStatus $status) use ($rows, $currency) {
            $forStatus = $rows->filter(fn ($row) => ($row->status instanceof DocumentStatus ? $row->status : DocumentStatus::from($row->status)) === $status);

            return [$status->value => [
                'count' => (int) $forStatus->sum('documents'),
                ...$this->amounts($forStatus, $currency),
            ]];
        })->all();
    }

    /** @return array<string, mixed> */
    private function kpis(Builder $query, string $currency): array
    {
        $documents = $query->where('status', '!=', DocumentStatus::Draft)
            ->get(['id', 'status', 'currency', 'total_minor', 'sent_at', 'approved_at']);

        $approved = $documents->filter(fn (Document $d) => $d->status === DocumentStatus::Approved);
        $waiting = $documents->filter(fn (Document $d) => in_array($d->status, [DocumentStatus::Sent, DocumentStatus::Viewed], true));

        // Time from sending to approval, in hours (approvals recorded without a send time are skipped).
        $hours = $approved->filter(fn (Document $d) => $d->sent_at && $d->approved_at)
            ->map(fn (Document $d) => $d->sent_at->diffInMinutes($d->approved_at) / 60);

        $rows = $waiting->groupBy('currency')->map(fn (Collection $group, string $code) => (object) ['currency' => $code, 'amount' => $group->sum('total_minor')]);

        return [
            'sent' => $documents->count(),
            'approved' => $approved->count(),
            // Approved ÷ sent: every document that reached the client counts, answered or not.
            'approval_rate' => $documents->count() ? round($approved->count() / $documents->count() * 100) : null,
            'avg_hours_to_approval' => $hours->isNotEmpty() ? round($hours->avg(), 1) : null,
            'waiting' => ['count' => $waiting->count(), ...$this->amounts($rows->values(), $currency)],
        ];
    }

    /**
     * Follow-ups due now (all dates, latest revisions only).
     *
     * @return array<string, array{count: int, items: list<array<string, mixed>>}>
     */
    private function attention(Request $request, string $type): array
    {
        $today = Carbon::today();
        $base = fn () => $this->visible($request, $type)->with('client');

        $groups = [
            'not_viewed' => $base()->where('status', DocumentStatus::Sent)
                ->whereNull('first_viewed_at')
                ->where('sent_at', '<=', now()->subDays(self::NOT_VIEWED_DAYS))
                ->oldest('sent_at'),
            'no_answer' => $base()->where('status', DocumentStatus::Viewed)
                ->where('first_viewed_at', '<=', now()->subDays(self::NO_ANSWER_DAYS))
                ->oldest('first_viewed_at'),
            'expiring' => $base()->whereIn('status', [DocumentStatus::Sent, DocumentStatus::Viewed])
                ->whereBetween('valid_until', [$today->toDateString(), $today->copy()->addDays(self::EXPIRING_DAYS)->toDateString()])
                ->orderBy('valid_until'),
        ];

        return collect($groups)->map(fn (Builder $query) => [
            'count' => (clone $query)->count(),
            'items' => $query->limit(5)->get()->map(fn (Document $d) => DocumentPresenter::summary($d))->all(),
        ])->all();
    }

    /**
     * @param  Collection<int, object{currency: string, amount: int|string}>  $rows
     * @return array{value: string, others: list<array{currency: string, value: string}>}
     */
    private function amounts(Collection $rows, string $currency): array
    {
        $byCurrency = $rows->groupBy('currency')->map(fn (Collection $group) => (int) $group->sum('amount'));

        return [
            'value' => Money::fromMinor($byCurrency->get($currency, 0), $currency),
            'others' => $byCurrency->except($currency)
                ->filter(fn (int $minor) => $minor > 0)
                ->map(fn (int $minor, string $code) => ['currency' => $code, 'value' => Money::fromMinor($minor, $code)])
                ->values()
                ->all(),
        ];
    }

    private function visible(Request $request, string $type): Builder
    {
        return Document::ofType($type)
            ->latestRevisions()
            ->unless($request->user()->can('view_all_documents'), fn (Builder $query) => $query->where('created_by', $request->user()->id));
    }
}
