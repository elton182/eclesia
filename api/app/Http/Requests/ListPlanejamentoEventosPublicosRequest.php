<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListPlanejamentoEventosPublicosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pastoral_nome' => ['sometimes', 'nullable', 'string', 'min:2', 'max:120'],
        ];
    }
}
