<?php

namespace App\Services;

use App\Support\Price;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NexardaService
{
    private string $baseUrl = 'https://www.nexarda.com/api/v3';

    /** Durée du dernier résultat conservé pour tenir pendant une panne Nexarda. */
    private const FALLBACK_TTL_HOURS = 24;

    private static function extractPlatform(?string $editionFull): ?string
    {
        if (! $editionFull || ! preg_match('/FOR:\s*([A-Z0-9\-]+)/i', $editionFull, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    /**
     * Recherche / feed popularité pour l'accueil.
     *
     * Sans endpoint « popular » dédié, une requête vide bascule sur un terme
     * large ; tri et filtres se font côté client.
     *
     * Une panne de Nexarda n'est jamais mise en cache : sinon un seul appel
     * trop lent fige une page vide pendant toute la durée du cache. En cas
     * d'échec on sert le dernier résultat connu, et à défaut on signale la
     * panne (`error`) pour que l'accueil affiche un écran d'erreur plutôt
     * qu'un « Aucun résultat » trompeur.
     */
    public function searchGames(string $query = '', int $page = 1): array
    {
        $query = trim($query) !== '' ? trim($query) : 'a';
        $page = max(1, $page);
        $cacheKey = 'nexarda_games_'.md5($query)."_p{$page}";
        $fallbackKey = "{$cacheKey}_last_ok";

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/search", [
                'type' => 'games',
                'q' => $query,
                'page' => $page,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['results'] ?? [];

                $games = collect($results['items'] ?? [])
                    ->map(fn ($item) => $this->mapGameListItem($item))
                    ->filter()
                    ->values()
                    ->all();

                $payload = [
                    'games' => $games,
                    'page' => $results['page'] ?? $page,
                    'pages' => $results['pages'] ?? 1,
                    'total' => $results['total'] ?? count($games),
                ];

                Cache::put($cacheKey, $payload, now()->addMinutes(30));
                // Copie longue durée conservée comme filet de secours.
                Cache::put($fallbackKey, $payload, now()->addHours(self::FALLBACK_TTL_HOURS));

                return $payload;
            }

            Log::warning('Nexarda : recherche refusée', [
                'status' => $response->status(),
                'query' => $query,
                'page' => $page,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Nexarda : recherche injoignable', [
                'reason' => $e->getMessage(),
                'query' => $query,
                'page' => $page,
            ]);
        }

        $fallback = Cache::get($fallbackKey);

        if ($fallback !== null) {
            return [...$fallback, 'stale' => true];
        }

        return ['games' => [], 'page' => $page, 'pages' => 1, 'total' => 0, 'error' => true];
    }

    private function mapGameListItem(array $item): ?array
    {
        $info = $item['game_info'] ?? null;

        if (! $info || empty($info['id'])) {
            return null;
        }

        $lowest = (float) ($info['lowest_price'] ?? 0);
        $discount = (int) ($info['highest_discount'] ?? 0);
        $normal = Price::deriveNormalPrice($lowest, $discount);

        return [
            'id' => (string) $info['id'],
            'title' => $info['name'] ?? ($item['title'] ?? 'Unknown'),
            'image' => $item['image'] ?? null,
            'price' => $lowest > 0 ? $lowest : null,
            'normalPrice' => $normal,
            'discount' => $discount,
            'upcoming' => (bool) ($info['upcoming'] ?? false),
            'platforms' => collect($info['platforms'] ?? [])
                ->pluck('slug')
                ->filter()
                ->values()
                ->all(),
        ];
    }

    public function searchGame(string $title): ?array
    {
        $cacheKey = 'nexarda_search_'.md5($title);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($title) {
            try {
                $response = Http::timeout(8)->get("{$this->baseUrl}/search", [
                    'type' => 'games',
                    'q' => $title,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! empty($data['results']['items'])) {
                        return $data['results']['items'][0]['game_info'] ?? null;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Nexarda : recherche de titre injoignable', [
                    'reason' => $e->getMessage(),
                    'title' => $title,
                ]);
            }

            return null;
        });
    }

    public function getPrices(int $gameId): ?array
    {
        $cacheKey = "nexarda_prices_{$gameId}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($gameId) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/prices", [
                    'id' => $gameId,
                    'type' => 'game',
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (! empty($data['success']) && ! empty($data['prices']['list'])) {
                        $offers = collect($data['prices']['list'])
                            ->filter(fn ($offer) => $offer['available'] ?? false)
                            ->map(fn ($offer) => [
                                'url' => $offer['url'] ?? null,
                                'store' => $offer['store']['name'] ?? 'Unknown',
                                'storeImage' => $offer['store']['image'] ?? null,
                                'storeType' => $offer['store']['type'] ?? 'Unknown',
                                'official' => $offer['store']['official'] ?? false,
                                'edition' => $offer['edition'] ?? null,
                                'editionFull' => $offer['edition_full'] ?? null,
                                'platform' => self::extractPlatform($offer['edition_full'] ?? null),
                                'region' => $offer['region'] ?? null,
                                'price' => $offer['price'] ?? 0,
                                'discount' => $offer['discount'] ?? 0,
                                'coupon' => ! empty($offer['coupon']['available']) ? [
                                    'code' => $offer['coupon']['code'] ?? null,
                                    'discount' => $offer['coupon']['discount'] ?? 0,
                                    'priceWithout' => $offer['coupon']['price_without'] ?? null,
                                ] : null,
                            ])
                            ->values();

                        return [
                            'game' => [
                                'id' => $data['info']['id'] ?? $gameId,
                                'name' => $data['info']['name'] ?? null,
                                'cover' => $data['info']['cover'] ?? null,
                            ],
                            'currency' => $data['prices']['currency'] ?? 'EUR',
                            'currencySymbol' => $data['prices']['currency_symbol'] ?? '€',
                            'lowest' => $data['prices']['lowest'] ?? null,
                            'highest' => $data['prices']['highest'] ?? null,
                            'maxDiscount' => $data['prices']['max_discount'] ?? 0,
                            // Les totaux de Nexarda comptent aussi les offres
                            // épuisées ou retirées : on ne compte que ce qui est
                            // réellement achetable, sinon l'en-tête annonce plus
                            // d'offres que la liste n'en affiche.
                            'storeCount' => $offers->pluck('store')->unique()->count(),
                            'offerCount' => $offers->count(),
                            'editions' => $data['prices']['editions'] ?? [],
                            'offers' => $offers->all(),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Nexarda : relevé de prix injoignable', [
                    'reason' => $e->getMessage(),
                    'gameId' => $gameId,
                ]);
            }

            return null;
        });
    }

    public function getDealsForGame(string $title): ?array
    {
        $game = $this->searchGame($title);

        if (! $game || empty($game['id'])) {
            return null;
        }

        return $this->getPrices($game['id']);
    }
}
