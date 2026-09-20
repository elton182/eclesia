<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarioSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'local_id' => ['sometimes', 'required', 'string'],
            'dia_semana' => ['sometimes', 'integer', 'min:0', 'max:6'],
            'hora' => ['sometimes', 'date_format:H:i'],
            'secao' => ['sometimes', 'in:fds,semana'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'ativo' => ['sometimes', 'boolean'],
        ];
    }
}
