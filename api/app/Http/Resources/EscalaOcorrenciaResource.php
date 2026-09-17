<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EscalaOcorrencia
 */
class EscalaOcorrenciaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'escala_tipo_id' => $this->escala_tipo_id,
            'titulo' => $this->titulo,
            'inicia_em' => $this->inicia_em?->toIso8601String(),
            'termina_em' => $this->termina_em?->toIso8601String(),
            'local' => $this->local,
            'evento_agenda_id' => $this->evento_agenda_id,
            'tipo_nome' => $this->whenLoaded('tipo', fn () => $this->tipo?->nome),
            'atribuicoes' => EscalaAtribuicaoResource::collection($this->whenLoaded('atribuicoes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
