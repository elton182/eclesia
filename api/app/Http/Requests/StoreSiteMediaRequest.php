<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteMediaRequest extends FormRequest
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
        return [
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp,svg'],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }
}
