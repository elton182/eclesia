<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListAuditLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => [
                'nullable',
                'string',
                Rule::in([
                    'created',
                    'updated',
                    'deleted',
                    'login_success',
                    'login_failed',
                    'logout',
                    'roles_synced',
                ]),
            ],
            'auditable_type' => ['nullable', 'string', 'max:255'],
            'actor_user_ulid' => ['nullable', 'string', 'max:26'],
            'desde' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:desde'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
