<?php

namespace App\Actions;

use App\Mail\DocumentMail;
use App\Models\Document;
use App\Models\DocumentSend;
use App\Models\User;
use App\Services\DocumentWorkflow;
use App\Support\MessageTemplate;
use App\Support\Money;
use App\Support\Token;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a document by email, WhatsApp or copied link. Every send gets its own tracked link,
 * so the timeline shows which channel and recipient opened it.
 */
class ShareDocument
{
    public function __construct(private DocumentWorkflow $workflow) {}

    /**
     * What the send dialog starts with: the company's templates filled in for this document.
     * "{link}" stays as is; it becomes the tracked link when the document is actually sent.
     *
     * @return array{whatsapp: array{phone: ?string, message: string}, email: array{to: ?string, subject: string, body: string}}
     */
    public function defaults(Document $document): array
    {
        $templates = $document->company->preferences()->get("templates.{$document->type}");
        $values = $this->values($document, '{link}');
        $client = $document->client;

        return [
            'whatsapp' => ['phone' => $client?->phone, 'message' => MessageTemplate::render($templates['whatsapp'], $values)],
            'email' => [
                'to' => $client?->email,
                'subject' => MessageTemplate::render($templates['email_subject'], $values),
                'body' => MessageTemplate::render($templates['email_body'], $values),
            ],
        ];
    }

    /**
     * @param  array{recipient?: ?string, message?: ?string, subject?: ?string, attach_pdf?: bool}  $data  message/subject may contain {link}
     * @return array{send: DocumentSend, url: string, message: ?string, whatsapp_url: ?string, email_failed: bool}
     */
    public function share(Document $document, string $channel, array $data, User $user): array
    {
        $token = Token::generate();
        $url = route('public.document', $token);
        $message = isset($data['message']) ? MessageTemplate::render($data['message'], ['link' => $url]) : null;

        $send = DB::transaction(function () use ($document, $channel, $data, $user, $token, $message) {
            $this->workflow->markSent($document);

            return DocumentSend::create([
                'document_id' => $document->id,
                'channel' => $channel,
                'recipient' => $data['recipient'] ?? null,
                'token_hash' => Token::hash($token),
                'message' => $message,
                'sent_by' => $user->id,
                'sent_at' => now(),
            ]);
        });

        $emailFailed = false;
        if ($channel === 'email') {
            $emailFailed = ! $this->email($document, $send, MessageTemplate::render($data['subject'] ?? '', ['link' => $url]), (string) $message, $url, (bool) ($data['attach_pdf'] ?? false));
        }

        return [
            'send' => $send,
            'url' => $url,
            'message' => $message,
            // wa.me click-to-chat: free, no WhatsApp API. The member presses send in WhatsApp.
            'whatsapp_url' => $channel === 'whatsapp' ? 'https://wa.me/'.ltrim((string) $send->recipient, '+').'?text='.rawurlencode((string) $message) : null,
            'email_failed' => $emailFailed,
        ];
    }

    /** Sends now (the member waits for the answer). A mail failure is recorded, never thrown. */
    private function email(Document $document, DocumentSend $send, string $subject, string $body, string $url, bool $attachPdf): bool
    {
        try {
            Mail::to($send->recipient)->send(new DocumentMail($document, $subject, $body, $url, $attachPdf));
            $send->update(['email_status' => 'sent']);

            return true;
        } catch (Throwable $e) {
            Log::warning('Document email not sent: '.$e->getMessage(), ['document' => $document->id, 'send' => $send->id]);
            $send->update(['email_status' => 'failed']);

            return false;
        }
    }

    /** @return array<string, string> */
    private function values(Document $document, string $link): array
    {
        $date = fn (?Carbon $date) => $date?->locale('ar')->translatedFormat('j F Y') ?? '';
        $client = $document->client;

        return [
            'client_name' => $client?->contact_name ?: ($client?->name ?? ''),
            'number' => $document->displayNumber(),
            'amount' => Money::format($document->total_minor, $document->currency),
            'link' => $link,
            'valid_until' => $date($document->valid_until),
            'due_date' => $date($document->due_date),
            'company' => $document->company->name,
        ];
    }
}
