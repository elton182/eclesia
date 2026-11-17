<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;

class MergeEccCasaisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EccVisibilityScope::class)->userCan('ecc.casais.manage');
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\In>|string>
     */
    public function rules(): array
    {
        return [
            'keep_id' => ['required', 'string'],
            'absorb_id' => ['required', 'string', 'different:keep_id'],
            'apply_fields' => ['sometimes', 'array'],
            'apply_fields.*' => ['string'],
        ];
    }
}
