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
}
