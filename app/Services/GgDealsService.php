<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GgDealsService
{
    private string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.ggdeals.base_url');
        $this->apiKey = config('services.ggdeals.key');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Get prices for a game by its Steam App ID.
     * Returns retail + keyshop prices with historical lows.
     */
    public function getPricesBySteamAppId(string $steamAppId, string $region = 'fr'): ?array
    {
        if (! $this->isConfigured() || empty($steamAppId)) {
            return null;
        }

        $cacheKey = "ggdeals_prices_{$steamAppId}_{$region}";

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($steamAppId, $region) {
            try {
                $response = Http::timeout(8)->get("{$this->baseUrl}/prices/by-steam-app-id/", [
                    'ids' => $steamAppId,
                    'key' => $this->apiKey,
                    'region' => $region,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! empty($data['success']) && ! empty($data['data'][$steamAppId])) {
                        $gameData = $data['data'][$steamAppId];

                        return [
                            'title' => $gameData['title'] ?? null,
                            'url' => $gameData['url'] ?? null,
                            'prices' => [
                                'currentRetail' => $this->parsePrice($gameData['prices']['currentRetail'] ?? null),
                                'currentKeyshops' => $this->parsePrice($gameData['prices']['currentKeyshops'] ?? null),
                                'historicalRetail' => $this->parsePrice($gameData['prices']['historicalRetail'] ?? null),
                                'historicalKeyshops' => $this->parsePrice($gameData['prices']['historicalKeyshops'] ?? null),
                                'currency' => $gameData['prices']['currency'] ?? 'EUR',
                            ],
                        ];
                    }
                }
            } catch (\Throwable) {
                // API down or timeout
            }

            return null;
        });
    }

    private function parsePrice(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
