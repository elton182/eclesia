<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\EccEquipe
 */
class EccEquipeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $acessoUser = $this->relationLoaded('acessoUser')
            ? $this->acessoUser
            : $this->acessoUser()->first();

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cor' => $this->cor,
            'casais_count' => $this->casais_count ?? $this->casais()->count(),
            'tem_acesso' => $this->acesso_user_id !== null,
            'acesso_username' => $acessoUser?->username,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
