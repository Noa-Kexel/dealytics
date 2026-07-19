<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\PriceAlert;
use App\Models\PriceSnapshot;
use App\Models\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Real, platform-wide stats for the home page hero.
     * Cached so the most-hit page stays cheap.
     */
    public function index(): JsonResponse
    {
        $stats = Cache::remember('home_stats', now()->addMinutes(10), fn () => [
            'trackedGames' => $this->trackedGames(),
            'hotDeals' => $this->hotDeals(),
            'totalSavings' => $this->totalSavings(),
        ]);

        return response()->json($stats);
    }

    /**
     * Distinct games the platform actually tracks — i.e. those with recorded
     * price history, or that a user has favorited or set an alert on.
     */
    private function trackedGames(): int
    {
        $union = PriceSnapshot::query()->select('game_id')
            ->union(Favorite::query()->select('game_id'))
            ->union(PriceAlert::query()->select('game_id'));

        return DB::query()->fromSub($union, 'tracked')->count();
    }

    /**
     * Games whose most recent snapshot is at least 50% off right now.
     */
    private function hotDeals(): int
    {
        $latest = PriceSnapshot::query()
            ->select('game_id', DB::raw('MAX(captured_on) as latest_on'))
            ->groupBy('game_id');

        return PriceSnapshot::query()
            ->joinSub($latest, 'l', function ($join) {
                $join->on('price_snapshots.game_id', '=', 'l.game_id')
                    ->on('price_snapshots.captured_on', '=', 'l.latest_on');
            })
            ->where('price_snapshots.discount', '>=', 50)
            ->distinct()
            ->count('price_snapshots.game_id');
    }

    /**
     * Total money users saved across every purchase they logged.
     */
    private function totalSavings(): int
    {
        $saved = (float) Purchase::query()
            ->selectRaw('COALESCE(SUM(original_price - price), 0) as saved')
            ->value('saved');

        return (int) round(max(0, $saved));
    }
}
