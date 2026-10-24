<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EccRecado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEccRecadoRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:180'],
            'corpo' => ['required', 'string', 'max:10000'],
            'urgente' => ['sometimes', 'boolean'],
            'fixado' => ['sometimes', 'boolean'],
            'escopo' => ['required', 'string', Rule::in([
                EccRecado::ESCOPO_PAROQUIA,
                EccRecado::ESCOPO_ETAPA,
                EccRecado::ESCOPO_EQUIPE,
            ])],
            'equipe_id' => ['nullable', 'string'],
            'etapa' => ['nullable', 'integer', 'min:1', 'max:3'],
            'publicado_em' => ['nullable', 'date'],
            'valido_ate' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in([
                EccRecado::STATUS_RASCUNHO,
                EccRecado::STATUS_PUBLICADO,
            ])],
        ];
    }
}
