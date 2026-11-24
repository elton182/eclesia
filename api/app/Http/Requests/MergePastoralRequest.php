<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MergePastoralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'keep_id' => ['required', 'string', 'exists:pastorais,id'],
            'absorb_id' => ['required', 'string', 'different:keep_id', 'exists:pastorais,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'absorb_id.different' => 'Selecione duas pastorais diferentes para unificar.',
        ];
    }
}
