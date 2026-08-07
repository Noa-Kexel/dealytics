<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    /**
     * Actif uniquement si explicitement activé et que les deux clés sont présentes.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('services.turnstile.enabled')
            && filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret_key'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! static::isEnabled()) {
            return;
        }

        if (! is_string($value) || $value === '') {
            return;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post((string) config('services.turnstile.verify_url'), [
                'secret' => config('services.turnstile.secret_key'),
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

        if (! $response->successful() || $response->json('success') !== true) {
            $fail('La vérification anti-robot a échoué. Merci de réessayer.');
        }
    }
}
