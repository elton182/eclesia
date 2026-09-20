<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarioItem */
class CalendarioItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'local' => $this->whenLoaded('local', fn () => new CalendarioLocalResource($this->local)),
            'tipo_id' => $this->tipo_id,
            'tipo' => $this->whenLoaded('tipo', fn () => new CalendarioEventoTipoResource($this->tipo)),
            'data' => $this->data?->toDateString(),
            'hora' => $this->hora,
            'secao' => $this->secao,
            'titulo' => $this->titulo,
            'pessoa_id' => $this->pessoa_id,
            'celebrante_nome' => $this->celebrante_nome,
            'notas' => $this->notas,
            'observacao_id' => $this->observacao_id,
            'ordem' => $this->ordem,
        ];
    }
}
