<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EscalaAccessService;
use App\Services\IgrejaContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEscalaTipoRequest extends FormRequest
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
        $igrejaId = app(IgrejaContext::class)->current()->id;
        $tipoId = (string) $this->route('id');

        return [
            'nome' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('escala_tipos', 'nome')
                    ->where(static fn ($query) => $query->where('igreja_id', $igrejaId))
                    ->ignore($tipoId),
            ],
            'descricao' => ['nullable', 'string'],
            'unidade_preferida' => ['sometimes', Rule::in(['pessoa', 'casal', 'ambos'])],
            'recorrencia' => ['sometimes', Rule::in(['avulsa', 'semanal', 'mensal'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nome.unique' => 'Já existe um tipo de escala com este nome nesta igreja.',
        ];
    }
}
