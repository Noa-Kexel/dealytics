<?php

namespace App\Http\Controllers;

use App\Services\ItadService;
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

    /**
     * Fetch enriched game info from RAWG API (proxied through backend).
     */
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

    /**
     * Fetch game deals from IsThereAnyDeal API (proxied through backend).
     */
    public function itad(Request $request, string $title, ItadService $itad): JsonResponse
    {
        if (! $itad->isConfigured()) {
            return response()->json(['error' => 'ITAD API not configured'], 503);
        }

        $steamAppId = $request->query('steamAppId');

        $data = $itad->getDealsForGame($title, $steamAppId);

        if (! $data) {
            return response()->json(['error' => 'Game not found on ITAD'], 404);
        }

        return response()->json($data);
    }
}
