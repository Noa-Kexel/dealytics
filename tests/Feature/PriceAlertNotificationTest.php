<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PriceAlertReached;
use App\Services\NexardaService;
use App\Services\PriceAlertChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PriceAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_endpoint_triggers_alert_when_price_is_reached(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $user->priceAlerts()->create([
            'game_id' => '123',
            'title' => 'Test Game',
            'target_price' => 20.00,
            'is_reached' => false,
        ]);

        $this->mock(NexardaService::class, function ($mock) {
            $mock->shouldReceive('getPrices')
                ->with(123)
                ->once()
                ->andReturn(['lowest' => 15.99]);
        });

        $response = $this->actingAs($user)->postJson('/api/alerts/check');

        $response->assertOk()
            ->assertJsonPath('triggered.0.game_id', '123')
            ->assertJsonPath('triggered.0.current_price', 15.99);

        $this->assertDatabaseHas('price_alerts', [
            'user_id' => $user->id,
            'game_id' => '123',
            'is_reached' => true,
        ]);

        Notification::assertSentTo($user, PriceAlertReached::class);
    }

    public function test_checker_does_not_retrigger_already_reached_alerts(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $user->priceAlerts()->create([
            'game_id' => '456',
            'title' => 'Already Reached',
            'target_price' => 10.00,
            'current_price' => 8.00,
            'is_reached' => true,
            'notified_at' => now(),
        ]);

        $checker = app(PriceAlertChecker::class);
        $triggered = $checker->checkForUser($user);

        $this->assertEmpty($triggered);
        Notification::assertNothingSent();
    }

    public function test_notifications_api_returns_user_notifications(): void
    {
        $user = User::factory()->create();

        $alert = $user->priceAlerts()->create([
            'game_id' => '789',
            'title' => 'Notify Me',
            'target_price' => 25.00,
        ]);

        $user->notify(new PriceAlertReached($alert, 19.99));

        $response = $this->actingAs($user)->getJson('/api/notifications');

        $response->assertOk()
            ->assertJsonPath('0.type', 'price_alert')
            ->assertJsonPath('0.game_id', '789');
    }

    public function test_user_can_delete_one_of_their_notifications(): void
    {
        $user = User::factory()->create();

        $alert = $user->priceAlerts()->create([
            'game_id' => '789',
            'title' => 'Notify Me',
            'target_price' => 25.00,
        ]);

        $user->notify(new PriceAlertReached($alert, 19.99));

        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->deleteJson("/api/notifications/{$notification->id}")
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_delete_someone_elses_notification(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $alert = $owner->priceAlerts()->create([
            'game_id' => '789',
            'title' => 'Notify Me',
            'target_price' => 25.00,
        ]);

        $owner->notify(new PriceAlertReached($alert, 19.99));

        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($intruder)
            ->deleteJson("/api/notifications/{$notification->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
    }
}
