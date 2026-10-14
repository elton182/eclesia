<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;

class StoreEccFinanceiroDepositoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $visibility = app(EccVisibilityScope::class);

        return $visibility->userCan('ecc.financeiro.depositos.submeter')
            || $visibility->userCan('ecc.financeiro.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'equipe_id' => ['required', 'string'],
            'competencia' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'conta_id' => ['nullable', 'string'],
            'comprovante' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
