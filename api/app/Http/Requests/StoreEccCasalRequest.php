<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EccCasalAtividade;
use App\Services\EccVisibilityScope;
use App\Services\IgrejaContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEccCasalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EccVisibilityScope::class)->userCan('ecc.casais.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->baseRules(), $this->fichaRules());
    }

    /**
     * @return array<string, mixed>
     */
    protected function baseRules(): array
    {
        return [
            'equipe_id' => ['nullable', 'string'],
            'equipe' => ['nullable', 'string', 'max:255'],
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'data_nascimento' => ['nullable', 'string'],
            'nome_conjuge' => ['required', 'string', 'max:255'],
            'email_conjuge' => ['nullable', 'email', 'max:255'],
            'telefone_conjuge' => ['nullable', 'string', 'max:50'],
            'data_nascimento_conjuge' => ['nullable', 'string'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
            'cep' => ['nullable', 'string', 'max:20'],
            'data_casamento' => ['nullable', 'string'],
            'filhos' => ['nullable', 'string'],
            'observacoes' => ['nullable', 'string'],
            'piloto' => ['sometimes', 'boolean'],
            'anos_casados' => ['nullable', 'integer', 'min:0', 'max:120'],
            'ecc_origem' => ['nullable', 'string', 'max:100'],
            'funcao_dirigente' => ['nullable', 'string'],
            'foi_coordenador_geral' => ['sometimes', 'boolean'],
            'engajamento_paroquial' => ['nullable', 'string'],
            'habilidades' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function fichaRules(): array
    {
        $igrejaId = app(IgrejaContext::class)->current()->id;

        return [
            'ele' => ['sometimes', 'array'],
            'ele.nome_usual' => ['nullable', 'string', 'max:255'],
            'ele.profissao' => ['nullable', 'string', 'max:255'],
            'ele.religiao' => ['nullable', 'string', 'max:255'],
            'ele.endereco_profissional' => ['nullable', 'string', 'max:500'],
            'ele.telefone_profissional' => ['nullable', 'string', 'max:50'],
            'ela' => ['sometimes', 'array'],
            'ela.nome_usual' => ['nullable', 'string', 'max:255'],
            'ela.profissao' => ['nullable', 'string', 'max:255'],
            'ela.religiao' => ['nullable', 'string', 'max:255'],
            'ela.endereco_profissional' => ['nullable', 'string', 'max:500'],
            'ela.telefone_profissional' => ['nullable', 'string', 'max:50'],
            'etapas' => ['sometimes', 'array'],
            'etapas.*.etapa' => ['required', 'integer', Rule::in([1, 2, 3])],
            'etapas.*.ecc_numero' => ['nullable', 'string', 'max:20'],
            'etapas.*.data' => ['nullable', 'string'],
            'etapas.*.local' => ['nullable', 'string', 'max:255'],
            'atividades' => ['sometimes', 'array'],
            'atividades.*.ecc_numero' => ['required', 'string', 'max:20'],
            'atividades.*.equipe_servico_id' => [
                'required',
                'string',
                Rule::exists('ecc_equipes_servico', 'id')->where('igreja_id', $igrejaId),
            ],
            'atividades.*.status' => ['required', 'string', Rule::in(EccCasalAtividade::STATUS)],
            'atividades.*.observacao' => ['nullable', 'string'],
            'preferencias' => ['sometimes', 'array'],
            'preferencias.*.equipe_servico_id' => [
                'required',
                'string',
                Rule::exists('ecc_equipes_servico', 'id')->where('igreja_id', $igrejaId),
            ],
            'preferencias.*.ordem' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
