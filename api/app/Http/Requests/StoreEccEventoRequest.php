<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EccEvento;
use App\Services\EccVisibilityScope;
use App\Services\IgrejaContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEccEventoRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:255'],
            'evento_tipo_id' => [
                'required_without:tipo',
                'nullable',
                'string',
                Rule::exists('evento_tipos', 'id')->where(fn ($q) => $q->where('igreja_id', $igrejaId)),
            ],
            'tipo' => ['required_without:evento_tipo_id', 'nullable', 'string', 'max:64'],
            'origem' => ['nullable', 'string', Rule::in([EccEvento::ORIGEM_ECC, EccEvento::ORIGEM_GERAL])],
            'inicia_em' => ['required', 'date'],
            'termina_em' => ['nullable', 'date', 'after_or_equal:inicia_em'],
            'local' => ['nullable', 'string', 'max:255'],
            'casal_compras_id' => [
                'nullable',
                'string',
                Rule::exists('casais', 'id')->where(fn ($q) => $q->where('igreja_id', $igrejaId)),
            ],
        ];
    }
}
