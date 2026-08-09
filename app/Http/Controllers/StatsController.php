<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\PriceAlert;
use App\Models\PriceSnapshot;
use App\Support\Price;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * Stats globales pour le hero de l'accueil (cache 10 min).
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

    /** Jeux distincts avec historique, favori ou alerte. */
    private function trackedGames(): int
    {
        $union = PriceSnapshot::query()->select('game_id')
            ->union(Favorite::query()->select('game_id'))
            ->union(PriceAlert::query()->select('game_id'));

        return DB::query()->fromSub($union, 'tracked')->count();
    }

    /** Jeux dont le dernier snapshot est à −50 % ou plus. */
    private function hotDeals(): int
    {
        return $this->latestSnapshots()
            ->where('price_snapshots.discount', '>=', 50)
            ->distinct()
            ->count('price_snapshots.game_id');
    }

    /** Somme des économies (prix d'origine − prix actuel) sur les derniers snapshots. */
    private function totalSavings(): int
    {
        $expr = Price::sqlUnitSavingsExpression();
        $saved = (float) $this->latestSnapshots()
            ->whereBetween('price_snapshots.discount', [1, 99])
            ->selectRaw("COALESCE(SUM({$expr}), 0) as saved")
            ->value('saved');

        return (int) round(max(0, $saved));
    }

    /** Dernier snapshot par jeu. */
    private function latestSnapshots(): Builder
    {
        $latest = PriceSnapshot::query()
            ->select('game_id', DB::raw('MAX(captured_on) as latest_on'))
            ->groupBy('game_id');

        return PriceSnapshot::query()->joinSub($latest, 'l', function ($join) {
            $join->on('price_snapshots.game_id', '=', 'l.game_id')
                ->on('price_snapshots.captured_on', '=', 'l.latest_on');
        });
    }
}
