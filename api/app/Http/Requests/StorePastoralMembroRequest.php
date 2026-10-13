<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Pastoral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePastoralMembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'exists:users,ulid'],
            'papel' => ['required', 'string', Rule::in(Pastoral::PAPEIS)],
        ];
    }
}
