<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ItadService
{
    private string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.itad.base_url');
        $this->apiKey = config('services.itad.key');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Look up a game by title (exact match) or Steam App ID.
     * Returns the ITAD game UUID or null.
     */
    public function lookupGame(string $title, ?string $steamAppId = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = 'itad_lookup_'.md5($title.$steamAppId);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($title, $steamAppId) {
            try {
                // Prefer Steam App ID lookup (more precise)
                if ($steamAppId) {
                    $response = Http::timeout(5)->get("{$this->baseUrl}/games/lookup/v1", [
                        'key' => $this->apiKey,
                        'appid' => "app/{$steamAppId}",
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();

                        if (! empty($data['found']) && ! empty($data['game']['id'])) {
                            return $data['game'];
                        }
                    }
                }

                // Fallback to title search
                $response = Http::timeout(5)->get("{$this->baseUrl}/games/search/v1", [
                    'key' => $this->apiKey,
                    'title' => $title,
                    'results' => 1,
                ]);

                if ($response->successful()) {
                    $results = $response->json();

                    return ! empty($results) ? $results[0] : null;
                }
            } catch (\Throwable) {
                // API down or timeout
            }

            return null;
        });
    }

    /**
     * Get current prices across all stores for a game UUID.
     * Returns deals array with store name, price, regular price, cut, url.
     */
    public function getPrices(string $gameUuid, string $country = 'FR'): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $cacheKey = "itad_prices_{$gameUuid}_{$country}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($gameUuid, $country) {
            try {
                $response = Http::timeout(8)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post("{$this->baseUrl}/games/prices/v3?key={$this->apiKey}&country={$country}&capacity=0", [
                        $gameUuid,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! empty($data) && ! empty($data[0]['deals'])) {
                        return $data[0];
                    }
                }
            } catch (\Throwable) {
                // API down or timeout
            }

            return [];
        });
    }

    /**
     * Full lookup: search game + get all current prices.
     * Returns normalized data ready for the frontend.
     */
    public function getDealsForGame(string $title, ?string $steamAppId = null): ?array
    {
        $game = $this->lookupGame($title, $steamAppId);

        if (! $game || empty($game['id'])) {
            return null;
        }

        $priceData = $this->getPrices($game['id']);

        if (empty($priceData) || empty($priceData['deals'])) {
            return null;
        }

        $deals = collect($priceData['deals'])->map(function ($deal) {
            return [
                'shop' => $deal['shop']['name'] ?? 'Unknown',
                'shopId' => $deal['shop']['id'] ?? 0,
                'price' => $deal['price']['amount'] ?? 0,
                'currency' => $deal['price']['currency'] ?? 'EUR',
                'regularPrice' => $deal['regular']['amount'] ?? 0,
                'cut' => $deal['cut'] ?? 0,
                'url' => $deal['url'] ?? null,
                'drm' => collect($deal['drm'] ?? [])->pluck('name')->toArray(),
                'platforms' => collect($deal['platforms'] ?? [])->pluck('name')->toArray(),
                'storeLow' => $deal['storeLow']['amount'] ?? null,
            ];
        })
            ->sortBy('price')
            ->values()
            ->toArray();

        // Extract historical low if available
        $historyLow = null;

        if (! empty($priceData['historyLow']['all'])) {
            $historyLow = [
                'price' => $priceData['historyLow']['all']['amount'] ?? 0,
                'currency' => $priceData['historyLow']['all']['currency'] ?? 'EUR',
            ];
        }

        return [
            'gameId' => $game['id'],
            'title' => $game['title'] ?? $title,
            'deals' => $deals,
            'totalDeals' => count($deals),
            'historyLow' => $historyLow,
        ];
    }
}
