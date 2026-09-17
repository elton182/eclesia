<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use App\Services\IgrejaContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEccEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EccVisibilityScope::class)->userCan('ecc.eventos.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $igrejaId = app(IgrejaContext::class)->current()->id;

        return [
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'evento_tipo_id' => [
                'nullable',
                'string',
                Rule::exists('evento_tipos', 'id')->where(fn ($q) => $q->where('igreja_id', $igrejaId)),
            ],
            'tipo' => ['nullable', 'string', 'max:64'],
            'inicia_em' => ['sometimes', 'required', 'date'],
            'termina_em' => ['nullable', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
            'casal_compras_id' => [
                'nullable',
                'string',
                Rule::exists('casais', 'id')->where(fn ($q) => $q->where('igreja_id', $igrejaId)),
            ],
        ];
    }
}
