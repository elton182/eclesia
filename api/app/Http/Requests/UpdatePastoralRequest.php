<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePastoralRequest extends FormRequest
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
            'igreja_id' => ['sometimes', 'required', 'string', 'exists:igrejas,id'],
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'descricao_publica' => ['nullable', 'string'],
            'contato_publico' => ['nullable', 'string', 'max:500'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'publicado_no_site' => ['sometimes', 'boolean'],
            'ativa' => ['sometimes', 'boolean'],
        ];
    }
}
