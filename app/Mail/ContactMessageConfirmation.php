<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Accusé de réception envoyé à la personne qui a rempli le formulaire.
 */
class ContactMessageConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '%s — Nous avons bien reçu votre message',
                config('app.name'),
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-confirmation',
            with: [
                'senderName' => $this->contactMessage->name,
                'subjectLine' => $this->contactMessage->subject,
                'body' => $this->contactMessage->message,
                'sentAt' => $this->contactMessage->created_at ?? now(),
                'faqUrl' => route('faq'),
                'homeUrl' => url('/'),
            ],
        );
    }
}
