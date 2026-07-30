<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
