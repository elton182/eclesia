<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EscalaAccessService;
use Illuminate\Foundation\Http\FormRequest;

class StoreEscalaAtribuicaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EscalaAccessService::class)->canManage();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'escala_equipe_id' => ['required', 'string'],
            'pessoa_id' => ['nullable', 'string'],
            'casal_id' => ['nullable', 'string'],
        ];
    }
}
