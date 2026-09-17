<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EscalaTipo
 */
class EscalaTipoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'unidade_preferida' => $this->unidade_preferida,
            'recorrencia' => $this->recorrencia,
            'equipes_count' => $this->equipes_count ?? $this->equipes()->count(),
            'ocorrencias_count' => $this->ocorrencias_count ?? $this->ocorrencias()->count(),
            'equipes' => EscalaEquipeResource::collection($this->whenLoaded('equipes')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
