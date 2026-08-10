<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NexardaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PriceAlertApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_alerts_api(): void
    {
        $this->getJson('/api/alerts')->assertUnauthorized();
        $this->postJson('/api/alerts/check')->assertUnauthorized();
    }

    public function test_user_can_create_list_update_and_delete_alert(): void
    {
        $user = User::factory()->create();

        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('getPrices')
                ->with(96)
                ->andReturn(['lowest' => 40.00]);
        });

        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
                'target_price' => 25.50,
                'current_price' => 40.00,
            ])
            ->assertCreated()
            ->assertJsonPath('target_price', '25.50');

        $this->actingAs($user)
            ->getJson('/api/alerts')
            ->assertOk()
            ->assertJsonCount(1);

        $this->actingAs($user)
            ->patchJson('/api/alerts/96', [
                'current_price' => 30.00,
            ])
            ->assertOk()
            ->assertJsonPath('current_price', '30.00');

        $this->actingAs($user)
            ->deleteJson('/api/alerts/96')
            ->assertOk();

        $this->assertSame(0, $user->priceAlerts()->count());
    }

    public function test_creating_alert_again_resets_reached_state(): void
    {
        $user = User::factory()->create();

        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('getPrices')
                ->with(96)
                ->andReturn(['lowest' => 30.00]);
        });

        $user->priceAlerts()->create([
            'game_id' => '96',
            'title' => 'Cyberpunk 2077',
            'target_price' => 20.00,
            'is_reached' => true,
            'notified_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
                'target_price' => 18.00,
                'current_price' => 30.00,
            ])
            ->assertCreated();

        $alert = $user->priceAlerts()->sole();
        $this->assertFalse((bool) $alert->is_reached);
        $this->assertNull($alert->notified_at);
        $this->assertSame('18.00', (string) $alert->target_price);
    }

    public function test_check_does_not_trigger_when_price_is_still_above_target(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $user->priceAlerts()->create([
            'game_id' => '96',
            'title' => 'Cyberpunk 2077',
            'target_price' => 20.00,
            'is_reached' => false,
        ]);

        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('getPrices')
                ->with(96)
                ->once()
                ->andReturn(['lowest' => 35.00]);
        });

        $this->actingAs($user)
            ->postJson('/api/alerts/check')
            ->assertOk()
            ->assertJsonCount(0, 'triggered');

        $this->assertDatabaseHas('price_alerts', [
            'game_id' => '96',
            'is_reached' => false,
        ]);

        Notification::assertNothingSent();
    }

    public function test_alert_validation_rejects_invalid_target_price(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
                'target_price' => -1,
                'current_price' => 40.00,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_price');
    }

    public function test_alert_validation_rejects_target_price_above_or_equal_to_current(): void
    {
        $user = User::factory()->create();

        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('getPrices')
                ->with(96)
                ->twice()
                ->andReturn(['lowest' => 33.62]);
        });

        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'game_id' => '96',
                'title' => 'Halo: Campaign Evolved',
                'target_price' => 60.00,
                'current_price' => 33.62,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_price');

        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'game_id' => '96',
                'title' => 'Halo: Campaign Evolved',
                'target_price' => 33.62,
                'current_price' => 33.62,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_price');

        $this->assertSame(0, $user->priceAlerts()->count());
    }

    public function test_alert_uses_live_price_when_client_sends_stale_current_price(): void
    {
        $user = User::factory()->create();

        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('getPrices')
                ->with(96)
                ->once()
                ->andReturn(['lowest' => 20.00]);
        });

        // Le client ment / a un prix obsolète à 50€, mais le live est à 20€ :
        // un objectif à 25€ doit être refusé.
        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'game_id' => '96',
                'title' => 'Cyberpunk 2077',
                'target_price' => 25.00,
                'current_price' => 50.00,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_price');
    }
}
