<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CalendarioEventoTipo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarioEventoTipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:80'],
            'secao_padrao' => ['sometimes', Rule::in([
                CalendarioEventoTipo::SECAO_GRADE,
                CalendarioEventoTipo::SECAO_FESTA,
                CalendarioEventoTipo::SECAO_CASAMENTO,
            ])],
            'exige_titulo' => ['sometimes', 'boolean'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
