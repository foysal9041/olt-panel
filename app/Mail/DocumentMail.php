<?php

namespace App\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * An invoice or money receipt emailed to a client: a short note with the
 * key figures, and the document itself attached as a PDF.
 */
class DocumentMail extends Mailable
{
    /**
     * @param  array<string, string>  $rows  figures shown in the mail, label => value
     */
    public function __construct(
        public string $title,
        public string $greeting,
        public string $intro,
        public array $rows,
        public string $pdf,
        public string $fileName,
        public ?string $note = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.document');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->pdf, $this->fileName)->withMime('application/pdf')];
    }

    /** From the office mailbox, replies to its reply-to address if it has one. */
    public function sender(string $address, ?string $name, ?string $replyTo): static
    {
        $this->from(new Address($address, $name ?: 'Sunlit Network DC'));
        if ($replyTo) {
            $this->replyTo($replyTo);
        }

        return $this;
    }
}
