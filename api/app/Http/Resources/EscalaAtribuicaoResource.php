<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EscalaAtribuicao
 */
class EscalaAtribuicaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $casalRotulo = null;
        if ($this->relationLoaded('casal') && $this->casal) {
            $a = $this->casal->pessoaA?->nome ?? '';
            $b = $this->casal->pessoaB?->nome ?? '';
            $casalRotulo = trim($a.' e '.$b, ' e');
        }

        return [
            'id' => $this->id,
            'escala_ocorrencia_id' => $this->escala_ocorrencia_id,
            'escala_equipe_id' => $this->escala_equipe_id,
            'equipe_nome' => $this->whenLoaded('equipe', fn () => $this->equipe?->nome),
            'pessoa_id' => $this->pessoa_id,
            'casal_id' => $this->casal_id,
            'pessoa_nome' => $this->whenLoaded('pessoa', fn () => $this->pessoa?->nome),
            'casal_rotulo' => $casalRotulo,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
