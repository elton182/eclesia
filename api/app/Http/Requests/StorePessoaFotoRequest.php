<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\EccVisibilityScope;
use Illuminate\Foundation\Http\FormRequest;

class StorePessoaFotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scope = app(EccVisibilityScope::class);

        return $scope->userCan('pessoas.manage') || $scope->userCan('ecc.casais.manage');
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
