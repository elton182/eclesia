<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EscalaAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEscalaEquipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EscalaAccessService::class)->canManage();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tipoId = (string) $this->route('tipoId');

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('escala_equipes', 'nome')->where(
                    static fn ($query) => $query->where('escala_tipo_id', $tipoId)
                ),
            ],
            'cor' => ['nullable', 'string', 'max:32'],
            'ordem' => ['nullable', 'integer', 'min:0'],
            'vagas_sugeridas' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.unique' => 'Já existe uma equipe/função com este nome neste tipo de escala.',
        ];
    }
}
