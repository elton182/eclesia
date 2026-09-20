<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarioItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'data' => ['required', 'date'],
            'tipo_id' => ['required', 'string'],
            'local_id' => ['nullable', 'string'],
            'hora' => ['nullable', 'date_format:H:i'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'pessoa_id' => ['nullable', 'string'],
            'celebrante_nome' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:500'],
            'observacao_id' => ['nullable', 'string'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            // legado: aceito se enviado sem tipo_id em clientes antigos — preferir tipo_id
            'secao' => ['sometimes', 'string'],
        ];
    }
}
