<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarioObservacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:255'],
            'descricao' => ['sometimes', 'string'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
