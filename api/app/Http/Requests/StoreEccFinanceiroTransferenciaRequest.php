<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;

class StoreEccFinanceiroTransferenciaRequest extends FormRequest
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
            'conta_origem_id' => ['required', 'string'],
            'conta_destino_id' => ['required', 'string'],
            'data' => ['required', 'date'],
            'historico' => ['required', 'string', 'max:2000'],
            'valor' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
