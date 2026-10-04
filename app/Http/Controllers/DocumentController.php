<?php

namespace App\Http\Controllers;

use App\Actions\CopyDocument;
use App\Actions\SaveDocument;
use App\Enums\DocumentStatus;
use App\Models\Client;
use App\Models\Document;
use App\Models\TaxRate;
use App\Services\CurrentCompany;
use App\Support\Digits;
use App\Support\DocumentPresenter;
use App\Support\Money;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quotations (/quotes) and invoices (/invoices): one controller, the route passes the type.
 */
class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $this->type();
        $status = DocumentStatus::tryFrom((string) $request->query('status'));
        $notViewed = $request->boolean('not_viewed');
        $base = $this->visible($request, $type);

        $documents = (clone $base)
            ->with('client', 'creator')
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($notViewed, fn (Builder $query) => $query->whereNotNull('sent_at')->whereNull('first_viewed_at')->where('sent_at', '<=', now()->subDays(3)))
            ->when($request->query('q'), function (Builder $query, string $term) {
                $like = '%'.addcslashes(trim($term), '%_\\').'%';
                $query->where(fn (Builder $q) => $q->where('number', 'like', $like)->orWhereHas('client', fn (Builder $c) => $c->where('name', 'like', $like)));
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Document $document) => DocumentPresenter::summary($document));

        return Inertia::render('Documents/Index', [
            'type' => $type,
            'documents' => $documents,
            'filters' => ['q' => (string) $request->query('q', ''), 'status' => $status?->value, 'not_viewed' => $notViewed],
            'counts' => (clone $base)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status')->map(fn ($count) => (int) $count),
            'canCreate' => $request->user()->can('create', [Document::class, $type]),
        ]);
    }

    public function create(Request $request, CurrentCompany $current): Response
    {
        $type = $this->type();
        $this->authorize('create', [Document::class, $type]);

        $settings = $current->get()->preferences();
        $today = Carbon::today();
        $client = $request->integer('client_id') ? Client::find($request->integer('client_id')) : null;

        return $this->form($type, null, [
            'client' => $client?->toFormArray(),
            'currency' => $current->get()->currency,
            'issue_date' => $today->toDateString(),
            'valid_until' => $type === 'quote' ? $today->copy()->addDays($settings->get('documents.quote.validity_days'))->toDateString() : null,
            'due_date' => $type === 'invoice' ? $today->copy()->addDays($settings->get('documents.invoice.due_days'))->toDateString() : null,
            'discount_type' => 'percent',
            'discount_value' => '',
            'notes' => $settings->get("documents.{$type}.notes") ?? '',
            'terms' => $settings->get("documents.{$type}.terms") ?? '',
            'lines' => [],
        ]);
    }

    public function store(Request $request, SaveDocument $save): RedirectResponse
    {
        $type = $this->type();
        $this->authorize('create', [Document::class, $type]);

        $document = $save->handle(null, $type, $this->validated($request, $type), $request->user());

        return redirect()->route("{$document->routePrefix()}.show", $document)->with('success', __('ui.documents.saved'));
    }

    public function show(Request $request, Document $document): Response
    {
        $type = $this->type();
        $this->ensureType($document, $type);
        $this->authorize('view', $document);

        $document->load('lines', 'client', 'creator', 'company', 'quote', 'invoice');
        $user = $request->user();

        return Inertia::render('Documents/Show', [
            'document' => DocumentPresenter::full($document),
            'revisions' => $document->revisions()->get()->map(fn (Document $revision) => [
                'id' => $revision->id,
                'number' => $revision->displayNumber(),
                'status' => $revision->status->value,
                'is_current' => $revision->id === $document->id,
            ]),
            'quote' => $document->quote ? ['id' => $document->quote->id, 'number' => $document->quote->displayNumber()] : null,
            'invoice' => $document->invoice ? ['id' => $document->invoice->id, 'number' => $document->invoice->displayNumber()] : null,
            'can' => [
                'update' => $user->can('update', $document),
                'revise' => $user->can('revise', $document),
                'duplicate' => $user->can('duplicate', $document),
                'convert' => $user->can('convert', $document),
                'delete' => $user->can('delete', $document),
            ],
        ]);
    }

    public function edit(Document $document): Response
    {
        $type = $this->type();
        $this->ensureType($document, $type);
        $this->authorize('update', $document);

        return $this->form($type, $document, DocumentPresenter::form($document->load('lines', 'client')));
    }

    public function update(Request $request, Document $document, SaveDocument $save): RedirectResponse
    {
        $type = $this->type();
        $this->ensureType($document, $type);
        $this->authorize('update', $document);

        $save->handle($document, $type, $this->validated($request, $type, $document), $request->user());

        return redirect()->route("{$document->routePrefix()}.show", $document)->with('success', __('ui.documents.saved'));
    }

    public function duplicate(Request $request, Document $document, CopyDocument $copy): RedirectResponse
    {
        $type = $this->type();
        $this->ensureType($document, $type);
        $this->authorize('duplicate', $document);

        $new = $copy->duplicate($document, $request->user());

        return redirect()->route("{$new->routePrefix()}.edit", $new)->with('success', __('ui.documents.duplicated'));
    }

    public function revise(Request $request, Document $document, CopyDocument $copy): RedirectResponse
    {
        $type = $this->type();
        $this->ensureType($document, $type);
        $this->authorize('revise', $document);

        $new = $copy->revise($document, $request->user());

        return redirect()->route("{$new->routePrefix()}.edit", $new)->with('success', __('ui.documents.revised', ['revision' => $new->revision]));
    }

    public function convert(Request $request, Document $document, CopyDocument $copy): RedirectResponse
    {
        $type = $this->type();
        $this->ensureType($document, $type);
        $this->authorize('convert', $document);

        $invoice = $copy->convertToInvoice($document, $request->user());

        return redirect()->route('invoices.show', $invoice)->with('success', __('ui.documents.converted'));
    }

    public function destroy(Document $document): RedirectResponse
    {
        $type = $this->type();
        $this->ensureType($document, $type);
        $this->authorize('delete', $document);

        DB::transaction(function () use ($document) {
            // Deleting a v2 draft makes v1 the current version again.
            if ($document->is_latest && $document->parent_id) {
                Document::whereKey($document->parent_id)->update(['is_latest' => true]);
            }
            $document->delete();
        });

        $parent = $document->parent_id ? Document::find($document->parent_id) : null;

        return ($parent ? redirect()->route("{$parent->routePrefix()}.show", $parent) : redirect()->route("{$document->routePrefix()}.index"))
            ->with('success', __('ui.documents.deleted'));
    }

    /** Documents of this type the user may see (latest revisions only). */
    private function visible(Request $request, string $type): Builder
    {
        return Document::ofType($type)
            ->latestRevisions()
            ->unless($request->user()->can('view_all_documents'), fn (Builder $query) => $query->where('created_by', $request->user()->id));
    }

    private function form(string $type, ?Document $document, array $values): Response
    {
        $company = app(CurrentCompany::class)->get();
        $currencies = $company->preferences()->get('currencies');

        return Inertia::render('Documents/Form', [
            'type' => $type,
            'document' => $document ? ['id' => $document->id, 'number' => $document->displayNumber()] : null,
            'values' => $values,
            'taxRates' => TaxRate::orderByDesc('is_default')->orderBy('rate')->get(['id', 'name', 'rate', 'is_default']),
            'currencies' => collect(array_unique([...$currencies, $values['currency']]))
                ->map(fn (string $code) => ['code' => $code, 'decimals' => Money::decimals($code)])
                ->values(),
            'phoneCodes' => Phone::CODES,
            'units' => trans('ui.items.unit_suggestions'),
            'canManageClients' => request()->user()->can('manage_clients'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $type, ?Document $document = null): array
    {
        $company = app(CurrentCompany::class)->get();

        // Numbers typed with Arabic digits or thousands separators.
        $request->merge([
            'discount_value' => Digits::decimal($request->input('discount_value')),
            'lines' => array_map(fn ($line) => is_array($line) ? [
                ...$line,
                'qty' => Digits::decimal($line['qty'] ?? ''),
                'unit_price' => Digits::decimal($line['unit_price'] ?? ''),
                'discount' => Digits::decimal($line['discount'] ?? ''),
            ] : $line, (array) $request->input('lines', [])),
        ]);

        $currencies = array_filter([...$company->preferences()->get('currencies'), $document?->currency]);
        $currency = in_array($request->input('currency'), $currencies, true) ? $request->input('currency') : null;
        $price = $currency ? ['regex:'.Money::pattern($currency)] : [];
        $decimal3 = 'regex:/^\d{1,9}(\.\d{1,3})?$/';
        $ownRow = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $company->id)->whereNull('deleted_at');

        return $request->validate([
            'client_id' => ['required', $ownRow('clients')],
            'currency' => ['required', Rule::in($currencies)],
            'issue_date' => ['required', 'date'],
            'valid_until' => [Rule::requiredIf($type === 'quote'), 'nullable', 'date', 'after_or_equal:issue_date'],
            'due_date' => [Rule::requiredIf($type === 'invoice'), 'nullable', 'date', 'after_or_equal:issue_date'],
            'discount_type' => ['required', Rule::in(['percent', 'amount'])],
            'discount_value' => ['nullable', ...($request->input('discount_type') === 'amount' ? $price : [$decimal3, 'numeric', 'max:100'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.item_id' => ['nullable', $ownRow('items')],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'lines.*.qty' => ['required', $decimal3, 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.unit_price' => ['required', ...$price],
            'lines.*.discount' => ['nullable', $decimal3, 'numeric', 'max:100'],
            'lines.*.tax_rate_id' => ['nullable', Rule::exists('tax_rates', 'id')->where('company_id', $company->id)],
        ]);
    }

    /** "quote" or "invoice", set by the route group (routes/web.php). */
    private function type(): string
    {
        return request()->route('type');
    }

    private function ensureType(Document $document, string $type): void
    {
        abort_if($document->type !== $type, 404);
    }
}
