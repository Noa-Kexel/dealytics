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
     * Recherche un jeu par titre ou Steam App ID → UUID ITAD.
     */
    public function lookupGame(string $title, ?string $steamAppId = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = 'itad_lookup_'.md5($title.$steamAppId);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($title, $steamAppId) {
            try {
                // App ID Steam d'abord (plus fiable).
                if ($steamAppId) {
                    $response = Http::timeout(5)->get("{$this->baseUrl}/games/lookup/v1", [
                        'key' => $this->apiKey,
                        'appid' => $steamAppId,
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();

                        if (! empty($data['found']) && ! empty($data['game']['id'])) {
                            return $data['game'];
                        }
                    }
                }

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
                // API down ou timeout
            }

            return null;
        });
    }

    /**
     * Historique des prix sur N mois.
     *
     * @return array<int, array{date: int, price: float}>
     */
    public function getHistory(string $gameUuid, int $months = 24, string $country = 'FR'): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $cacheKey = "itad_history_{$gameUuid}_{$months}_{$country}";
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $history = $this->fetchHistory($gameUuid, $months, $country);

        // Un historique vide vient aussi bien d'un jeu sans relevés que d'un timeout
        // ou d'une panne API. On le garde peu de temps pour ne pas bloquer la page
        // sur le repli « snapshots » pendant 6 h après un simple incident réseau.
        Cache::put($cacheKey, $history, $history === [] ? now()->addMinutes(5) : now()->addHours(6));

        return $history;
    }

    /**
     * Appel réseau brut vers ITAD, sans cache.
     *
     * @return array<int, array{date: int, price: float}>
     */
    private function fetchHistory(string $gameUuid, int $months, string $country): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/games/history/v2", [
                'key' => $this->apiKey,
                'id' => $gameUuid,
                'country' => $country,
                'since' => now()->subMonths($months)->toIso8601String(),
            ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json())
                ->map(function ($entry) {
                    $amount = $entry['deal']['price']['amount'] ?? null;

                    if ($amount === null || empty($entry['timestamp'])) {
                        return null;
                    }

                    return [
                        'date' => strtotime($entry['timestamp']),
                        'price' => (float) $amount,
                    ];
                })
                ->filter()
                ->sortBy('date')
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Lookup + historique en une seule passe.
     *
     * @return array<int, array{date: int, price: float}>
     */
    public function getPriceHistoryForGame(string $title, ?string $steamAppId = null, int $months = 24): array
    {
        $game = $this->lookupGame($title, $steamAppId);

        if (! $game || empty($game['id'])) {
            return [];
        }

        return $this->getHistory($game['id'], $months);
    }
}
