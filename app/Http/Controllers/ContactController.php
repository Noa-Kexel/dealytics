<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\ContactMessageConfirmation;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Public contact form. Pre-filled when the visitor is logged in.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Contact', [
            'contactEmail' => config('legal.editor.email'),
            'defaults' => [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
            ],
        ]);
    }

    /**
     * Store the message, warn the site owner and confirm to the sender.
     */
    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $message = ContactMessage::create([
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
        ]);

        // Les deux Mailable implémentent ShouldQueue : l'envoi part en file
        // d'attente, la réponse HTTP n'attend pas le serveur SMTP.
        Mail::to(config('legal.editor.email'))->send(new ContactMessageReceived($message));
        Mail::to($message->email)->send(new ContactMessageConfirmation($message));

        return back()->with(
            'success',
            'Message envoyé ! Un accusé de réception vient de partir vers '.$message->email.'.',
        );
    }
}
