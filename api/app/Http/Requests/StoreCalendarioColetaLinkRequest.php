<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarioColetaLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rotulo' => ['nullable', 'string', 'max:120'],
            'expira_em' => ['nullable', 'date'],
        ];
    }
}
