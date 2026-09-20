<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('site.settings.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Identidade (titulo/cores/logo) espelha tenants.name + app_settings — SPEC-011.
        return [
            'publicado' => ['sometimes', 'boolean'],
            'seo' => ['nullable', 'array'],
            'contato' => ['nullable', 'array'],
            'menu' => ['nullable', 'array'],
        ];
    }
}
