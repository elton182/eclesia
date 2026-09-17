<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EscalaAccessService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEscalaOcorrenciaRequest extends FormRequest
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
            'titulo' => ['nullable', 'string', 'max:255'],
            'inicia_em' => ['sometimes', 'date'],
            'termina_em' => ['nullable', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
        ];
    }
}
