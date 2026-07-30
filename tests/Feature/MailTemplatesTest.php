<?php

namespace Tests\Feature;

use App\Models\PriceAlert;
use App\Models\User;
use App\Notifications\PriceAlertReached;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function renderPriceAlert(User $user): string
    {
        $alert = $user->priceAlerts()->create([
            'game_id' => '96',
            'title' => 'Cyberpunk 2077',
            'target_price' => 35.00,
        ]);

        return (string) (new PriceAlertReached($alert, 29.99))->toMail($user)->render();
    }

    public function test_price_alert_mail_shows_the_game_and_both_prices(): void
    {
        $user = User::factory()->create(['name' => 'Noa']);

        $html = $this->renderPriceAlert($user);

        $this->assertStringContainsString('Cyberpunk 2077', $html);
        $this->assertStringContainsString('29,99 €', $html);
        $this->assertStringContainsString('35,00 €', $html);
        // 35,00 − 29,99 : l'écart avec l'objectif est mis en avant.
        $this->assertStringContainsString('5,01 € sous votre objectif', $html);
        $this->assertStringContainsString(url('/game/96'), $html);
        $this->assertStringContainsString('Noa', $html);
    }

    public function test_price_alert_subject_carries_the_game_and_price(): void
    {
        $user = User::factory()->create();

        $alert = new PriceAlert;
        $alert->game_id = '96';
        $alert->title = 'Cyberpunk 2077';
        $alert->target_price = 35.00;

        $subject = (new PriceAlertReached($alert, 29.99))->toMail($user)->subject;

        $this->assertSame(
            config('app.name').' — Prix cible atteint : Cyberpunk 2077 à 29,99 €',
            $subject,
        );
    }

    public function test_mail_layout_links_to_the_legal_pages(): void
    {
        $html = $this->renderPriceAlert(User::factory()->create());

        $this->assertStringContainsString(route('legal.notice'), $html);
        $this->assertStringContainsString(route('legal.privacy'), $html);
        $this->assertStringContainsString(route('legal.terms'), $html);
    }

    public function test_verification_mail_uses_the_branded_template(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Noa']);

        $mail = (new VerifyEmail)->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame(config('app.name').' — Confirmez votre adresse e-mail', $mail->subject);
        $this->assertStringContainsString('Confirmez votre adresse e-mail', $html);
        $this->assertStringContainsString('Confirmer mon adresse', $html);
        $this->assertStringContainsString('/email/verify/'.$user->id, $html);
        // Le gabarit maison ne passe plus par la mise en page markdown de Laravel.
        $this->assertStringNotContainsString('Whoops!', $html);
    }

    public function test_password_reset_mail_uses_the_branded_template(): void
    {
        $user = User::factory()->create();

        $mail = (new ResetPassword('un-jeton-de-test'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame(
            config('app.name').' — Réinitialisation de votre mot de passe',
            $mail->subject,
        );
        $this->assertStringContainsString('Choisir un nouveau mot de passe', $html);
        $this->assertStringContainsString('un-jeton-de-test', $html);
        $this->assertStringContainsString($user->email, $html);
        $this->assertStringContainsString('60 minutes', $html);
    }
}
