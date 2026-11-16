<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommitEccCasaisImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EccVisibilityScope::class)->userCan('ecc.casais.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.row_index' => ['required', 'integer', 'min:0'],
            'decisions.*.action' => ['required', 'string', Rule::in(['create', 'update', 'skip'])],
            'decisions.*.casal_id' => ['nullable', 'string'],
            'decisions.*.apply_fields' => ['nullable', 'array'],
            'decisions.*.apply_fields.*' => ['string'],
        ];
    }
}
