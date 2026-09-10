<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;

class ImportEccCasaisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EccVisibilityScope::class)->userCan('ecc.casais.manage');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
        ];
    }
}
