<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarioMensal */
class CalendarioMensalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ano' => $this->ano,
            'mes' => $this->mes,
            'status' => $this->status,
            'titulo' => $this->titulo,
            'subtitulo' => $this->subtitulo,
            'fechado_em' => $this->fechado_em?->toIso8601String(),
            'itens' => CalendarioItemResource::collection($this->whenLoaded('itens')),
            'observacoes' => $this->whenLoaded('observacoes', function () {
                $obs = $this->observacoes->values();
                $obs->each(fn ($o, $i) => $o->numero = $i + 1);

                return CalendarioObservacaoResource::collection($obs);
            }),
            'tempos_liturgicos' => CalendarioTempoLiturgicoResource::collection($this->whenLoaded('temposLiturgicos')),
            'indisponibilidades' => CalendarioIndisponibilidadeResource::collection($this->whenLoaded('indisponibilidades')),
            'coleta_links' => CalendarioColetaLinkResource::collection($this->whenLoaded('coletaLinks')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
