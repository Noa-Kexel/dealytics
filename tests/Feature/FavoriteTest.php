<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Jaquette réelle renvoyée par Nexarda : 271 caractères, au-delà du
     * VARCHAR(255) par défaut.
     */
    private const NEXARDA_COVER = 'https://imgcdn1.nexarda.com/uploads/handler.gz?compress=v1&width=328&height=440&fit=cover&padding=0&bg=&url=https%3A%2F%2Fimgcdn1.nexarda.com%2Fuploads%2F-%2F2026%2F1780856246-b70c93d69e82884e45699b13c9691a88f166aa7ad77783ff7e8f4cec97c112c2.png&default=nexarda.game.cover';

    public function test_a_long_nexarda_cover_url_is_stored_intact(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/favorites', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
                'thumb' => self::NEXARDA_COVER,
            ])
            ->assertCreated();

        $this->assertSame(self::NEXARDA_COVER, $user->favorites()->sole()->thumb);
    }

    /**
     * Cas limite : une jaquette de exactement 500 caractères, la longueur
     * maximale autorisée par le contrôleur.
     *
     * Ce test passe toujours sur SQLite, qui n'applique pas les longueurs
     * déclarées. Il n'a de valeur que sur MySQL — là où le bug s'est produit —
     * et échouerait si la colonne repassait sous 500.
     */
    public function test_a_thumb_at_the_maximum_length_is_stored_intact(): void
    {
        $user = User::factory()->create();
        $thumb = 'https://exemple.com/'.str_repeat('a', 480);

        $this->assertSame(500, strlen($thumb));

        $this->actingAs($user)
            ->postJson('/api/favorites', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
                'thumb' => $thumb,
            ])
            ->assertCreated();

        $this->assertSame($thumb, $user->favorites()->sole()->thumb);
    }

    public function test_a_cover_url_beyond_the_limit_is_rejected_cleanly(): void
    {
        $user = User::factory()->create();

        // Mieux vaut une 422 explicite qu'une erreur SQL en 500.
        $this->actingAs($user)
            ->postJson('/api/favorites', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
                'thumb' => 'https://exemple.com/'.str_repeat('a', 600),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('thumb');
    }

    public function test_favorites_require_a_verified_account(): void
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->postJson('/api/favorites', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
            ])
            ->assertStatus(403);
    }
}
