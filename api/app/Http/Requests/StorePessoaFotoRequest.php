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

        $pessoaId = (string) $this->route('id');

        return $scope->canAtualizarPessoa($pessoaId);
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
