<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_budget_api(): void
    {
        $this->getJson('/api/budget')->assertUnauthorized();
        $this->putJson('/api/budget', ['monthly_limit' => 100])->assertUnauthorized();
    }

    public function test_budget_defaults_to_150_when_unset(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/budget')
            ->assertOk()
            ->assertJsonPath('monthly_limit', 150)
            ->assertJsonPath('month', now()->format('Y-m'));
    }

    public function test_user_can_update_and_read_budget_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/budget', ['monthly_limit' => 200])
            ->assertOk()
            ->assertJsonPath('monthly_limit', '200.00');

        $this->actingAs($user)
            ->getJson('/api/budget')
            ->assertOk()
            ->assertJsonPath('monthly_limit', 200);

        $this->assertDatabaseHas('budget_settings', [
            'user_id' => $user->id,
            'month' => now()->format('Y-m'),
            'monthly_limit' => 200,
        ]);
    }

    public function test_budget_validation_rejects_invalid_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/budget', ['monthly_limit' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('monthly_limit');
    }

    public function test_purchase_delete_removes_only_owned_purchase(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $purchase = $owner->purchases()->create([
            'game_title' => 'Hades',
            'price' => 12.49,
            'original_price' => 24.99,
            'store' => 'Steam',
            'purchased_at' => now(),
        ]);

        $this->actingAs($other)
            ->deleteJson('/api/purchases/'.$purchase->id)
            ->assertNotFound();

        $this->actingAs($owner)
            ->deleteJson('/api/purchases/'.$purchase->id)
            ->assertOk();

        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id]);
    }
}
