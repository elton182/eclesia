<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Casal
 */
class EccCasalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipe_id' => $this->ecc_equipe_id,
            'equipe_nome' => $this->equipe?->nome,
            'nome' => $this->pessoaA?->nome,
            'email' => $this->pessoaA?->email,
            'telefone' => $this->pessoaA?->telefone,
            'data_nascimento' => $this->pessoaA?->data_nascimento?->toDateString(),
            'nome_conjuge' => $this->pessoaB?->nome,
            'email_conjuge' => $this->pessoaB?->email,
            'telefone_conjuge' => $this->pessoaB?->telefone,
            'data_nascimento_conjuge' => $this->pessoaB?->data_nascimento?->toDateString(),
            'endereco' => $this->endereco,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'cep' => $this->cep,
            'data_casamento' => $this->data_casamento?->toDateString(),
            'filhos' => $this->filhos,
            'observacoes' => $this->observacoes,
            'piloto' => (bool) $this->piloto,
            'anos_casados' => $this->anos_casados,
            'ecc_origem' => $this->ecc_origem,
            'experiencia_servico' => $this->experiencia_servico,
            'preferencia_funcao' => $this->preferencia_funcao,
            'funcao_dirigente' => $this->funcao_dirigente,
            'foi_coordenador_geral' => (bool) $this->foi_coordenador_geral,
            'ficha_com_foto' => (bool) $this->ficha_com_foto,
            'etapa_2' => $this->etapa_2,
            'etapa_3' => $this->etapa_3,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
