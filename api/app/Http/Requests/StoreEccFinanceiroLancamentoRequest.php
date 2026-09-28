<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEccFinanceiroLancamentoRequest extends FormRequest
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
            'conta_id' => ['required', 'string'],
            'data' => ['required', 'date'],
            'historico' => ['required', 'string', 'max:2000'],
            'tipo' => ['required', 'string', Rule::in(['entrada', 'saida'])],
            'valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
