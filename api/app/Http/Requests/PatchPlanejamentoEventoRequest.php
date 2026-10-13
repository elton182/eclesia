<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PlanejamentoEvento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatchPlanejamentoEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'data_inicio' => ['sometimes', 'date'],
            'data_fim' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', Rule::in(PlanejamentoEvento::STATUSES)],
            'notas_revisao' => ['nullable', 'string'],
        ];
    }
}
