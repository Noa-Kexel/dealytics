<?php

namespace App\Console\Commands;

use App\Models\Favorite;
use App\Models\PriceAlert;
use App\Models\PriceSnapshot;
use App\Services\NexardaService;
use Illuminate\Console\Command;

class SnapshotPricesCommand extends Command
{
    protected $signature = 'prices:snapshot';

    protected $description = "Capture today's price for every tracked game (favorites + price alerts)";

    public function handle(NexardaService $nexarda): int
    {
        // Tracked games = anything users favorited or set an alert on.
        $gameIds = Favorite::query()->pluck('game_id')
            ->merge(PriceAlert::query()->pluck('game_id'))
            ->filter(fn ($id) => is_numeric($id))
            ->unique()
            ->values();

        if ($gameIds->isEmpty()) {
            $this->info('No tracked games to snapshot.');

            return self::SUCCESS;
        }

        $this->info("Snapshotting {$gameIds->count()} tracked game(s)...");
        $captured = 0;

        foreach ($gameIds as $gameId) {
            $data = $nexarda->getPrices((int) $gameId);

            if ($data && ! empty($data['lowest'])) {
                PriceSnapshot::record((string) $gameId, (float) $data['lowest'], (int) ($data['maxDiscount'] ?? 0));
                $captured++;
            }
        }

        $this->info("Captured {$captured} price snapshot(s).");

        return self::SUCCESS;
    }
}
