<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class TranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_broker_messages_are_translated(): void
    {
        foreach (['reset', 'sent', 'throttled', 'token', 'user'] as $key) {
            $message = __("passwords.{$key}");

            // Sans fichier de langue, Laravel renvoie la clé brute
            // (« passwords.sent »), ce qui s'affichait tel quel à l'écran.
            $this->assertNotSame("passwords.{$key}", $message);
        }

        $this->assertStringContainsString('lien de réinitialisation', __('passwords.sent'));
    }

    public function test_the_forgot_password_form_answers_in_french(): void
    {
        User::factory()->create(['email' => 'noa@exemple.com']);

        $this->post('/forgot-password', ['email' => 'noa@exemple.com'])
            ->assertSessionHas('status', __('passwords.sent'));

        $this->assertStringNotContainsString('passwords.', session('status'));
    }

    public function test_validation_messages_are_in_french(): void
    {
        $this->post(route('contact.store'), ['name' => '', 'email' => 'x', 'subject' => '', 'message' => ''])
            ->assertSessionHasErrors([
                'name' => 'Le champ nom est obligatoire.',
                'email' => 'Le champ adresse e-mail doit être une adresse e-mail valide.',
            ]);
    }

    public function test_login_failure_is_in_french(): void
    {
        $this->assertSame('Ces identifiants ne correspondent à aucun compte.', __('auth.failed'));
    }

    public function test_the_application_defaults_to_french(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('fr', config('app.fallback_locale'));
    }

    public function test_english_stays_available_through_the_framework(): void
    {
        // Laravel embarque ses propres traductions anglaises : un serveur dont
        // le .env garde APP_LOCALE=en affichera de l'anglais, pas des clés
        // brutes. D'où l'importance de basculer APP_LOCALE sur « fr » en prod.
        App::setLocale('en');

        $this->assertNotSame('passwords.sent', __('passwords.sent'));
        $this->assertNotSame(__('passwords.sent', [], 'fr'), __('passwords.sent'));
    }
}
