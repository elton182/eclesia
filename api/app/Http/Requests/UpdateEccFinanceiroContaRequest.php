<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEccFinanceiroContaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EccVisibilityScope::class)->userCan('ecc.financeiro.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'string', 'max:255'],
            'tipo' => ['sometimes', 'string', Rule::in(['banco', 'especie'])],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'ativa' => ['sometimes', 'boolean'],
        ];
    }
}
