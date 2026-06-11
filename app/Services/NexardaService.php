<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NexardaService
{
    private string $baseUrl = 'https://www.nexarda.com/api/v3';

    private static function extractPlatform(?string $editionFull): ?string
    {
        if (! $editionFull || ! preg_match('/FOR:([A-Z0-9\-]+)/i', $editionFull, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }

    /**
     * Search games (or fetch the popularity-ordered feed) for the home page.
     *
     * Nexarda has no dedicated "popular games" endpoint, and its /search
     * endpoint ignores `sort` and `per_page` (always 50 results, ordered by
     * popularity). So an empty query falls back to a broad term that matches
     * most titles, which surfaces the trending games. Sorting and filtering
     * are done client-side on the returned page.
     */
    public function searchGames(string $query = '', int $page = 1): array
    {
        $query = trim($query) !== '' ? trim($query) : 'a';
        $page = max(1, $page);
        $cacheKey = 'nexarda_games_' . md5($query) . "_p{$page}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($query, $page) {
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

                    return [
                        'games' => $games,
                        'page' => $results['page'] ?? $page,
                        'pages' => $results['pages'] ?? 1,
                        'total' => $results['total'] ?? count($games),
                    ];
                }
            } catch (\Throwable) {
            }

            return ['games' => [], 'page' => $page, 'pages' => 1, 'total' => 0];
        });
    }

    private function mapGameListItem(array $item): ?array
    {
        $info = $item['game_info'] ?? null;

        if (! $info || empty($info['id'])) {
            return null;
        }

        $lowest = (float) ($info['lowest_price'] ?? 0);
        $discount = (int) ($info['highest_discount'] ?? 0);

        // Nexarda only gives the discounted price; derive the original.
        $normal = $discount > 0 && $discount < 100
            ? round($lowest / (1 - $discount / 100), 2)
            : $lowest;

        return [
            'id' => (string) $info['id'],
            'title' => $info['name'] ?? ($item['title'] ?? 'Unknown'),
            'image' => $item['image'] ?? null,
            'price' => $lowest > 0 ? $lowest : null,
            'normalPrice' => $normal > 0 ? $normal : null,
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
        $cacheKey = 'nexarda_search_' . md5($title);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($title) {
            try {
                $response = Http::timeout(8)->get("{$this->baseUrl}/search", [
                    'type' => 'games',
                    'q' => $title,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (!empty($data['results']['items'])) {
                        return $data['results']['items'][0]['game_info'] ?? null;
                    }
                }
            } catch (\Throwable) {
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

                    if (!empty($data['success']) && !empty($data['prices']['list'])) {
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
                            'storeCount' => $data['prices']['stores'] ?? 0,
                            'offerCount' => $data['prices']['offers'] ?? 0,
                            'editions' => $data['prices']['editions'] ?? [],
                            'offers' => collect($data['prices']['list'])
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
                                    'coupon' => !empty($offer['coupon']['available']) ? [
                                        'code' => $offer['coupon']['code'] ?? null,
                                        'discount' => $offer['coupon']['discount'] ?? 0,
                                        'priceWithout' => $offer['coupon']['price_without'] ?? null,
                                    ] : null,
                                ])
                                ->values()
                                ->all(),
                        ];
                    }
                }
            } catch (\Throwable) {
            }

            return null;
        });
    }

    public function getDealsForGame(string $title): ?array
    {
        $game = $this->searchGame($title);

        if (!$game || empty($game['id'])) {
            return null;
        }

        return $this->getPrices($game['id']);
    }
}
