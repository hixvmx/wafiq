<?php

namespace App\Http\Controllers;

use App\Actions\ShareDocument;
use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Document;
use App\Services\DocumentWorkflow;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The team sends a document (send dialog) or extends its validity. */
class DocumentShareController extends Controller
{
    /** Answers JSON: the dialog needs the tracked link / WhatsApp URL straight away. */
    public function store(Request $request, Document $document, ShareDocument $share): JsonResponse
    {
        abort_if($document->type !== $request->route('type'), 404);
        $this->authorize('send', $document);

        $channel = $request->input('channel');
        $data = $request->validate([
            'channel' => ['required', Rule::in(['email', 'whatsapp', 'link'])],
            'recipient' => [Rule::requiredIf(in_array($channel, ['email', 'whatsapp'], true)), 'nullable', 'string', 'max:255', ...($channel === 'email' ? ['email'] : [])],
            'subject' => [Rule::requiredIf($channel === 'email'), 'nullable', 'string', 'max:255'],
            'message' => [Rule::requiredIf(in_array($channel, ['email', 'whatsapp'], true)), 'nullable', 'string', 'max:5000'],
            'attach_pdf' => ['boolean'],
        ]);

        if ($channel === 'whatsapp') {
            $data['recipient'] = Phone::normalize(null, $data['recipient'])
                ?? throw ValidationException::withMessages(['recipient' => __('ui.share.invalid_phone')]);
        }
        if ($channel === 'email') {
            $data['recipient'] = Str::lower(trim($data['recipient']));
        }

        // The tracked link must be in the message, wherever the member moved or deleted it.
        if (isset($data['message']) && ! str_contains($data['message'], '{link}')) {
            $data['message'] = rtrim($data['message'])."\n{link}";
        }

        $result = $share->share($document, $channel, $data, $request->user());

        return response()->json([
            'url' => $result['url'],
            'whatsapp_url' => $result['whatsapp_url'],
            'email_failed' => $result['email_failed'],
            'recipient' => $result['send']->recipient,
        ], 201);
    }

    public function extend(Request $request, Document $document, DocumentWorkflow $workflow): RedirectResponse
    {
        abort_if($document->type !== $request->route('type'), 404);
        $this->authorize('extend', $document);

        $data = $request->validate(['valid_until' => ['required', 'date', 'after_or_equal:today']]);
        $workflow->extend($document, Carbon::parse($data['valid_until']));
        Activity::log($document, ActivityType::Extended, ['valid_until' => $data['valid_until']], $request->user());

        return back()->with('success', __('ui.share.extended'));
    }
}
