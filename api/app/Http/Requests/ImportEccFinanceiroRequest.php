<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportEccFinanceiroRequest extends FormRequest
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
            'modo' => ['sometimes', 'string', Rule::in(['replace', 'merge'])],
            'anos' => ['required', 'array', 'min:1'],
            'anos.*.ano' => ['required', 'integer', 'min:2000', 'max:2100'],
            'anos.*.contas' => ['sometimes', 'array'],
            'anos.*.contas.*.nome' => ['sometimes', 'string', 'max:255'],
            'anos.*.contas.*.tipo' => ['sometimes', 'string', Rule::in(['banco', 'especie'])],
            'anos.*.lancamentos' => ['present', 'array'],
            'anos.*.lancamentos.*.data' => ['required', 'date'],
            'anos.*.lancamentos.*.historico' => ['required', 'string', 'max:2000'],
            'anos.*.lancamentos.*.conta_tipo' => ['required', 'string', Rule::in(['banco', 'especie'])],
            'anos.*.lancamentos.*.tipo' => ['required', 'string', Rule::in(['entrada', 'saida'])],
            'anos.*.lancamentos.*.valor' => ['required', 'numeric', 'min:0'],
            'anos.*.lancamentos.*.abertura' => ['sometimes', 'boolean'],
            'anos.*.lancamentos.*.transferencia_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
