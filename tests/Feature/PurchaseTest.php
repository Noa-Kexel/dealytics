<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_the_current_month_purchases(): void
    {
        $user = User::factory()->create();

        $user->purchases()->create([
            'game_title' => 'Ce mois-ci',
            'price' => 20.00,
            'original_price' => 50.00,
            'store' => 'Steam',
            'purchased_at' => now(),
        ]);
        $user->purchases()->create([
            'game_title' => 'Mois dernier',
            'price' => 15.00,
            'original_price' => 30.00,
            'store' => 'GOG',
            'purchased_at' => now()->subMonthNoOverflow(),
        ]);

        $this->actingAs($user)
            ->getJson('/api/purchases')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['game_title' => 'Ce mois-ci']);
    }

    public function test_store_accepts_a_custom_purchased_at_date(): void
    {
        $user = User::factory()->create();
        $pastDate = now()->subMonthsNoOverflow(2)->setTime(12, 0);

        $this->actingAs($user)
            ->postJson('/api/purchases', [
                'game_title' => 'Achat rétroactif',
                'price' => 9.99,
                'original_price' => 29.99,
                'store' => 'Steam',
                'purchased_at' => $pastDate->toDateString(),
            ])
            ->assertCreated()
            ->assertJsonFragment(['game_title' => 'Achat rétroactif']);

        $this->assertDatabaseHas('purchases', [
            'user_id' => $user->id,
            'game_title' => 'Achat rétroactif',
        ]);

        $this->assertTrue(
            $user->purchases()->where('game_title', 'Achat rétroactif')->first()
                ->purchased_at
                ->isSameDay($pastDate)
        );

        $this->actingAs($user)
            ->getJson('/api/purchases')
            ->assertOk()
            ->assertJsonCount(0);

        $this->actingAs($user)
            ->getJson('/api/purchases?month='.$pastDate->format('Y-m'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['game_title' => 'Achat rétroactif']);
    }

    public function test_store_persists_a_purchase_and_it_appears_in_the_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/purchases', [
                'game_title' => 'Hades',
                'price' => 12.49,
                'original_price' => 24.99,
                'store' => 'Steam',
            ])
            ->assertCreated()
            ->assertJsonFragment(['game_title' => 'Hades']);

        $this->assertDatabaseHas('purchases', [
            'user_id' => $user->id,
            'game_title' => 'Hades',
        ]);

        $this->actingAs($user)
            ->getJson('/api/purchases')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_history_sums_spending_for_the_current_month(): void
    {
        $user = User::factory()->create();

        $user->purchases()->create([
            'game_title' => 'Achat A',
            'price' => 20.00,
            'original_price' => 40.00,
            'store' => 'Steam',
            'purchased_at' => now(),
        ]);
        $user->purchases()->create([
            'game_title' => 'Achat B',
            'price' => 9.99,
            'original_price' => 19.99,
            'store' => 'GOG',
            'purchased_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/purchases/history')
            ->assertOk();

        // Six months returned, most recent last; current month totals both purchases.
        $history = $response->json();
        $this->assertCount(6, $history);
        $this->assertSame(29.99, $history[5]['spent']);
    }

    public function test_history_returns_six_distinct_months_from_the_end_of_a_month(): void
    {
        $this->travelTo(Carbon::parse('2026-08-31 12:00:00'));

        $user = User::factory()->create();

        $user->purchases()->create([
            'game_title' => 'Achat de juillet',
            'price' => 15.50,
            'original_price' => 30.00,
            'store' => 'Steam',
            'purchased_at' => Carbon::parse('2026-07-10'),
        ]);

        $history = $this->actingAs($user)
            ->getJson('/api/purchases/history')
            ->assertOk()
            ->json();

        // Six mois consécutifs distincts : mars → août, sans débordement de fin de mois.
        $this->assertCount(6, $history);
        $this->assertSame(6, count(array_unique(array_column($history, 'month'))));
        // Juillet est l'avant-dernier bucket et porte bien l'achat.
        $this->assertSame(15.50, $history[4]['spent']);
        $this->assertEquals(0, $history[5]['spent']);
    }
}
