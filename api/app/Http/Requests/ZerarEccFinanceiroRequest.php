<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZerarEccFinanceiroRequest extends FormRequest
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
            'confirmacao' => ['required', 'string', Rule::in(['zerar'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmacao.in' => 'Digite zerar para confirmar.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('confirmacao')) {
            $this->merge([
                'confirmacao' => mb_strtolower(trim((string) $this->input('confirmacao'))),
            ]);
        }
    }
}
