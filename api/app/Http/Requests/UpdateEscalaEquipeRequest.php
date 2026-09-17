<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EscalaAccessService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEscalaEquipeRequest extends FormRequest
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
            'nome' => ['sometimes', 'string', 'max:255'],
            'cor' => ['nullable', 'string', 'max:32'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'vagas_sugeridas' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
