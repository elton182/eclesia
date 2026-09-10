<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SiteFormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSiteFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('site.forms.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:120', 'alpha_dash', 'unique:site_forms,slug'],
            'descricao' => ['nullable', 'string'],
            'ativo' => ['sometimes', 'boolean'],
            'destino_email' => ['nullable', 'email', 'max:255'],
            'sucesso_mensagem' => ['nullable', 'string', 'max:500'],
            'fields' => ['sometimes', 'array'],
            'fields.*.nome' => ['required_with:fields', 'string', 'max:64'],
            'fields.*.label' => ['required_with:fields', 'string', 'max:255'],
            'fields.*.tipo' => ['required_with:fields', 'string', Rule::in(SiteFormField::TIPOS)],
            'fields.*.obrigatorio' => ['sometimes', 'boolean'],
            'fields.*.opcoes' => ['nullable', 'array'],
            'fields.*.ordem' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
