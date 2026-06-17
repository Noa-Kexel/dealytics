<?php

namespace App\Notifications;

use App\Models\PriceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PriceAlertReached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public PriceAlert $alert,
        public float $currentPrice,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $gameUrl = url('/game/'.$this->alert->game_id);

        return (new MailMessage)
            ->subject('Dealytics — Prix cible atteint : '.$this->alert->title)
            ->greeting('Bonne nouvelle !')
            ->line(sprintf(
                '« %s » est maintenant à %.2f€, en dessous de votre objectif de %.2f€.',
                $this->alert->title,
                $this->currentPrice,
                (float) $this->alert->target_price,
            ))
            ->action('Voir le jeu', $gameUrl)
            ->line('Merci d\'utiliser Dealytics pour suivre vos jeux.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'price_alert',
            'game_id' => $this->alert->game_id,
            'title' => $this->alert->title,
            'target_price' => (float) $this->alert->target_price,
            'current_price' => $this->currentPrice,
            'message' => sprintf(
                '« %s » est à %.2f€ (objectif : %.2f€)',
                $this->alert->title,
                $this->currentPrice,
                (float) $this->alert->target_price,
            ),
        ];
    }
}
