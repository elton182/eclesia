<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SiteForm
 */
class SiteFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'slug' => $this->slug,
            'descricao' => $this->descricao,
            'ativo' => (bool) $this->ativo,
            'destino_email' => $this->destino_email,
            'sucesso_mensagem' => $this->sucesso_mensagem,
            'fields' => SiteFormFieldResource::collection($this->whenLoaded('fields')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
