<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SteamWishlistService
{
    public function __construct(private NexardaService $nexarda) {}

    /**
     * Résout un profil Steam (steamid64, URL, vanity) en steamid64.
     */
    public function resolveSteamId(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (preg_match('/^\d{17}$/', $input)) {
            return $input;
        }

        if (preg_match('#/profiles/(\d{17})#', $input, $m)) {
            return $m[1];
        }

        // URL /id/<vanity> ou nom vanity seul.
        $vanity = $input;

        if (preg_match('~/id/([^/?\#]+)~', $input, $m)) {
            $vanity = $m[1];
        }

        return $this->resolveVanity($vanity);
    }

    private function resolveVanity(string $vanity): ?string
    {
        $vanity = trim($vanity, "/ \t\n\r");

        if ($vanity === '' || str_contains($vanity, '/')) {
            return null;
        }

        return Cache::remember('steam_vanity_'.md5($vanity), now()->addDay(), function () use ($vanity) {
            try {
                $response = Http::timeout(8)->get("https://steamcommunity.com/id/{$vanity}/", ['xml' => 1]);

                if ($response->successful() && preg_match('#<steamID64>(\d{17})</steamID64>#', $response->body(), $m)) {
                    return $m[1];
                }
            } catch (\Throwable) {
            }

            return null;
        });
    }

    /**
     * Wishlist publique enrichie avec le prix Steam courant.
     *
     * @return array{available: bool, items: array<int, array<string, mixed>>}
     */
    public function getWishlist(string $steamId, int $limit = 18): array
    {
        return Cache::remember("steam_wishlist_{$steamId}", now()->addMinutes(30), function () use ($steamId, $limit) {
            $appIds = $this->fetchWishlistAppIds($steamId);

            if ($appIds === null) {
                return ['available' => false, 'items' => []];
            }

            $items = collect($appIds)
                ->take($limit)
                ->map(fn (int $appId) => $this->fetchAppSummary($appId))
                ->filter()
                ->values()
                ->all();

            return ['available' => true, 'items' => $items];
        });
    }

    /**
     * @return array<int, int>|null  Null when the wishlist is private/unavailable.
     */
    private function fetchWishlistAppIds(string $steamId): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://api.steampowered.com/IWishlistService/GetWishlist/v1/', [
                'steamid' => $steamId,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $items = $response->json('response.items');

            if (! is_array($items)) {
                return null;
            }

            // Priorité la plus basse = plus haut dans la wishlist.
            return collect($items)
                ->sortBy('priority')
                ->pluck('appid')
                ->map(fn ($id) => (int) $id)
                ->all();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Meilleur prix multi-boutiques via Nexarda.
     *
     * @return array{id: string, price: float|null, normalPrice: float|null, discount: int}|null
     */
    private function bestNexardaPrice(string $title): ?array
    {
        $match = $this->nexarda->searchGames($title)['games'][0] ?? null;

        if (! $match) {
            return null;
        }

        return [
            'id' => $match['id'],
            'price' => $match['price'],
            'normalPrice' => $match['normalPrice'],
            'discount' => (int) ($match['discount'] ?? 0),
        ];
    }

    private function fetchAppSummary(int $appId): ?array
    {
        return Cache::remember("steam_app_summary_{$appId}", now()->addHour(), function () use ($appId) {
            try {
                $response = Http::timeout(8)->get('https://store.steampowered.com/api/appdetails', [
                    'appids' => $appId,
                    'filters' => 'basic,price_overview',
                    'cc' => 'fr',
                    'l' => 'french',
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $payload = $response->json((string) $appId);

                if (empty($payload['success']) || empty($payload['data'])) {
                    return null;
                }

                $data = $payload['data'];
                $title = $data['name'] ?? 'Unknown';
                $price = $data['price_overview'] ?? null;
                $steamPrice = $price ? round($price['final'] / 100, 2) : null;

                // Croisement Nexarda pour le meilleur prix multi-boutiques.
                $nexarda = $this->bestNexardaPrice($title);

                $useNexarda = $nexarda && $nexarda['price'] !== null
                    && ($steamPrice === null || $nexarda['price'] <= $steamPrice);

                return [
                    'appId' => $appId,
                    'title' => $title,
                    'image' => $data['header_image'] ?? null,
                    'isFree' => (bool) ($data['is_free'] ?? false),
                    'price' => $useNexarda ? $nexarda['price'] : $steamPrice,
                    'normalPrice' => $useNexarda
                        ? $nexarda['normalPrice']
                        : ($price ? round($price['initial'] / 100, 2) : null),
                    'discount' => $useNexarda
                        ? $nexarda['discount']
                        : ($price ? (int) $price['discount_percent'] : 0),
                    'steamPrice' => $steamPrice,
                    'source' => $useNexarda ? 'nexarda' : 'steam',
                    'nexardaId' => $nexarda['id'] ?? null,
                    'storeUrl' => "https://store.steampowered.com/app/{$appId}",
                ];
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
