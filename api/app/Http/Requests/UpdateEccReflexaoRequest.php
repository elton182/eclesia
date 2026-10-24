<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EccReflexao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEccReflexaoRequest extends FormRequest
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
            'titulo' => ['sometimes', 'required', 'string', 'max:180'],
            'passagem_biblica' => ['nullable', 'string', 'max:255'],
            'texto' => ['sometimes', 'required', 'string', 'max:50000'],
            'publicado_em' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', 'string', Rule::in([
                EccReflexao::STATUS_RASCUNHO,
                EccReflexao::STATUS_PUBLICADO,
            ])],
            'imagem' => ['nullable', 'file', 'image', 'max:5120'],
            'remover_imagem' => ['sometimes', 'boolean'],
        ];
    }
}
