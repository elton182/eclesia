<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEccFinanceiroLancamentoRequest extends FormRequest
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
            'conta_id' => ['sometimes', 'string'],
            'data' => ['sometimes', 'date'],
            'historico' => ['sometimes', 'string', 'max:2000'],
            'tipo' => ['sometimes', 'string', Rule::in(['entrada', 'saida'])],
            'valor' => ['sometimes', 'numeric', 'min:0.01'],
        ];
    }
}
