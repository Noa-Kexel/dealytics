<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PriceAlert;
use App\Models\User;
use App\Notifications\PriceAlertReached;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationTestController extends Controller
{
    /** Deal d'exemple pour l'aperçu et l'envoi de test. */
    private const SAMPLE = [
        'game_id' => '96', // Cyberpunk 2077 — id réel pour que le lien marche
        'title' => 'Cyberpunk 2077',
        'current_price' => 29.99,
        'target_price' => 35.00,
    ];

    /**
     * Templates e-mail prévisualisables depuis l'admin.
     *
     * @var array<string, string>
     */
    private const TEMPLATES = [
        'price-alert' => 'Alerte de prix atteinte',
        'verify-email' => 'Vérification de l\'adresse e-mail',
        'reset-password' => 'Réinitialisation du mot de passe',
        'contact-received' => 'Contact (copie pour l\'équipe)',
        'contact-confirmation' => 'Contact (accusé de réception)',
    ];

    /** Message d'exemple pour les e-mails de contact. */
    private const SAMPLE_CONTACT = [
        'name' => 'Camille Dubois',
        'email' => 'camille.dubois@exemple.com',
        'subject' => 'Un prix affiché me semble incorrect',
        'message' => "Bonjour,\n\nSur la fiche de Hollow Knight, le prix indiqué pour la boutique Fanatical est de 4,99 € alors que je trouve 7,49 € sur leur site.\n\nEst-ce un décalage de mise à jour ou une erreur ?\n\nMerci d'avance !",
    ];

    public function index(): Response
    {
        return Inertia::render('admin/Notifications', [
            'sample' => [
                'gameId' => self::SAMPLE['game_id'],
                'title' => self::SAMPLE['title'],
                'currentPrice' => self::SAMPLE['current_price'],
                'targetPrice' => self::SAMPLE['target_price'],
            ],
            'templates' => collect(self::TEMPLATES)
                ->map(fn (string $label, string $key): array => [
                    'key' => $key,
                    'label' => $label,
                ])
                ->values(),
            // Avec le driver « log », l'e-mail atterrit dans storage/logs.
            'mailer' => config('mail.default'),
        ]);
    }

    /** Notification in-app de test (cloche + toast), sans e-mail. */
    public function send(Request $request): RedirectResponse
    {
        // notifyNow + canal database uniquement : sync, pas d'e-mail.
        $request->user()->notifyNow(
            new PriceAlertReached($this->sampleAlert(), self::SAMPLE['current_price']),
            ['database'],
        );

        return back()->with('success', 'Notification de test envoyée.');
    }

    /** Envoie uniquement l'e-mail d'alerte de prix à l'admin courant. */
    public function sendEmail(Request $request): RedirectResponse
    {
        $request->user()->notifyNow(
            new PriceAlertReached($this->sampleAlert(), self::SAMPLE['current_price']),
            ['mail'],
        );

        return back()->with('success', 'E-mail de test envoyé à '.$request->user()->email.'.');
    }

    /** Aperçu d'un template mail (iframe admin). */
    public function preview(Request $request, string $template): View
    {
        abort_unless(array_key_exists($template, self::TEMPLATES), 404);

        return view('emails.'.$template, $this->sampleDataFor($template, $request->user()));
    }

    private function sampleAlert(): PriceAlert
    {
        $alert = new PriceAlert;
        $alert->game_id = self::SAMPLE['game_id'];
        $alert->title = self::SAMPLE['title'];
        $alert->target_price = self::SAMPLE['target_price'];

        return $alert;
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleDataFor(string $template, User $user): array
    {
        return match ($template) {
            'price-alert' => [
                'userName' => $user->name,
                'title' => self::SAMPLE['title'],
                'currentPrice' => self::SAMPLE['current_price'],
                'targetPrice' => self::SAMPLE['target_price'],
                'gameUrl' => url('/game/'.self::SAMPLE['game_id']),
                'alertsUrl' => url('/dashboard#price-alerts'),
            ],
            'verify-email' => [
                'userName' => $user->name,
                'url' => url('/email/verify/'.$user->id.'/apercu?signature=exemple'),
                'expiresInMinutes' => config('auth.verification.expire', 60),
            ],
            'reset-password' => [
                'userName' => $user->name,
                'email' => $user->email,
                'url' => url('/reset-password/jeton-d-exemple?email='.urlencode($user->email)),
                'expiresInMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
            ],
            'contact-received' => [
                'senderName' => self::SAMPLE_CONTACT['name'],
                'senderEmail' => self::SAMPLE_CONTACT['email'],
                'subjectLine' => self::SAMPLE_CONTACT['subject'],
                'body' => self::SAMPLE_CONTACT['message'],
                'sentAt' => now(),
                'isMember' => true,
                'adminUrl' => route('admin.contact.index'),
            ],
            'contact-confirmation' => [
                'senderName' => self::SAMPLE_CONTACT['name'],
                'subjectLine' => self::SAMPLE_CONTACT['subject'],
                'body' => self::SAMPLE_CONTACT['message'],
                'sentAt' => now(),
                'faqUrl' => route('faq'),
                'homeUrl' => url('/'),
            ],
        };
    }
}
