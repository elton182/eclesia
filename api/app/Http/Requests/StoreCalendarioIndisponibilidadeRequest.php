<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarioIndisponibilidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nome_exibicao' => ['required', 'string', 'max:120'],
            'datas' => ['required', 'array', 'min:1'],
            'datas.*' => ['required', 'date'],
            'motivo' => ['nullable', 'string', 'max:255'],
            'pessoa_id' => ['nullable', 'string'],
        ];
    }
}
