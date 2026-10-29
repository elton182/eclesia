<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientDiagnosticRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'motivo' => ['sometimes', 'string', Rule::in(['foto', 'geral', 'pwa', 'outro'])],
            'nota' => ['nullable', 'string', 'max:500'],
            'meta' => ['required', 'array'],
            'meta.app_version' => ['nullable', 'string', 'max:64'],
            'meta.standalone' => ['nullable', 'boolean'],
            'meta.camera_permission' => ['nullable', 'string', 'max:32'],
            'meta.href' => ['nullable', 'string', 'max:500'],
            'meta.online' => ['nullable', 'boolean'],
            'meta.platform' => ['nullable', 'string', 'max:120'],
            'meta.viewport' => ['nullable', 'string', 'max:40'],
            'events' => ['required', 'array', 'max:100'],
            'events.*.t' => ['required', 'integer'],
            'events.*.type' => ['required', 'string', 'max:80'],
            'events.*.data' => ['nullable', 'array'],
        ];
    }
}
