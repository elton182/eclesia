<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreAppBrandingLogoRequest extends FormRequest
{
    public function authorize(): bool
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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
