<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\PlanejamentoPastoralToken */
class PlanejamentoPastoralTokenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'planejamento_anual_id' => $this->planejamento_anual_id,
            'pastoral_id' => $this->pastoral_id,
            'token_preview' => $this->token_preview,
            'token' => $this->when(isset($this->plain_token), $this->plain_token),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'revogado_em' => $this->revogado_em?->toIso8601String(),
            'ativo' => $this->isAtivo(),
        ];
    }
}
