<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanejamentoEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pastoral_id' => ['sometimes', 'string', 'exists:pastorais,id'],
            'titulo' => ['sometimes', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'data_inicio' => ['sometimes', 'date'],
            'data_fim' => ['nullable', 'date'],
            'dia_inteiro' => ['sometimes', 'boolean'],
        ];
    }
}
