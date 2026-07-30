<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\PriceAlertReached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationTestTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user->refresh();
    }

    public function test_admin_can_send_itself_a_test_notification(): void
    {
        $admin = $this->makeUser(UserRole::SuperAdmin);

        $this->actingAs($admin)
            ->post(route('admin.notifications.test'))
            ->assertRedirect();

        $notification = $admin->fresh()->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame('price_alert', $notification->data['type']);
        $this->assertSame('Cyberpunk 2077', $notification->data['title']);
    }

    public function test_regular_users_cannot_send_test_notifications(): void
    {
        $this->actingAs($this->makeUser(UserRole::User))
            ->post(route('admin.notifications.test'))
            ->assertForbidden();
    }

    public function test_guests_are_redirected_from_the_notifications_tab(): void
    {
        $this->get(route('admin.notifications.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_send_itself_the_price_alert_email(): void
    {
        Notification::fake();

        $admin = $this->makeUser(UserRole::SuperAdmin);

        $this->actingAs($admin)
            ->post(route('admin.notifications.test-email'))
            ->assertRedirect();

        Notification::assertSentTo(
            $admin,
            PriceAlertReached::class,
            // Un test d'e-mail ne doit pas créer de notification in-app en plus.
            fn ($notification, array $channels): bool => $channels === ['mail'],
        );
    }

    public function test_admin_can_preview_each_mail_template(): void
    {
        $admin = $this->makeUser(UserRole::Admin);

        foreach (['price-alert', 'verify-email', 'reset-password'] as $template) {
            $this->actingAs($admin)
                ->get(route('admin.notifications.preview', $template))
                ->assertOk()
                ->assertSee('EALYTICS', false);
        }
    }

    public function test_unknown_mail_template_returns_not_found(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->get(route('admin.notifications.preview', 'inexistant'))
            ->assertNotFound();
    }

    public function test_regular_users_cannot_preview_mail_templates(): void
    {
        $this->actingAs($this->makeUser(UserRole::User))
            ->get(route('admin.notifications.preview', 'price-alert'))
            ->assertForbidden();
    }
}
