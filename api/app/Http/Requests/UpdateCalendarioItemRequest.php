<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CalendarioItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarioItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'data' => ['sometimes', 'date'],
            'secao' => ['sometimes', Rule::in(CalendarioItem::SECOES)],
            'local_id' => ['nullable', 'string'],
            'hora' => ['nullable', 'date_format:H:i'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'pessoa_id' => ['nullable', 'string'],
            'celebrante_nome' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:500'],
            'observacao_id' => ['nullable', 'string'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
