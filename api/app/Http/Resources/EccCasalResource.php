<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Pessoa;
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
        [$ele, $ela] = $this->resolveEleEla();

        return [
            'id' => $this->id,
            'equipe_id' => $this->ecc_equipe_id,
            'equipe_nome' => $this->equipe?->nome,
            // Compat: ordem de cadastro (pessoa A / B)
            'nome' => $this->pessoaA?->nome,
            'email' => $this->pessoaA?->email,
            'telefone' => $this->pessoaA?->telefone,
            'data_nascimento' => $this->pessoaA?->data_nascimento?->toDateString(),
            'sexo' => $this->pessoaA?->sexo,
            'nome_conjuge' => $this->pessoaB?->nome,
            'email_conjuge' => $this->pessoaB?->email,
            'telefone_conjuge' => $this->pessoaB?->telefone,
            'data_nascimento_conjuge' => $this->pessoaB?->data_nascimento?->toDateString(),
            'sexo_conjuge' => $this->pessoaB?->sexo,
            // Exibição por papel (Ele / Ela), independente da ordem de cadastro
            'ele' => $this->personPayload($ele),
            'ela' => $this->personPayload($ela),
            'endereco' => $this->endereco,
            'bairro' => $this->bairro,
            'cidade' => $this->cidade,
            'uf' => $this->uf,
            'cep' => $this->cep,
            'data_casamento' => $this->data_casamento?->toDateString(),
            'filhos' => $this->filhos,
            'observacoes' => $this->observacoes,
            'engajamento_paroquial' => $this->engajamento_paroquial,
            'habilidades' => $this->habilidades,
            'piloto' => (bool) $this->piloto,
            'anos_casados' => $this->anos_casados,
            'ecc_origem' => $this->ecc_origem,
            'funcao_dirigente' => $this->funcao_dirigente,
            'foi_coordenador_geral' => (bool) $this->foi_coordenador_geral,
            'ficha_com_foto' => filled($this->pessoaA?->foto_path) || filled($this->pessoaB?->foto_path),
            'etapas' => $this->etapasPayload(),
            'atividades' => $this->atividadesPayload(),
            'preferencias' => $this->preferenciasPayload(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{0: ?Pessoa, 1: ?Pessoa}
     */
    private function resolveEleEla(): array
    {
        $a = $this->pessoaA;
        $b = $this->pessoaB;
        $sexoA = strtoupper((string) ($a?->sexo ?? ''));
        $sexoB = strtoupper((string) ($b?->sexo ?? ''));

        if ($sexoA === 'M' && $sexoB === 'F') {
            return [$a, $b];
        }
        if ($sexoA === 'F' && $sexoB === 'M') {
            return [$b, $a];
        }

        // Sem sexo definido: mantém ordem de cadastro (A = Ele, B = Ela)
        return [$a, $b];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personPayload(?Pessoa $pessoa): ?array
    {
        if ($pessoa === null) {
            return null;
        }

        return [
            'id' => $pessoa->id,
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefone,
            'data_nascimento' => $pessoa->data_nascimento?->toDateString(),
            'sexo' => $pessoa->sexo,
            'foto_url' => $pessoa->fotoUrl(),
            'nome_usual' => $pessoa->nome_usual,
            'profissao' => $pessoa->profissao,
            'religiao' => $pessoa->religiao,
            'endereco_profissional' => $pessoa->endereco_profissional,
            'telefone_profissional' => $pessoa->telefone_profissional,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function etapasPayload(): array
    {
        $structured = $this->relationLoaded('etapas')
            ? $this->etapas
            : $this->etapas()->get();

        if ($structured->isNotEmpty()) {
            return $structured->map(static fn ($e) => [
                'etapa' => (int) $e->etapa,
                'ecc_numero' => $e->ecc_numero,
                'data' => $e->data?->toDateString(),
                'local' => $e->local,
            ])->values()->all();
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function atividadesPayload(): array
    {
        $rows = $this->relationLoaded('atividades')
            ? $this->atividades
            : $this->atividades()->with('equipeServico')->get();

        if ($this->relationLoaded('atividades') && $rows->isNotEmpty() && ! $rows->first()->relationLoaded('equipeServico')) {
            $rows->load('equipeServico');
        }

        return $rows->map(static fn ($a) => [
            'id' => $a->id,
            'ecc_numero' => $a->ecc_numero,
            'equipe_servico_id' => $a->ecc_equipe_servico_id,
            'equipe_servico_nome' => $a->equipeServico?->nome,
            'status' => $a->status,
            'observacao' => $a->observacao,
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function preferenciasPayload(): array
    {
        $rows = $this->relationLoaded('preferencias')
            ? $this->preferencias
            : $this->preferencias()->with('equipeServico')->get();

        if ($this->relationLoaded('preferencias') && $rows->isNotEmpty() && ! $rows->first()->relationLoaded('equipeServico')) {
            $rows->load('equipeServico');
        }

        return $rows->map(static fn ($p) => [
            'id' => $p->id,
            'equipe_servico_id' => $p->ecc_equipe_servico_id,
            'equipe_servico_nome' => $p->equipeServico?->nome,
            'ordem' => $p->ordem,
        ])->values()->all();
    }
}
