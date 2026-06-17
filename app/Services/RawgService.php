<?php

namespace App\Services;

use App\Support\GameDescriptionFormatter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RawgService
{
    private string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.rawg.base_url');
        $this->apiKey = config('services.rawg.key');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }

    /**
     * Search for a game by title and return the best match.
     */
    public function searchGame(string $title): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = 'rawg_search_'.md5($title);

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($title) {
            try {
                $response = Http::timeout(5)->get("{$this->baseUrl}/games", [
                    'key' => $this->apiKey,
                    'search' => $title,
                    'page_size' => 1,
                    'search_precise' => true,
                ]);

                if ($response->successful()) {
                    $results = $response->json('results', []);

                    return ! empty($results) ? $results[0] : null;
                }
            } catch (\Throwable) {
                // API down or timeout
            }

            return null;
        });
    }

    /**
     * Get full game details by RAWG ID (includes description, screenshots, etc.).
     */
    public function getGame(int $rawgId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $cacheKey = "rawg_game_{$rawgId}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($rawgId) {
            try {
                $response = Http::timeout(5)->get("{$this->baseUrl}/games/{$rawgId}", [
                    'key' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable) {
                // API down or timeout
            }

            return null;
        });
    }

    /**
     * Get screenshots for a game.
     */
    public function getScreenshots(int $rawgId): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $cacheKey = "rawg_screenshots_{$rawgId}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($rawgId) {
            try {
                $response = Http::timeout(5)->get("{$this->baseUrl}/games/{$rawgId}/screenshots", [
                    'key' => $this->apiKey,
                    'page_size' => 6,
                ]);

                if ($response->successful()) {
                    return $response->json('results', []);
                }
            } catch (\Throwable) {
                // API down or timeout
            }

            return [];
        });
    }

    /**
     * Search and get full details for a game title.
     * Combines search + detail + screenshots in one call.
     */
    public function getGameByTitle(string $title): ?array
    {
        $searchResult = $this->searchGame($title);

        if (! $searchResult) {
            return null;
        }

        $details = $this->getGame($searchResult['id']);

        if (! $details) {
            return null;
        }

        $screenshots = $this->getScreenshots($searchResult['id']);

        return [
            'source' => 'rawg',
            'id' => $details['id'],
            'name' => $details['name'],
            'description' => GameDescriptionFormatter::fromPlainText($details['description_raw'] ?? ''),
            'released' => $details['released'] ?? null,
            'background_image' => $details['background_image'] ?? null,
            'rating' => $details['rating'] ?? 0,
            'ratings_count' => $details['ratings_count'] ?? 0,
            'metacritic' => $details['metacritic'] ?? null,
            'playtime' => $details['playtime'] ?? 0,
            'genres' => collect($details['genres'] ?? [])->pluck('name')->toArray(),
            'platforms' => collect($details['platforms'] ?? [])->pluck('platform.name')->toArray(),
            'developers' => collect($details['developers'] ?? [])->pluck('name')->toArray(),
            'publishers' => collect($details['publishers'] ?? [])->pluck('name')->toArray(),
            'tags' => collect($details['tags'] ?? [])->take(8)->pluck('name')->toArray(),
            'screenshots' => collect($screenshots)->pluck('image')->toArray(),
            'website' => $details['website'] ?? null,
        ];
    }
}
