<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $month = $request->query('month', now()->format('Y-m'));
        [$year, $monthNumber] = explode('-', $month);

        $purchases = $request->user()
            ->purchases()
            ->whereYear('purchased_at', $year)
            ->whereMonth('purchased_at', $monthNumber)
            ->orderByDesc('purchased_at')
            ->get();

        return response()->json($purchases);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:9999',
            'original_price' => 'required|numeric|min:0|max:9999',
            'store' => 'nullable|string|max:100',
            'purchased_at' => 'nullable|date',
        ]);

        $purchase = $request->user()
            ->purchases()
            ->create([
                ...$validated,
                'store' => $validated['store'] ?? 'N/A',
                'purchased_at' => $validated['purchased_at'] ?? now(),
            ]);

        return response()->json($purchase, 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $purchase = $request->user()
            ->purchases()
            ->where('id', $id)
            ->firstOrFail();

        $purchase->delete();

        return response()->json(['message' => 'Achat supprimé']);
    }

    /** Historique des dépenses sur les 6 derniers mois. */
    public function history(Request $request): JsonResponse
    {
        $history = [];
        $now = now();

        for ($i = 5; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);

            $spent = $request->user()
                ->purchases()
                ->whereYear('purchased_at', $date->year)
                ->whereMonth('purchased_at', $date->month)
                ->sum('price');

            $history[] = [
                'month' => $date->translatedFormat('M'),
                'spent' => round((float) $spent, 2),
            ];
        }

        return response()->json($history);
    }
}
