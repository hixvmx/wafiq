<?php

namespace App\Http\Controllers;

use App\Actions\RecordView;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentSend;
use App\Services\CurrentCompany;
use App\Services\DocumentWorkflow;
use App\Services\PdfRenderer;
use App\Support\DocumentPresenter;
use App\Support\Money;
use App\Support\Token;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The client's page behind a tracked link (/d/{token}): no login.
 * The client can read the document and approve or reject it while it's open.
 */
class PublicDocumentController extends Controller
{
    public function __construct(private DocumentWorkflow $workflow) {}

    public function show(Request $request, string $token): Response
    {
        [$send, $document] = $this->resolve($token);
        $this->workflow->expireIfDue($document);
        $document->load('lines', 'client', 'company');

        $replaced = $this->workflow->isReplaced($document);
        $isTeam = (bool) $request->user()?->roleIn($document->company);
        $typeName = __("ui.documents.types.{$document->type}.one");
        $company = $document->company_snapshot['name'] ?? $document->company->name;

        return Inertia::render('Public/Document', [
            'token' => $token,
            'document' => DocumentPresenter::full($document),
            'state' => $replaced ? 'replaced' : $document->status->value,
            'answer' => [
                'approved_at' => $document->approved_at?->toIso8601String(),
                'approved_by_name' => $document->approved_by_name,
                'rejected_at' => $document->rejected_at?->toIso8601String(),
                'expired_at' => $document->expired_at?->toIso8601String(),
            ],
            'canRespond' => ! $isTeam && $this->workflow->canClientRespond($document),
            'isTeam' => $isTeam,
            'rejectReasons' => trans('ui.public.reject_reasons'),
            // Read by app.blade.php: WhatsApp & co. build the link preview from the server HTML.
            // Title, company and amount only: no client name or private details.
            'og' => [
                'title' => "{$typeName} {$document->displayNumber()} — {$company}",
                'description' => Money::format($document->total_minor, $document->currency),
                'image' => $document->company->imageUrl('logo'),
            ],
        ]);
    }

    /** The client downloads the PDF (doesn't count as a view: only the page's signal does). */
    public function pdf(string $token, PdfRenderer $pdf): HttpResponse
    {
        [, $document] = $this->resolve($token);
        $this->workflow->expireIfDue($document);

        return response($pdf->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf->filename($document).'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /** The "viewed" signal, sent by the page's JavaScript after 2 visible seconds. */
    public function view(Request $request, string $token, RecordView $record): HttpResponse
    {
        [$send] = $this->resolve($token);
        $record->handle($send, $request->user(), $request->ip(), $request->userAgent());

        return response()->noContent();
    }

    public function approve(Request $request, string $token): RedirectResponse
    {
        [, $document] = $this->resolve($token);
        abort_if((bool) $request->user()?->roleIn($document->company), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'agree' => ['accepted'],
        ]);

        $done = $this->workflow->approve($document, trim($data['name']), $request->ip(), $request->userAgent());

        return back()->with($done ? 'success' : 'error', __($done ? 'ui.public.approved_thanks' : 'ui.public.no_longer_open'));
    }

    public function reject(Request $request, string $token): RedirectResponse
    {
        [, $document] = $this->resolve($token);
        abort_if((bool) $request->user()?->roleIn($document->company), 403);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $reason = trim(implode(' — ', array_filter([$data['reason'] ?? null, $data['details'] ?? null]))) ?: null;

        $done = $this->workflow->reject($document, $reason);

        return back()->with($done ? 'success' : 'error', __($done ? 'ui.public.rejected_thanks' : 'ui.public.no_longer_open'));
    }

    /** From a replaced version's page: a fresh tracked link to the newest sent version. */
    public function latest(string $token): RedirectResponse
    {
        [$send, $document] = $this->resolve($token);
        $newest = $this->workflow->newestSentRevision($document);

        if (! $newest || $newest->id === $document->id) {
            return redirect()->route('public.document', $token);
        }

        $newToken = Token::generate();
        DocumentSend::create([
            'document_id' => $newest->id,
            'channel' => $send->channel,
            'recipient' => $send->recipient,
            'token_hash' => Token::hash($newToken),
            'sent_by' => null, // opened from the old link, not sent by a member
            'sent_at' => now(),
        ]);

        return redirect()->route('public.document', $newToken);
    }

    /**
     * The send and its document, working in the document's company from here on.
     *
     * @return array{DocumentSend, Document}
     */
    private function resolve(string $token): array
    {
        $send = DocumentSend::findByToken($token);
        abort_unless($send, 404);

        app(CurrentCompany::class)->set(Company::findOrFail($send->company_id));
        $document = $send->document;
        abort_unless($document && ! $document->isDraft(), 404);

        return [$send, $document];
    }
}
