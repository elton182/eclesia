<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarioIndisponibilidade */
class CalendarioIndisponibilidadeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome_exibicao' => $this->nome_exibicao,
            'data' => $this->data?->toDateString(),
            'motivo' => $this->motivo,
            'pessoa_id' => $this->pessoa_id,
            'user_id' => $this->user_id,
        ];
    }
}
