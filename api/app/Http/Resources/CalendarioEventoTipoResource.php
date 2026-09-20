<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CalendarioEventoTipo */
class CalendarioEventoTipoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'nome' => $this->nome,
            'secao_padrao' => $this->secao_padrao,
            'exige_titulo' => (bool) $this->exige_titulo,
            'ordem' => $this->ordem,
            'ativo' => (bool) $this->ativo,
            'sistema' => (bool) $this->sistema,
        ];
    }
}
