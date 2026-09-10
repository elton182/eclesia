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
        return [
            'publicado' => ['sometimes', 'boolean'],
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'subtitulo' => ['nullable', 'string', 'max:255'],
            'logo_path' => ['nullable', 'string', 'max:500'],
            'favicon_path' => ['nullable', 'string', 'max:500'],
            'cores' => ['nullable', 'array'],
            'seo' => ['nullable', 'array'],
            'contato' => ['nullable', 'array'],
            'menu' => ['nullable', 'array'],
        ];
    }
}
