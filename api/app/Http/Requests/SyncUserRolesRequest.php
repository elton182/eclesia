<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncUserRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.assign') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'igreja_id' => ['nullable', 'string', 'max:26'],
            'roles' => ['required', 'array'],
            'roles.*.name' => ['required', 'string', Rule::in(RolesAndPermissionsSeeder::ROLES)],
            'roles.*.equipe_ids' => ['nullable', 'array'],
            'roles.*.equipe_ids.*' => ['string', 'max:26'],
        ];
    }
}
