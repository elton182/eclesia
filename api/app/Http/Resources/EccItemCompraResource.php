<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EccItemCompra
 */
class EccItemCompraResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $doadorRotulo = null;
        if ($this->relationLoaded('doador') && $this->doador) {
            $a = $this->doador->pessoaA?->nome ?? '';
            $b = $this->doador->pessoaB?->nome ?? '';
            $doadorRotulo = trim($a.' e '.$b, ' e') ?: null;
        }

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'qtd' => (float) $this->qtd,
            'unidade' => $this->unidade,
            'status' => $this->status,
            'doador_casal_id' => $this->doador_casal_id,
            'doador_rotulo' => $doadorRotulo,
            'valor_gasto' => $this->valor_gasto !== null ? (float) $this->valor_gasto : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
