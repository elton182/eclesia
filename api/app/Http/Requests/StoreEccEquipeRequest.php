<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;

class StoreEccEquipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(EccVisibilityScope::class)->userCan('ecc.equipes.manage');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cor' => ['nullable', 'string', 'max:32'],
        ];
    }
}
