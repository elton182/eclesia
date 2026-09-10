<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SiteComunicado
 */
class SiteComunicadoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'resumo' => $this->resumo,
            'corpo' => $this->corpo,
            'capa_media_id' => $this->capa_media_id,
            'publicado_em' => $this->publicado_em?->toIso8601String(),
            'status' => $this->status,
            'destaque' => (bool) $this->destaque,
            'igreja_id' => $this->igreja_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
