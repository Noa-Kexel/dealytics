<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_favorites_api(): void
    {
        $this->getJson('/api/favorites')->assertUnauthorized();
    }

    public function test_user_can_list_add_check_and_remove_favorites(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/favorites', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
                'thumb' => 'https://exemple.com/cover.png',
            ])
            ->assertCreated()
            ->assertJsonPath('game_id', '96');

        $this->actingAs($user)
            ->getJson('/api/favorites')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.title', 'Cyberpunk 2077');

        $this->actingAs($user)
            ->postJson('/api/favorites/check', ['game_ids' => ['96', '1']])
            ->assertOk()
            ->assertJson(['96']);

        // updateOrCreate : même game_id → pas de doublon
        $this->actingAs($user)
            ->postJson('/api/favorites', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077 — Ultimate',
                'thumb' => 'https://exemple.com/cover2.png',
            ])
            ->assertCreated();

        $this->assertSame(1, $user->favorites()->count());
        $this->assertSame('Cyberpunk 2077 — Ultimate', $user->favorites()->sole()->title);

        $this->actingAs($user)
            ->deleteJson('/api/favorites/96')
            ->assertOk();

        $this->assertSame(0, $user->favorites()->count());
    }

    public function test_favorites_validation_rejects_invalid_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/favorites', [
                'game_id' => '',
                'title' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['game_id', 'title']);
    }
}
