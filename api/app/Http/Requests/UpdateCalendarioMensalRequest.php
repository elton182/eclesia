<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarioMensalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:255'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
