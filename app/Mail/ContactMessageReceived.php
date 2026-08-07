<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Copie envoyée à l'exploitant du site à chaque nouveau message de contact.
 */
class ContactMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '%s — Nouveau message : %s',
                config('app.name'),
                $this->contactMessage->subject,
            ),
            // Répondre à l'e-mail ouvre directement une réponse vers l'expéditeur.
            replyTo: [new Address($this->contactMessage->email, $this->contactMessage->name)],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-received',
            with: [
                'senderName' => $this->contactMessage->name,
                'senderEmail' => $this->contactMessage->email,
                'subjectLine' => $this->contactMessage->subject,
                'body' => $this->contactMessage->message,
                'sentAt' => $this->contactMessage->created_at ?? now(),
                'isMember' => $this->contactMessage->user_id !== null,
                'adminUrl' => route('admin.contact.index'),
            ],
        );
    }
}
