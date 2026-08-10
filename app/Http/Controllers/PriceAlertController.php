<?php

namespace App\Http\Controllers;

use App\Services\NexardaService;
use App\Services\PriceAlertChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

    public function store(Request $request, NexardaService $nexarda): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => 'required|string|max:50',
            'title' => 'required|string|max:255',
            'target_price' => 'required|numeric|min:0.01|max:9999',
            'current_price' => 'required|numeric|min:0',
        ]);

        $currentPrice = $this->resolveCurrentPrice(
            $nexarda,
            $validated['game_id'],
            (float) $validated['current_price'],
        );

        if ((float) $validated['target_price'] >= $currentPrice) {
            throw ValidationException::withMessages([
                'target_price' => __('validation.custom.target_price.below_current', [
                    'price' => number_format($currentPrice, 2, ',', ' '),
                ]),
            ]);
        }

        $alert = $request->user()
            ->priceAlerts()
            ->updateOrCreate(
                ['game_id' => $validated['game_id']],
                [
                    'title' => $validated['title'],
                    'target_price' => $validated['target_price'],
                    'current_price' => $currentPrice,
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

    public function check(Request $request, PriceAlertChecker $checker): JsonResponse
    {
        $triggered = $checker->checkForUser($request->user());

        $alerts = $request->user()
            ->priceAlerts()
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'alerts' => $alerts,
            'triggered' => $triggered,
        ]);
    }

    private function resolveCurrentPrice(NexardaService $nexarda, string $gameId, float $fallback): float
    {
        if (is_numeric($gameId)) {
            $data = $nexarda->getPrices((int) $gameId);

            if ($data && $data['lowest'] !== null) {
                return (float) $data['lowest'];
            }
        }

        return $fallback;
    }
}
