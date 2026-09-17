<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use App\Services\IgrejaContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEccEventoDoacaoRequest extends FormRequest
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
            'valor' => ['required', 'numeric', 'min:0.01'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'casal_id' => [
                'nullable',
                'string',
                Rule::exists('casais', 'id')->where(fn ($q) => $q->where('igreja_id', $igrejaId)),
            ],
            'ecc_equipe_id' => [
                'nullable',
                'string',
                Rule::exists('ecc_equipes', 'id')->where(fn ($q) => $q->where('igreja_id', $igrejaId)),
            ],
            'doador_nome' => ['nullable', 'string', 'max:255'],
        ];
    }
}
