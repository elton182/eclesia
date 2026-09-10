<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SitePage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('site.pages.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SitePage|null $page */
        $page = $this->route('page');

        return [
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                'alpha_dash',
                Rule::unique('site_pages', 'slug')->ignore($page?->id),
            ],
            'titulo' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'string', Rule::in([SitePage::STATUS_RASCUNHO, SitePage::STATUS_PUBLICADO])],
            'is_home' => ['sometimes', 'boolean'],
            'ordem' => ['sometimes', 'integer', 'min:0'],
            'mostrar_no_menu' => ['sometimes', 'boolean'],
            'seo' => ['nullable', 'array'],
            'blocks' => ['sometimes', 'array'],
            'blocks.*.tipo' => ['required_with:blocks', 'string', Rule::in(SitePage::BLOCK_TYPES)],
            'blocks.*.ordem' => ['sometimes', 'integer', 'min:0'],
            'blocks.*.visivel' => ['sometimes', 'boolean'],
            'blocks.*.payload' => ['nullable', 'array'],
        ];
    }
}
