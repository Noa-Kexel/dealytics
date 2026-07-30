<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
