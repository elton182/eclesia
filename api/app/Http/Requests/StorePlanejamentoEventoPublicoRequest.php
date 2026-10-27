<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanejamentoEventoPublicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i'],
            'participantes_media' => ['nullable', 'integer', 'min:0'],
            'observacoes' => ['nullable', 'string'],
            'local_texto' => ['nullable', 'string', 'max:255'],
            'recursos' => ['sometimes', 'array', 'max:30'],
            'recursos.*.recurso_id' => ['required', 'string', 'distinct', 'exists:planejamento_recursos,id'],
            'recursos.*.quantidade' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
