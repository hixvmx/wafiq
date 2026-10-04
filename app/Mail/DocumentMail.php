<?php

namespace App\Mail;

use App\Models\Document;
use App\Services\PdfRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A quotation / invoice sent to the client: the team's message + a "view document" button. */
class DocumentMail extends Mailable
{
    use Queueable;

    public function __construct(
        public Document $document,
        public string $mailSubject,
        public string $body,
        public string $url,
        public bool $attachPdf = false,
    ) {}

    /** @return list<Attachment> */
    public function attachments(): array
    {
        if (! $this->attachPdf) {
            return [];
        }

        $pdf = app(PdfRenderer::class);

        return [Attachment::fromData(fn () => $pdf->render($this->document), $pdf->filename($this->document))->withMime('application/pdf')];
    }

    public function envelope(): Envelope
    {
        $company = $this->document->company;

        return new Envelope(
            from: new Address(config('mail.from.address'), $company->name),
            replyTo: $company->email ? [new Address($company->email, $company->name)] : [],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.document', with: [
            'body' => $this->body,
            'url' => $this->url,
            'button' => __("ui.share.mail_button.{$this->document->type}"),
        ]);
    }
}
