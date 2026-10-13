<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PlanejamentoEvento */
class PlanejamentoEventoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'planejamento_anual_id' => $this->planejamento_anual_id,
            'pastoral_id' => $this->pastoral_id,
            'pastoral_nome' => $this->whenLoaded('pastoral', fn () => $this->pastoral?->nome),
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'data_inicio' => $this->data_inicio?->toIso8601String(),
            'data_fim' => $this->data_fim?->toIso8601String(),
            'dia_inteiro' => (bool) $this->dia_inteiro,
            'status' => $this->status,
            'criado_via' => $this->criado_via,
            'notas_revisao' => $this->notas_revisao,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
