<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /**
     * Every contact request, newest first.
     */
    public function index(): Response
    {
        $messages = ContactMessage::query()
            ->latest()
            ->get()
            ->map(fn (ContactMessage $message) => [
                'id' => $message->id,
                'name' => $message->name,
                'email' => $message->email,
                'subject' => $message->subject,
                'message' => $message->message,
                'is_read' => $message->isRead(),
                'is_member' => $message->user_id !== null,
                'created_at' => $message->created_at,
            ]);

        return Inertia::render('admin/Contact', [
            'messages' => $messages,
            'unreadCount' => $messages->where('is_read', false)->count(),
        ]);
    }

    /**
     * Flip a message between read and unread.
     */
    public function toggleRead(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->read_at = $contactMessage->isRead() ? null : now();
        $contactMessage->save();

        return back()->with(
            'success',
            $contactMessage->isRead() ? 'Message marqué comme lu.' : 'Message marqué comme non lu.',
        );
    }

    public function destroy(ContactMessage $contactMessage): RedirectResponse
    {
        $contactMessage->delete();

        return back()->with('success', 'Message supprimé.');
    }
}
