<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()?->canAccessAdminPanel() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            ...$this->profileRules($target->id),
            // Mot de passe optionnel à la mise à jour.
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }
}
