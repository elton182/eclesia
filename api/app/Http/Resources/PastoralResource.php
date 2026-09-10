<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Pastoral
 */
class PastoralResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'igreja_id' => $this->igreja_id,
            'igreja_nome' => $this->whenLoaded('igreja', fn () => $this->igreja?->nome),
            'nome' => $this->nome,
            'descricao_publica' => $this->descricao_publica,
            'contato_publico' => $this->contato_publico,
            'ordem' => $this->ordem,
            'publicado_no_site' => (bool) $this->publicado_no_site,
            'ativa' => (bool) $this->ativa,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
