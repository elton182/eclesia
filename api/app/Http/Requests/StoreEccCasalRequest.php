<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEccCasalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
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
            'experiencia_servico' => ['nullable', 'string'],
            'preferencia_funcao' => ['nullable', 'string'],
            'funcao_dirigente' => ['nullable', 'string'],
            'foi_coordenador_geral' => ['sometimes', 'boolean'],
            'ficha_com_foto' => ['sometimes', 'boolean'],
            'etapa_2' => ['nullable', 'string', 'max:50'],
            'etapa_3' => ['nullable', 'string', 'max:50'],
        ];
    }
}
