<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;

class RejeitarEccFinanceiroDepositoRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
