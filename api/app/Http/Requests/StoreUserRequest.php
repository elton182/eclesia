<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:username'],
            'username' => ['nullable', 'string', 'max:120', 'required_without:email'],
            'password' => ['required', 'string', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'pessoa_id' => ['sometimes', 'nullable', 'string', 'max:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => 'Informe e-mail ou login (username).',
            'username.required_without' => 'Informe e-mail ou login (username).',
        ];
    }
}
