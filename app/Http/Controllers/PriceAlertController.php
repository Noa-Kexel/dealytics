<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $alerts = $request->user()
            ->priceAlerts()
            ->orderByDesc('created_at')
            ->get();

        return response()->json($alerts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'target_price' => 'required|numeric|min:0|max:9999',
        ]);

        $alert = $request->user()
            ->priceAlerts()
            ->updateOrCreate(
                ['game_id' => $validated['game_id']],
                [
                    ...$validated,
                    'is_reached' => false,
                    'notified_at' => null,
                ],
            );

        return response()->json($alert, 201);
    }

    public function update(Request $request, string $gameId): JsonResponse
    {
        $validated = $request->validate([
            'current_price' => 'nullable|numeric|min:0',
            'is_reached' => 'nullable|boolean',
            'notified_at' => 'nullable|date',
        ]);

        $alert = $request->user()
            ->priceAlerts()
            ->where('game_id', $gameId)
            ->firstOrFail();

        $alert->update($validated);

        return response()->json($alert);
    }

    public function destroy(Request $request, string $gameId): JsonResponse
    {
        $request->user()
            ->priceAlerts()
            ->where('game_id', $gameId)
            ->delete();

        return response()->json(['message' => 'Alerte supprimée']);
    }
}
