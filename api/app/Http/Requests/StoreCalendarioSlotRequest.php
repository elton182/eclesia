<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarioSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'local_id' => ['required', 'string'],
            'dia_semana' => ['required', 'integer', 'min:0', 'max:6'],
            'hora' => ['required', 'date_format:H:i'],
            'secao' => ['sometimes', 'in:fds,semana'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
