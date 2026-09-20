<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarioSlotPadrao */
class CalendarioSlotPadraoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'local' => $this->whenLoaded('local', fn () => new CalendarioLocalResource($this->local)),
            'dia_semana' => $this->dia_semana,
            'hora' => $this->hora,
            'secao' => $this->secao,
            'ordem' => $this->ordem,
            'ativo' => $this->ativo,
        ];
    }
}
