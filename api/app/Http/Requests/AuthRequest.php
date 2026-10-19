<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthRequest extends FormRequest
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
            'tenant' => ['required', 'string', 'max:255'],
            'login' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'email' => ['required_without:login', 'nullable', 'string', 'max:255'],
            'password' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório',
            'required_without' => 'Informe e-mail ou usuário',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant' => 'Organização',
            'login' => 'E-mail ou usuário',
            'email' => 'E-mail',
            'password' => 'Senha',
        ];
    }

    public function loginIdentifier(): string
    {
        $data = $this->validated();

        return trim((string) ($data['login'] ?? $data['email'] ?? ''));
    }
}
