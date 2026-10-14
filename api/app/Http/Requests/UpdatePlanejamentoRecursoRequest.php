<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PlanejamentoRecurso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanejamentoRecursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:120'],
            'tipo' => ['sometimes', 'string', Rule::in(PlanejamentoRecurso::TIPOS)],
            'quantidade' => ['sometimes', 'integer', 'min:1', 'max:9999'],
            'descricao' => ['nullable', 'string', 'max:1000'],
            'ativo' => ['sometimes', 'boolean'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
