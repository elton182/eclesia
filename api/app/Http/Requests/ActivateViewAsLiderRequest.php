<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ViewAsLiderService;
use Illuminate\Foundation\Http\FormRequest;

class ActivateViewAsLiderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return app(ViewAsLiderService::class)->canActivate($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'equipe_ids' => ['required', 'array', 'min:1'],
            'equipe_ids.*' => ['required', 'string', 'max:26'],
        ];
    }
}
