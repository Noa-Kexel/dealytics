<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $favorites = $request->user()
            ->favorites()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($favorites);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'thumb' => 'nullable|string|max:500',
        ]);

        $favorite = $request->user()
            ->favorites()
            ->updateOrCreate(
                ['game_id' => $validated['game_id']],
                $validated,
            );

        return response()->json($favorite, 201);
    }

    public function destroy(Request $request, string $gameId): JsonResponse
    {
        $request->user()
            ->favorites()
            ->where('game_id', $gameId)
            ->delete();

        return response()->json(['message' => 'Favori supprimé']);
    }

    /**
     * Check if specific games are in the user's favorites.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_ids' => 'required|array',
            'game_ids.*' => 'string|max:50',
        ]);

        $favoriteIds = $request->user()
            ->favorites()
            ->whereIn('game_id', $validated['game_ids'])
            ->pluck('game_id');

        return response()->json($favoriteIds);
    }
}
