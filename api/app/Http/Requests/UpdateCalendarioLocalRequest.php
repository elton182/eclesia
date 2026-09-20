<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarioLocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:120'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
