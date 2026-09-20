<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarioColetaLink */
class CalendarioColetaLinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'token_preview' => $this->token_preview,
            'rotulo' => $this->rotulo,
            'expira_em' => $this->expira_em?->toIso8601String(),
            'ativo' => $this->ativo,
        ];

    }
}
