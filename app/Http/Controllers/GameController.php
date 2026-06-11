<?php

namespace App\Http\Controllers;

use App\Models\PriceSnapshot;
use App\Services\NexardaService;
use App\Services\RawgService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameController extends Controller
{
    public function show(string $id)
    {
        return Inertia::render('Game/Show', [
            'gameId' => $id,
        ]);
    }

    public function games(Request $request, NexardaService $nexarda): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $page = (int) $request->query('page', 1);

        return response()->json($nexarda->searchGames($query, $page));
    }

    public function nexardaById(int $id, NexardaService $nexarda): JsonResponse
    {
        $data = $nexarda->getPrices($id);

        if (! $data) {
            return response()->json(['error' => 'Game not found on Nexarda'], 404);
        }

        // Opportunistic capture: record today's price so a real history builds
        // up over time for any game that gets viewed (one row per day).
        if (! empty($data['lowest'])) {
            PriceSnapshot::record((string) $id, (float) $data['lowest'], (int) ($data['maxDiscount'] ?? 0));
        }

        return response()->json($data);
    }

    public function history(int $id): JsonResponse
    {
        $snapshots = PriceSnapshot::where('game_id', (string) $id)
            ->orderBy('captured_on')
            ->get(['price', 'discount', 'captured_on'])
            ->map(fn ($s) => [
                'date' => $s->captured_on->timestamp,
                'price' => (float) $s->price,
                'discount' => (int) $s->discount,
            ]);

        return response()->json(['history' => $snapshots]);
    }

    public function rawg(string $title, RawgService $rawg): JsonResponse
    {
        if (! $rawg->isConfigured()) {
            return response()->json(['error' => 'RAWG API not configured'], 503);
        }

        $data = $rawg->getGameByTitle($title);

        if (! $data) {
            return response()->json(['error' => 'Game not found on RAWG'], 404);
        }

        return response()->json($data);
    }

    public function nexarda(string $title, NexardaService $nexarda): JsonResponse
    {
        $data = $nexarda->getDealsForGame($title);

        if (! $data) {
            return response()->json(['error' => 'Game not found on Nexarda'], 404);
        }

        return response()->json($data);
    }
}
