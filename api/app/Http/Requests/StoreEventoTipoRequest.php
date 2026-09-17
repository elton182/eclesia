<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EventoTipo;
use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventoTipoRequest extends FormRequest
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
        return [
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:64'],
            'abrev' => ['nullable', 'string', 'max:16'],
            'cor' => ['nullable', 'string', 'max:32'],
            'permite_compras' => ['nullable', 'boolean'],
            'escopo' => ['nullable', 'string', Rule::in([
                EventoTipo::ESCOPO_ECC,
                EventoTipo::ESCOPO_GERAL,
                EventoTipo::ESCOPO_AMBOS,
            ])],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
