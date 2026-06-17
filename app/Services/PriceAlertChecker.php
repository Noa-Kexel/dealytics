<?php

namespace App\Services;

use App\Models\PriceAlert;
use App\Models\User;
use App\Notifications\PriceAlertReached;
use Illuminate\Support\Collection;

class PriceAlertChecker
{
    public function __construct(private NexardaService $nexarda) {}

    /**
     * @return array<int, array{game_id: string, title: string, target_price: float, current_price: float}>
     */
    public function checkForUser(User $user): array
    {
        $triggered = [];

        $user->priceAlerts()
            ->where('is_reached', false)
            ->get()
            ->each(function (PriceAlert $alert) use (&$triggered) {
                if ($this->evaluateAlert($alert)) {
                    $triggered[] = [
                        'game_id' => $alert->game_id,
                        'title' => $alert->title,
                        'target_price' => (float) $alert->target_price,
                        'current_price' => (float) $alert->current_price,
                    ];
                }
            });

        return $triggered;
    }

    public function checkAll(): int
    {
        $count = 0;

        PriceAlert::query()
            ->where('is_reached', false)
            ->with('user')
            ->chunkById(50, function (Collection $alerts) use (&$count) {
                $alerts->each(function (PriceAlert $alert) use (&$count) {
                    if ($this->evaluateAlert($alert)) {
                        $count++;
                    }
                });
            });

        return $count;
    }

    private function evaluateAlert(PriceAlert $alert): bool
    {
        if ($alert->is_reached || ! is_numeric($alert->game_id)) {
            return false;
        }

        $data = $this->nexarda->getPrices((int) $alert->game_id);

        if (! $data || $data['lowest'] === null) {
            return false;
        }

        $currentPrice = (float) $data['lowest'];

        $alert->update(['current_price' => $currentPrice]);

        if ($currentPrice > (float) $alert->target_price) {
            return false;
        }

        $alert->update([
            'is_reached' => true,
            'notified_at' => now(),
        ]);

        $alert->user->notify(new PriceAlertReached($alert, $currentPrice));

        return true;
    }
}
