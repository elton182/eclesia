<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EccPerguntaCampo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEccPerguntaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'required', 'string', 'max:180'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'mensagem_pos_envio' => ['nullable', 'string', 'max:2000'],
            'responsavel_user_id' => ['sometimes', 'required', 'string', 'max:26'],
            'campos' => ['sometimes', 'array', 'min:1'],
            'campos.*.tipo' => ['required_with:campos', 'string', Rule::in([
                EccPerguntaCampo::TIPO_TEXTO_CURTO,
                EccPerguntaCampo::TIPO_TEXTO_LONGO,
                EccPerguntaCampo::TIPO_LISTA,
            ])],
            'campos.*.rotulo' => ['required_with:campos', 'string', 'max:180'],
            'campos.*.obrigatorio' => ['sometimes', 'boolean'],
            'campos.*.ordem' => ['sometimes', 'integer', 'min:0'],
            'campos.*.opcoes' => ['nullable', 'array'],
            'campos.*.opcoes.*' => ['string', 'max:120'],
        ];
    }
}
