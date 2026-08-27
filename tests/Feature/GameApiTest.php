<?php

namespace Tests\Feature;

use App\Models\PriceSnapshot;
use App\Models\User;
use App\Services\ItadService;
use App\Services\NexardaService;
use App\Services\SteamStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GameApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders(): void
    {
        $this->get(route('home'))->assertOk();
    }

    public function test_home_page_accepts_list_filter_query_params(): void
    {
        $this->get('/?q=elden&platform=steam&max=20&sort=price&sale=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Home'));
    }

    public function test_game_show_page_passes_game_id(): void
    {
        $this->get(route('game.show', ['id' => '96']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Game/Show')
                ->where('gameId', '96'));
    }

    public function test_favorites_and_dashboard_pages_require_auth(): void
    {
        $this->get(route('favorites'))->assertRedirect(route('login'));
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_favorites_and_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('favorites'))->assertOk();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_games_search_endpoint_uses_nexarda_service(): void
    {
        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('searchGames')
                ->with('hollow', 1)
                ->once()
                ->andReturn([
                    'games' => [[
                        'id' => '1',
                        'title' => 'Hollow Knight',
                        'image' => null,
                        'price' => 7.49,
                        'normalPrice' => 14.99,
                        'discount' => 50,
                        'upcoming' => false,
                        'platforms' => ['steam'],
                    ]],
                    'page' => 1,
                    'pages' => 1,
                    'total' => 1,
                ]);
        });

        $this->getJson('/api/games?q=hollow')
            ->assertOk()
            ->assertJsonPath('games.0.title', 'Hollow Knight')
            ->assertJsonPath('games.0.normalPrice', 14.99);
    }

    public function test_nexarda_by_id_returns_prices_and_records_snapshot(): void
    {
        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('getPrices')
                ->with(96)
                ->once()
                ->andReturn([
                    'game' => ['id' => 96, 'name' => 'Cyberpunk 2077', 'cover' => null],
                    'currency' => 'EUR',
                    'currencySymbol' => '€',
                    'lowest' => 29.99,
                    'highest' => 59.99,
                    'maxDiscount' => 50,
                    'storeCount' => 3,
                    'offerCount' => 5,
                    'editions' => [],
                    'offers' => [],
                ]);
        });

        $this->getJson('/api/nexarda/game/96')
            ->assertOk()
            ->assertJsonPath('lowest', 29.99)
            ->assertJsonPath('game.name', 'Cyberpunk 2077');

        $this->assertDatabaseHas('price_snapshots', [
            'game_id' => '96',
            'price' => 29.99,
            'discount' => 50,
        ]);
    }

    public function test_nexarda_by_id_returns_404_when_missing(): void
    {
        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('getPrices')->with(999)->once()->andReturn(null);
        });

        $this->getJson('/api/nexarda/game/999')
            ->assertNotFound()
            ->assertJsonPath('error', 'Game not found on Nexarda');
    }

    public function test_history_prefers_itad_when_available(): void
    {
        $this->mock(ItadService::class, function ($mock) {
            $mock->shouldReceive('getPriceHistoryForGame')
                ->with('Cyberpunk 2077', null)
                ->once()
                ->andReturn([
                    ['date' => 1700000000, 'price' => 40.0],
                    ['date' => 1701000000, 'price' => 29.99],
                ]);
        });

        $this->getJson('/api/games/96/history?title='.urlencode('Cyberpunk 2077'))
            ->assertOk()
            ->assertJsonPath('source', 'itad')
            ->assertJsonCount(2, 'history');
    }

    public function test_history_falls_back_to_snapshots(): void
    {
        PriceSnapshot::record('96', 35.00, 20);

        $this->mock(ItadService::class, function ($mock) {
            $mock->shouldReceive('getPriceHistoryForGame')->never();
        });

        $this->getJson('/api/games/96/history')
            ->assertOk()
            ->assertJsonPath('source', 'snapshots')
            ->assertJsonPath('history.0.price', 35);
    }

    public function test_steam_endpoint_returns_enrichment_or_404(): void
    {
        $this->mock(SteamStoreService::class, function ($mock) {
            $mock->shouldReceive('getGameByTitle')
                ->with('Hades')
                ->once()
                ->andReturn([
                    'id' => 1145360,
                    'name' => 'Hades',
                    'description' => 'Roguelike',
                    'released' => '2020-09-17',
                    'background_image' => null,
                    'rating' => 4.8,
                    'ratings_count' => 1000,
                    'metacritic' => 93,
                    'genres' => [],
                    'platforms' => [],
                    'developers' => [],
                    'publishers' => [],
                    'tags' => [],
                    'screenshots' => [],
                    'website' => null,
                ]);
        });

        $this->getJson('/api/steam/'.rawurlencode('Hades'))
            ->assertOk()
            ->assertJsonPath('name', 'Hades')
            ->assertJsonPath('metacritic', 93);
    }

    public function test_steam_hero_prefers_screenshot_over_empty_background(): void
    {
        Cache::flush();

        $appId = 4231820;
        $header = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$appId}/header.jpg";
        $bgRaw = "https://store.akamai.steamstatic.com/images/storepagebackground/app/{$appId}";
        $screenshot = 'https://example.com/ss.1920x1080.jpg';
        $libraryHero = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$appId}/library_hero.jpg";

        Http::fake([
            'store.steampowered.com/api/storesearch/*' => Http::response([
                'items' => [['id' => $appId, 'name' => "Castlevania: Belmont's Curse"]],
            ], 200),
            'store.steampowered.com/api/appdetails*' => Http::response([
                (string) $appId => [
                    'success' => true,
                    'data' => [
                        'name' => "Castlevania: Belmont's Curse",
                        'header_image' => $header,
                        'background_raw' => $bgRaw,
                        'screenshots' => [
                            ['path_full' => $screenshot],
                        ],
                        'platforms' => ['windows' => true, 'mac' => false, 'linux' => false],
                        'genres' => [],
                        'categories' => [],
                        'developers' => [],
                        'publishers' => [],
                        'release_date' => ['date' => '14 Oct, 2026'],
                        'short_description' => 'Test',
                    ],
                ],
            ], 200),
            'store.steampowered.com/appreviews/*' => Http::response([
                'query_summary' => ['review_score' => 0, 'total_reviews' => 0],
            ], 200),
            $libraryHero => Http::response('', 404),
        ]);

        $game = app(SteamStoreService::class)->getGameByTitle("Castlevania: Belmont's Curse");

        $this->assertNotNull($game);
        $this->assertSame($screenshot, $game['background_image']);
    }

    public function test_nexarda_search_maps_normal_price_via_price_helper(): void
    {
        Cache::flush();

        Http::fake([
            'www.nexarda.com/api/v3/search*' => Http::response([
                'results' => [
                    'items' => [[
                        'title' => 'Test Game',
                        'image' => null,
                        'game_info' => [
                            'id' => 42,
                            'name' => 'Test Game',
                            'lowest_price' => 10,
                            'highest_discount' => 50,
                            'upcoming' => false,
                            'platforms' => [['slug' => 'steam']],
                        ],
                    ]],
                    'page' => 1,
                    'pages' => 1,
                    'total' => 1,
                ],
            ], 200),
        ]);

        $result = app(NexardaService::class)->searchGames('test', 1);

        $this->assertSame('42', $result['games'][0]['id']);
        $this->assertSame(10.0, $result['games'][0]['price']);
        $this->assertSame(20.0, $result['games'][0]['normalPrice']);
        $this->assertSame(50, $result['games'][0]['discount']);
    }

    public function test_steam_enrichment_is_skipped_when_no_title_matches(): void
    {
        Cache::flush();

        // Steam remonte systématiquement un « meilleur » résultat : sans garde-fou
        // la fiche de GTA VI héritait de la jaquette de Vice City.
        Http::fake([
            'store.steampowered.com/api/storesearch/*' => Http::response([
                'items' => [
                    ['id' => 1546990, 'name' => 'Grand Theft Auto: Vice City – The Definitive Edition'],
                    ['id' => 271590, 'name' => 'Grand Theft Auto V'],
                ],
            ], 200),
            'store.steampowered.com/api/appdetails*' => Http::response([], 200),
        ]);

        $this->assertNull(app(SteamStoreService::class)->getGameByTitle('Grand Theft Auto VI'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'appdetails'));
    }

    public function test_steam_enrichment_tolerates_edition_suffixes(): void
    {
        Cache::flush();

        $appId = 489830;

        Http::fake([
            'store.steampowered.com/api/storesearch/*' => Http::response([
                'items' => [['id' => $appId, 'name' => 'The Elder Scrolls V: Skyrim Special Edition']],
            ], 200),
            'store.steampowered.com/api/appdetails*' => Http::response([
                (string) $appId => [
                    'success' => true,
                    'data' => [
                        'name' => 'The Elder Scrolls V: Skyrim Special Edition',
                        'platforms' => ['windows' => true, 'mac' => false, 'linux' => false],
                        'short_description' => 'RPG',
                    ],
                ],
            ], 200),
            'store.steampowered.com/appreviews/*' => Http::response([
                'query_summary' => ['review_score' => 0, 'total_reviews' => 0],
            ], 200),
            '*library_hero.jpg' => Http::response('', 404),
        ]);

        $game = app(SteamStoreService::class)->getGameByTitle('The Elder Scrolls V: Skyrim');

        $this->assertNotNull($game);
        $this->assertSame($appId, $game['id']);
    }

    public function test_nexarda_prices_count_only_available_offers(): void
    {
        Cache::flush();

        Http::fake([
            'www.nexarda.com/api/v3/prices*' => Http::response([
                'success' => true,
                'info' => ['id' => 96, 'name' => 'Cyberpunk 2077', 'cover' => null],
                'prices' => [
                    'currency' => 'EUR',
                    'currency_symbol' => '€',
                    'lowest' => 29.99,
                    'highest' => 59.99,
                    'max_discount' => 50,
                    // Totaux bruts de Nexarda : ils incluent les offres épuisées.
                    'stores' => 34,
                    'offers' => 65,
                    'editions' => [],
                    'list' => [
                        ['store' => ['name' => 'Steam'], 'price' => 29.99, 'available' => true],
                        ['store' => ['name' => 'GOG'], 'price' => 31.99, 'available' => true],
                        ['store' => ['name' => 'Steam'], 'price' => 34.99, 'available' => true],
                        ['store' => ['name' => 'Fanatical'], 'price' => 19.99, 'available' => false],
                    ],
                ],
            ], 200),
        ]);

        $prices = app(NexardaService::class)->getPrices(96);

        $this->assertCount(3, $prices['offers']);
        $this->assertSame(3, $prices['offerCount']);
        $this->assertSame(2, $prices['storeCount']);
    }
}
