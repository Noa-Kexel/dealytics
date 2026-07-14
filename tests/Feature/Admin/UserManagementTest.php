<?php

namespace Tests\Feature\Admin;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user->refresh();
    }

    private function registrationInput(string $email): array
    {
        return [
            'name' => 'Test',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }

    public function test_first_registered_user_becomes_super_admin(): void
    {
        $first = (new CreateNewUser)->create($this->registrationInput('first@example.com'));

        $this->assertSame(UserRole::SuperAdmin, $first->role);
    }

    public function test_subsequent_users_are_regular_users(): void
    {
        (new CreateNewUser)->create($this->registrationInput('first@example.com'));
        $second = (new CreateNewUser)->create($this->registrationInput('second@example.com'));

        $this->assertSame(UserRole::User, $second->role);
    }

    public function test_guests_are_redirected_from_admin_panel(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_regular_users_cannot_access_admin_panel(): void
    {
        $this->actingAs($this->makeUser(UserRole::User))
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_view_admin_panel(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin))
            ->get(route('admin.users.index'))
            ->assertOk();
    }

    public function test_super_admin_can_create_a_user_with_a_role(): void
    {
        $this->actingAs($this->makeUser(UserRole::SuperAdmin))
            ->post(route('admin.users.store'), [
                ...$this->registrationInput('new-admin@example.com'),
                'role' => 'admin',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'new-admin@example.com',
            'role' => 'admin',
        ]);
    }

    public function test_plain_admin_cannot_assign_elevated_roles(): void
    {
        $this->actingAs($this->makeUser(UserRole::Admin))
            ->post(route('admin.users.store'), [
                ...$this->registrationInput('sneaky@example.com'),
                'role' => 'superadmin',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_plain_admin_cannot_manage_another_admin(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $otherAdmin = $this->makeUser(UserRole::Admin);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $otherAdmin))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    public function test_last_super_admin_cannot_be_demoted(): void
    {
        $super = $this->makeUser(UserRole::SuperAdmin);

        $this->actingAs($super)
            ->put(route('admin.users.update', $super), [
                'name' => $super->name,
                'email' => $super->email,
                'role' => 'user',
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', [
            'id' => $super->id,
            'role' => 'superadmin',
        ]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $super = $this->makeUser(UserRole::SuperAdmin);

        $this->actingAs($super)->delete(route('admin.users.destroy', $super));

        $this->assertDatabaseHas('users', ['id' => $super->id]);
    }

    public function test_super_admin_can_delete_a_regular_user(): void
    {
        $super = $this->makeUser(UserRole::SuperAdmin);
        $victim = $this->makeUser(UserRole::User);

        $this->actingAs($super)
            ->delete(route('admin.users.destroy', $victim))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $victim->id]);
    }
}
