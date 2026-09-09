<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Igreja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIgrejaRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Igreja|string|null $igreja */
        $igreja = $this->route('igreja');

        if (is_string($igreja)) {
            $igreja = Igreja::query()->find($igreja);
        }

        if (! $igreja instanceof Igreja) {
            return false;
        }

        return $this->user()?->can('update', $igreja) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'tipo' => ['sometimes', 'required', 'string', Rule::in(Igreja::TIPOS)],
            'endereco' => ['nullable', 'string', 'max:500'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
            'cep' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
