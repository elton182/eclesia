<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EccPerguntaCampo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEccPerguntaRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:180'],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'responsavel_user_id' => ['required', 'string', 'max:26'],
            'campos' => ['required', 'array', 'min:1'],
            'campos.*.tipo' => ['required', 'string', Rule::in([
                EccPerguntaCampo::TIPO_TEXTO_CURTO,
                EccPerguntaCampo::TIPO_TEXTO_LONGO,
                EccPerguntaCampo::TIPO_LISTA,
            ])],
            'campos.*.rotulo' => ['required', 'string', 'max:180'],
            'campos.*.obrigatorio' => ['sometimes', 'boolean'],
            'campos.*.ordem' => ['sometimes', 'integer', 'min:0'],
            'campos.*.opcoes' => ['nullable', 'array'],
            'campos.*.opcoes.*' => ['string', 'max:120'],
        ];
    }
}
