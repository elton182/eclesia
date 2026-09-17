<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use App\Services\IgrejaContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoarEccItemCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EccVisibilityScope::class)->userCan('ecc.eventos.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $igrejaId = app(IgrejaContext::class)->current()->id;

        return [
            'casal_id' => [
                'required',
                'string',
                Rule::exists('casais', 'id')->where(fn ($q) => $q->where('igreja_id', $igrejaId)),
            ],
        ];
    }
}
