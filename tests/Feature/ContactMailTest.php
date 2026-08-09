<?php

namespace Tests\Feature;

use App\Mail\ContactMessageConfirmation;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactMailTest extends TestCase
{
    use RefreshDatabase;

    private function message(): ContactMessage
    {
        return ContactMessage::create([
            'name' => 'Camille Dubois',
            'email' => 'camille@exemple.com',
            'subject' => 'Un prix me semble incorrect',
            'message' => "Première ligne.\n\nSeconde ligne.",
        ]);
    }

    public function test_owner_copy_carries_the_message_and_replies_to_the_sender(): void
    {
        $mail = new ContactMessageReceived($this->message());
        $html = (string) $mail->render();

        $this->assertSame(
            config('app.name').' : Nouveau message « Un prix me semble incorrect »',
            $mail->envelope()->subject,
        );
        $this->assertStringContainsString('Camille Dubois', $html);
        $this->assertStringContainsString('camille@exemple.com', $html);
        $this->assertStringContainsString('Un prix me semble incorrect', $html);
        $this->assertStringContainsString('Première ligne.', $html);
        // Les retours à la ligne du message sont préservés.
        $this->assertStringContainsString('<br />', $html);
        $this->assertStringContainsString(route('admin.contact.index'), $html);
        // Répondre à l'e-mail écrit directement à l'expéditeur.
        $this->assertTrue($mail->hasReplyTo('camille@exemple.com'));
    }

    public function test_sender_confirmation_recaps_their_own_message(): void
    {
        $mail = new ContactMessageConfirmation($this->message());
        $html = (string) $mail->render();

        $this->assertSame(
            config('app.name').' : Nous avons bien reçu votre message',
            $mail->envelope()->subject,
        );
        $this->assertStringContainsString('Camille Dubois', $html);
        $this->assertStringContainsString('Un prix me semble incorrect', $html);
        $this->assertStringContainsString('Première ligne.', $html);
        $this->assertStringContainsString(route('faq'), $html);
    }

    public function test_contact_mails_do_not_claim_the_reader_has_an_account(): void
    {
        $html = (string) (new ContactMessageConfirmation($this->message()))->render();

        // Le pied de page générique ne convient pas à un visiteur sans compte.
        $this->assertStringNotContainsString('parce que vous avez un compte', $html);
        $this->assertStringContainsString('formulaire de contact', $html);
    }

    public function test_contact_mails_escape_user_supplied_html(): void
    {
        $message = ContactMessage::create([
            'name' => 'Robot',
            'email' => 'robot@exemple.com',
            'subject' => 'Test',
            'message' => '<script>alert("xss")</script> et un message assez long.',
        ]);

        $html = (string) (new ContactMessageReceived($message))->render();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_the_existing_mails_keep_the_account_footer(): void
    {
        $user = User::factory()->unverified()->create();

        $html = (string) (new VerifyEmail)->toMail($user)->render();

        $this->assertStringContainsString('parce que vous avez un compte', $html);
    }
}
