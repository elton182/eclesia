<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PlanejamentoEvento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanejamentoEventoRequest extends FormRequest
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
            'pastoral_id' => ['sometimes', 'string', 'exists:pastorais,id'],
            'titulo' => ['sometimes', 'string', 'max:255'],
            'data_inicio' => ['sometimes', 'date'],
            'data_fim' => ['nullable', 'date'],
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
