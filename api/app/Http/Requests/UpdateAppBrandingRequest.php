<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAppBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->userCanManageBranding();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cores' => ['nullable', 'array'],
            'cores.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cores.secondary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cores.text' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cores.text_muted' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cores.on_primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cores = $this->input('cores');
            if (! is_array($cores)) {
                return;
            }

            $allowed = ['primary', 'secondary', 'text', 'text_muted', 'on_primary'];
            $extra = array_diff(array_keys($cores), $allowed);
            if ($extra !== []) {
                $validator->errors()->add('cores', 'Chaves de cores não permitidas: '.implode(', ', $extra));
            }
        });
    }

    private function userCanManageBranding(): bool
    {
        $user = $this->user();

        if ($user instanceof SuperAdmin) {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        $previous = getPermissionsTeamId();
        setPermissionsTeamId(null);
        $ok = $user->hasRole('admin-tenant');
        setPermissionsTeamId($previous);

        return $ok;
    }
}
