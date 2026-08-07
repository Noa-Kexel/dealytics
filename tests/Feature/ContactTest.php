<?php

namespace Tests\Feature;

use App\Mail\ContactMessageConfirmation;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Camille Dubois',
            'email' => 'camille@exemple.com',
            'subject' => 'Un prix me semble incorrect',
            'message' => 'Le prix affiché pour Hollow Knight ne correspond pas à celui de la boutique.',
            ...$overrides,
        ];
    }

    private function enableTurnstile(): void
    {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);
    }

    public function test_contact_page_is_public(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Contact')
                ->where('contactEmail', config('legal.editor.email'))
                ->where('turnstileSiteKey', null)
                ->where('defaults.name', '')
                ->where('defaults.email', ''),
            );
    }

    public function test_contact_page_exposes_the_turnstile_site_key_when_configured(): void
    {
        $this->enableTurnstile();

        $this->get(route('contact'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('turnstileSiteKey', 'test-site-key'),
            );
    }

    public function test_contact_page_prefills_the_logged_in_user(): void
    {
        $user = User::factory()->create(['name' => 'Noa', 'email' => 'noa@exemple.com']);

        $this->actingAs($user)
            ->get(route('contact'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('defaults.name', 'Noa')
                ->where('defaults.email', 'noa@exemple.com'),
            );
    }

    public function test_a_visitor_can_submit_the_form(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Camille Dubois',
            'email' => 'camille@exemple.com',
            'subject' => 'Un prix me semble incorrect',
            'user_id' => null,
            'read_at' => null,
        ]);
    }

    public function test_submission_emails_both_the_owner_and_the_sender(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload());

        Mail::assertQueued(
            ContactMessageReceived::class,
            fn (ContactMessageReceived $mail) => $mail->hasTo(config('legal.editor.email')),
        );

        Mail::assertQueued(
            ContactMessageConfirmation::class,
            fn (ContactMessageConfirmation $mail) => $mail->hasTo('camille@exemple.com'),
        );
    }

    public function test_a_logged_in_sender_is_linked_to_their_account(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('contact.store'), $this->payload());

        $this->assertDatabaseHas('contact_messages', ['user_id' => $user->id]);
    }

    public function test_the_form_is_validated(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload([
            'email' => 'pas-un-email',
            'message' => 'trop court',
        ]))->assertSessionHasErrors(['email', 'message']);

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingQueued();
    }

    public function test_the_honeypot_rejects_bots(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingQueued();
    }

    public function test_turnstile_is_required_when_enabled(): void
    {
        Mail::fake();
        $this->enableTurnstile();

        $this->post(route('contact.store'), $this->payload())
            ->assertSessionHasErrors('turnstile_token');

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingQueued();
    }

    public function test_turnstile_rejects_an_invalid_token(): void
    {
        Mail::fake();
        $this->enableTurnstile();

        Http::fake([
            config('services.turnstile.verify_url') => Http::response(['success' => false], 200),
        ]);

        $this->post(route('contact.store'), $this->payload([
            'turnstile_token' => 'token-invalide',
        ]))->assertSessionHasErrors('turnstile_token');

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingQueued();
    }

    public function test_turnstile_accepts_a_valid_token(): void
    {
        Mail::fake();
        $this->enableTurnstile();

        Http::fake([
            config('services.turnstile.verify_url') => Http::response(['success' => true], 200),
        ]);

        $this->post(route('contact.store'), $this->payload([
            'turnstile_token' => 'token-valide',
        ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('contact_messages', 1);

        Http::assertSent(fn ($request) => $request->url() === config('services.turnstile.verify_url')
            && $request['response'] === 'token-valide'
            && $request['secret'] === 'test-secret-key');
    }

    public function test_the_admin_panel_lists_the_messages(): void
    {
        ContactMessage::create($this->payload());

        $this->actingAs($this->admin())
            ->get(route('admin.contact.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Contact')
                ->has('messages', 1)
                ->where('messages.0.subject', 'Un prix me semble incorrect')
                ->where('messages.0.is_read', false)
                ->where('unreadCount', 1),
            );
    }

    public function test_the_contact_tab_is_closed_to_regular_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.contact.index'))
            ->assertForbidden();
    }

    public function test_an_admin_can_toggle_the_read_flag(): void
    {
        $message = ContactMessage::create($this->payload());
        $admin = $this->admin();

        $this->actingAs($admin)->patch(route('admin.contact.read', $message))->assertRedirect();
        $this->assertNotNull($message->fresh()->read_at);

        $this->actingAs($admin)->patch(route('admin.contact.read', $message))->assertRedirect();
        $this->assertNull($message->fresh()->read_at);
    }

    public function test_an_admin_can_delete_a_message(): void
    {
        $message = ContactMessage::create($this->payload());

        $this->actingAs($this->admin())
            ->delete(route('admin.contact.destroy', $message))
            ->assertRedirect();

        $this->assertDatabaseCount('contact_messages', 0);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }
}
