<?php

namespace App\Http\Controllers;

use App\Services\SteamWishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SteamWishlistController extends Controller
{
    public function show(Request $request, SteamWishlistService $steam): JsonResponse
    {
        $steamId = $request->user()->steam_id;

        if (! $steamId) {
            return response()->json(['connected' => false, 'items' => []]);
        }

        $wishlist = $steam->getWishlist($steamId);

        return response()->json([
            'connected' => true,
            'steamId' => $steamId,
            'available' => $wishlist['available'],
            'items' => $wishlist['items'],
        ]);
    }

    public function store(Request $request, SteamWishlistService $steam): JsonResponse
    {
        $validated = $request->validate([
            'steam_input' => ['required', 'string', 'max:255'],
        ]);

        $steamId = $steam->resolveSteamId($validated['steam_input']);

        if (! $steamId) {
            return response()->json([
                'message' => 'Profil Steam introuvable. Vérifie l\'URL ou le SteamID.',
            ], 422);
        }

        $request->user()->update(['steam_id' => $steamId]);

        $wishlist = $steam->getWishlist($steamId);

        return response()->json([
            'connected' => true,
            'steamId' => $steamId,
            'available' => $wishlist['available'],
            'items' => $wishlist['items'],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->update(['steam_id' => null]);

        return response()->json(['connected' => false, 'items' => []]);
    }
}
