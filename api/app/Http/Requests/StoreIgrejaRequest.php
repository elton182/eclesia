<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Igreja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIgrejaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Igreja::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', Rule::in(Igreja::TIPOS)],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:igrejas,slug'],
            'endereco' => ['nullable', 'string', 'max:500'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
            'diocese' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'publicado_no_site' => ['sometimes', 'boolean'],
            'descricao_publica' => ['nullable', 'string'],
            'horario_missas' => ['nullable', 'string'],
            'banner_media_id' => ['nullable', 'string', 'exists:site_media,id'],
        ];
    }
}
