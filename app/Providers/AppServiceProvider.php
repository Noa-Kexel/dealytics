<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureNotificationMails();
    }

    /**
     * Route the framework's built-in auth e-mails through the Dealytics
     * templates (resources/views/emails) instead of the default markdown ones.
     */
    protected function configureNotificationMails(): void
    {
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject(config('app.name').' — Confirmez votre adresse e-mail')
            ->view('emails.verify-email', [
                'userName' => $notifiable->name,
                'url' => $url,
                'expiresInMinutes' => config('auth.verification.expire', 60),
            ]));

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], absolute: false));

            return (new MailMessage)
                ->subject(config('app.name').' — Réinitialisation de votre mot de passe')
                ->view('emails.reset-password', [
                    'userName' => $notifiable->name,
                    'email' => $notifiable->getEmailForPasswordReset(),
                    'url' => $url,
                    'expiresInMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
