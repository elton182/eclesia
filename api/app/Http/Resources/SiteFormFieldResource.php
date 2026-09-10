<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SiteFormField
 */
class SiteFormFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'label' => $this->label,
            'tipo' => $this->tipo,
            'obrigatorio' => (bool) $this->obrigatorio,
            'opcoes' => $this->opcoes ?? [],
            'ordem' => $this->ordem,
        ];
    }
}
