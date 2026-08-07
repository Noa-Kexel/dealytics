<?php

namespace App\Http\Requests;

use App\Rules\Turnstile;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            // Piège à robots : le champ est masqué en CSS, un humain le laisse vide.
            'website' => ['nullable', 'prohibited'],
            'turnstile_token' => Turnstile::isEnabled()
                ? ['required', 'string', new Turnstile]
                : ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.min' => 'Merci de détailler un peu votre demande (20 caractères minimum).',
            'website.prohibited' => 'Votre message a été refusé.',
            'turnstile_token.required' => 'La vérification anti-robot est requise.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nom',
            'email' => 'adresse e-mail',
            'subject' => 'sujet',
            'message' => 'message',
            'turnstile_token' => 'vérification anti-robot',
        ];
    }
}
