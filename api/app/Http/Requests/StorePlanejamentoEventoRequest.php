<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PlanejamentoEvento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanejamentoEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pastoral_id' => ['required', 'string', 'exists:pastorais,id'],
            'titulo' => ['required', 'string', 'max:255'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['nullable', 'date', 'after_or_equal:data_inicio'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'hora_fim' => ['nullable', 'date_format:H:i'],
            'participantes_media' => ['nullable', 'integer', 'min:0'],
            'recorrencia_texto' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'local_texto' => ['nullable', 'string', 'max:255'],
            'local_ids' => ['nullable', 'array'],
            'local_ids.*' => ['string', 'exists:calendario_locais,id'],
            'status_solicitacao' => ['sometimes', 'string', Rule::in(PlanejamentoEvento::STATUS_SOLICITACAO)],
            'motivo_ajuste' => ['nullable', 'string'],
        ];
    }
}
