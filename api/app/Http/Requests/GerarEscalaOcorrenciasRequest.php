<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EscalaAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GerarEscalaOcorrenciasRequest extends FormRequest
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
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after_or_equal:inicio'],
            'frequencia' => ['required', Rule::in(['semanal', 'mensal'])],
            'local' => ['nullable', 'string', 'max:255'],
            'titulo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
