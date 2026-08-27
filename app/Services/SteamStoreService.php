<?php

namespace App\Services;

use App\Support\GameDescriptionFormatter;
use App\Support\GameTitleMatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SteamStoreService
{
    /** Enrichit une fiche jeu via l'API store Steam publique. */
    public function getGameByTitle(string $title): ?array
    {
        $appId = $this->searchAppId($title);

        if (! $appId) {
            return null;
        }

        return $this->getGameByAppId($appId);
    }

    private function searchAppId(string $title): ?int
    {
        $cacheKey = 'steam_search_v2_'.md5(mb_strtolower(trim($title)));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($title) {
            try {
                // Recherche en anglais : les titres Nexarda le sont, et Steam
                // traduit les siens (« La Saga Skywalker »), ce qui empêcherait
                // tout rapprochement. La fiche, elle, reste récupérée en français.
                $response = Http::timeout(8)->get('https://store.steampowered.com/api/storesearch/', [
                    'term' => $title,
                    'l' => 'english',
                    'cc' => 'fr',
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $items = $response->json('items', []);

                if (empty($items)) {
                    return null;
                }

                $normalizedTitle = GameTitleMatcher::normalize($title);

                foreach ($items as $item) {
                    if (GameTitleMatcher::normalize((string) ($item['name'] ?? '')) === $normalizedTitle) {
                        return (int) $item['id'];
                    }
                }

                // Steam remonte toujours un « meilleur » résultat, même pour un
                // jeu absent de la boutique : « Grand Theft Auto VI » renvoyait
                // Vice City. Sans correspondance sûre, pas d'enrichissement.
                foreach ($items as $item) {
                    if (GameTitleMatcher::matches($title, (string) ($item['name'] ?? ''))) {
                        return (int) $item['id'];
                    }
                }

                return null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    public function getGameByAppId(int $appId): ?array
    {
        // Préfère library_hero / screenshot aux fonds sombres ou vides.
        $cacheKey = "steam_game_v4_{$appId}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($appId) {
            try {
                $response = Http::timeout(10)->get('https://store.steampowered.com/api/appdetails', [
                    'appids' => $appId,
                    'l' => 'french',
                    'cc' => 'fr',
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $payload = $response->json((string) $appId);

                if (empty($payload['success']) || empty($payload['data'])) {
                    return null;
                }

                $data = $payload['data'];
                $reviews = $this->fetchReviewSummary($appId);

                $rawDescription = $data['detailed_description']
                    ?? $data['about_the_game']
                    ?? $data['short_description']
                    ?? '';
                $description = GameDescriptionFormatter::fromHtml($rawDescription);

                $platforms = [];

                if ($data['platforms']['windows'] ?? false) {
                    $platforms[] = 'Windows';
                }

                if ($data['platforms']['mac'] ?? false) {
                    $platforms[] = 'macOS';
                }

                if ($data['platforms']['linux'] ?? false) {
                    $platforms[] = 'Linux';
                }

                $metacritic = $data['metacritic']['score'] ?? null;
                $rating = $reviews['rating'] ?? 0;
                $ratingsCount = $reviews['total_reviews'] ?? ($data['recommendations']['total'] ?? 0);
                $screenshots = collect($data['screenshots'] ?? [])->pluck('path_full')->filter()->values()->all();

                return [
                    'source' => 'steam',
                    'id' => $appId,
                    'name' => $data['name'] ?? 'Unknown',
                    'description' => $description,
                    'released' => $data['release_date']['date'] ?? null,
                    'background_image' => $this->resolveHeroImage($appId, $data, $screenshots),
                    'rating' => $rating,
                    'ratings_count' => $ratingsCount,
                    'metacritic' => $metacritic,
                    'genres' => collect($data['genres'] ?? [])->pluck('description')->filter()->values()->all(),
                    'platforms' => $platforms,
                    'developers' => $data['developers'] ?? [],
                    'publishers' => $data['publishers'] ?? [],
                    'tags' => collect($data['categories'] ?? [])->pluck('description')->take(8)->values()->all(),
                    'screenshots' => $screenshots,
                    'website' => $data['website'] ?? null,
                ];
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * Steam header_image is only ~460×215 — soft when stretched as a page hero.
     * background_raw is often a near-empty dark blur for indie titles — prefer real art.
     *
     * @param  list<string>  $screenshots
     */
    private function resolveHeroImage(int $appId, array $data, array $screenshots): ?string
    {
        $libraryHero = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$appId}/library_hero.jpg";

        try {
            if (Http::timeout(3)->head($libraryHero)->successful()) {
                return $libraryHero;
            }
        } catch (\Throwable) {
        }

        if (! empty($screenshots[0])) {
            return $screenshots[0];
        }

        if (! empty($data['header_image'])) {
            return $data['header_image'];
        }

        $backgroundRaw = $data['background_raw'] ?? null;

        if (is_string($backgroundRaw)
            && $backgroundRaw !== ''
            && ! str_contains($backgroundRaw, 'storepagebackground')) {
            return $backgroundRaw;
        }

        return null;
    }

    /**
     * @return array{rating: float, total_reviews: int}
     */
    private function fetchReviewSummary(int $appId): array
    {
        try {
            $response = Http::timeout(8)->get("https://store.steampowered.com/appreviews/{$appId}", [
                'json' => 1,
                'language' => 'all',
                'num_per_page' => 0,
            ]);

            if (! $response->successful()) {
                return ['rating' => 0, 'total_reviews' => 0];
            }

            $summary = $response->json('query_summary', []);
            $reviewScore = (int) ($summary['review_score'] ?? 0);

            return [
                'rating' => $reviewScore > 0 ? round($reviewScore / 2, 1) : 0,
                'total_reviews' => (int) ($summary['total_reviews'] ?? 0),
            ];
        } catch (\Throwable) {
            return ['rating' => 0, 'total_reviews' => 0];
        }
    }
}
