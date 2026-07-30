<?php

namespace Tests\Feature;

use App\Models\PriceSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomeStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The endpoint caches its result — start each test from a clean slate.
        Cache::flush();
    }

    public function test_stats_endpoint_returns_the_expected_shape(): void
    {
        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJsonStructure(['trackedGames', 'hotDeals', 'totalSavings']);
    }

    public function test_stats_are_computed_from_real_data(): void
    {
        $user = User::factory()->create();
        $now = now();

        // Tracked games: one via price history, one favorited, one alerted → 3 distinct.
        // 60% off on a 20€ price → original 50€, so 30€ of savings available.
        PriceSnapshot::record('game-a', 20.00, 60);

        DB::table('favorites')->insert([
            'user_id' => $user->id,
            'game_id' => 'game-b',
            'title' => 'Game B',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('price_alerts')->insert([
            'user_id' => $user->id,
            'game_id' => 'game-c',
            'title' => 'Game C',
            'target_price' => 10.00,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJson([
                'trackedGames' => 3,
                'hotDeals' => 1,
                'totalSavings' => 30,
            ]);
    }

    public function test_a_low_discount_is_not_counted_as_a_hot_deal(): void
    {
        PriceSnapshot::record('game-x', 45.00, 10); // only 10% off

        $this->getJson('/api/stats')
            ->assertOk()
            ->assertJson(['hotDeals' => 0]);
    }
}
